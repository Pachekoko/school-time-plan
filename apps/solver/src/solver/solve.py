"""Orquestación: prechecks → modelo CP-SAT → espacios → puntaje, y diagnóstico si no hay
solución."""

import time

from ortools.sat.python import cp_model

from solver.contract import (
    Diagnostico,
    Estadisticas,
    ReglasConfig,
    SesionIn,
    SolveRequest,
    SolveResponse,
    ValidateRequest,
    ValidateResponse,
)
from solver.diagnosis import explain_core
from solver.instance import Instance, build_instance
from solver.messages import nombre
from solver.model.hard import Supuestos, add_hard
from solver.model.rooms import assign_rooms
from solver.model.soft import add_soft, build_objective
from solver.model.variables import Vars, build_vars
from solver.prechecks import run_prechecks
from solver.scoring import REGLAS_S, score
from solver.validator import validate_schedule

PARTE_ESPACIOS = 0.2  # fracción del tiempo límite para la etapa de espacios
PARTE_FASE_A = 0.6  # tope de la fase A dentro del tiempo de búsqueda
LIMITE_DIAGNOSTICO_S = 30


def solve(req: SolveRequest) -> SolveResponse:
    """Dos fases: A busca un horario que cumpla las reglas duras y las obligatorias (sin
    preferencias, que es lo que más tarda); B optimiza las preferencias partiendo de A."""
    inicio = time.perf_counter()
    inst = build_instance(req)  # InstanceError: la API responde 422
    p = req.parametros
    diagnostico = run_prechecks(inst)
    if diagnostico and not p.permitir_parcial:
        return _respuesta(inst, "sin_solucion", [], diagnostico, inicio, "NO_EJECUTADO", None)

    tiempo_espacios = max(1.0, p.tiempo_limite_s * PARTE_ESPACIOS)
    fin_busqueda = inicio + max(1.0, p.tiempo_limite_s - tiempo_espacios)

    m, v, _ = _modelo(inst, con_supuestos=False, solo_factibilidad=True)
    solver = _solver(
        max(1.0, (fin_busqueda - time.perf_counter()) * PARTE_FASE_A), p.num_workers, p.semilla
    )
    status = solver.Solve(m)
    if status == cp_model.INFEASIBLE:
        diagnostico += _diagnosticar(inst, p.tiempo_limite_s)
        return _respuesta(
            inst, "sin_solucion", [], diagnostico, inicio, solver.StatusName(status), m
        )
    if status == cp_model.UNKNOWN:
        diagnostico.append(
            Diagnostico(
                codigo="TIEMPO",
                regla="",
                entidades=[],
                mensaje=f"No se encontró un horario en {p.tiempo_limite_s:g} s; da más tiempo o "
                "afloja alguna regla obligatoria",
            )
        )
        return _respuesta(
            inst, map_status(status, False), [], diagnostico, inicio, solver.StatusName(status), m
        )
    sesiones = extract_sesiones(inst, v, solver)

    restante = fin_busqueda - time.perf_counter()
    if _hay_preferencias(p.reglas) and restante >= 1:
        mb, vb, _ = _modelo(inst, con_supuestos=False)
        _sugerir(mb, vb, v, solver)
        solver_b = _solver(restante, p.num_workers, p.semilla)
        status_b = solver_b.Solve(mb)
        if status_b in (cp_model.OPTIMAL, cp_model.FEASIBLE):
            m, solver, status = mb, solver_b, status_b
            sesiones = extract_sesiones(inst, vb, solver_b)

    con_espacio = assign_rooms(inst, sesiones, tiempo_espacios)
    if con_espacio is None:
        planteles = ", ".join(nombre(inst, "plantel", pl) for pl in sorted(inst.planteles))
        diagnostico.append(
            Diagnostico(
                codigo="ESPACIOS",
                regla="H4",
                entidades=[],
                mensaje=f"No se pudieron asignar espacios sin choques; revisa la capacidad de "
                f"{planteles}",
            )
        )
        return _respuesta(
            inst, "sin_solucion", [], diagnostico, inicio, solver.StatusName(status), m
        )
    estado = map_status(status, any(s.sin_acomodar for s in con_espacio))
    return _respuesta(inst, estado, con_espacio, diagnostico, inicio, solver.StatusName(status), m)


def reglas_de_factibilidad(reglas: ReglasConfig) -> ReglasConfig:
    """Copia de las reglas con las preferencias apagadas; las obligatorias se quedan."""
    copia = reglas.model_copy(deep=True)
    for r in REGLAS_S:
        if getattr(copia, r).modo == "preferencia":
            setattr(copia, r, getattr(copia, r).model_copy(update={"modo": "apagada"}))
    return copia


def _hay_preferencias(reglas: ReglasConfig) -> bool:
    return any(getattr(reglas, r).modo == "preferencia" for r in REGLAS_S)


def _sugerir(m: cp_model.CpModel, v: Vars, previo: Vars, solver: cp_model.CpSolver) -> None:
    """Sugiere a la fase B la solución de la fase A (mismas llaves de variables)."""
    vistos: set[int] = set()
    for nombre_var in ("x", "t", "sel", "y", "acomodado"):
        actuales, anteriores = getattr(v, nombre_var), getattr(previo, nombre_var)
        for clave, var in actuales.items():
            if var.Index() in vistos or clave not in anteriores:
                continue
            vistos.add(var.Index())
            m.AddHint(var, solver.Value(anteriores[clave]))


def validate(req: ValidateRequest) -> ValidateResponse:
    inst = build_instance(req.problema)
    puntaje, desglose = score(inst, req.sesiones)
    return ValidateResponse(
        violaciones=validate_schedule(inst, req.sesiones), desglose=desglose, puntaje=puntaje
    )


def map_status(status: int, hay_sin_acomodar: bool) -> str:
    if status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return "factible_parcial" if hay_sin_acomodar else "ok"
    if status == cp_model.INFEASIBLE:
        return "sin_solucion"
    if status == cp_model.UNKNOWN:
        return "tiempo_agotado"
    raise RuntimeError(f"CP-SAT devolvió {cp_model.CpSolver().StatusName(status)}")


def extract_sesiones(inst: Instance, v: Vars, solver: cp_model.CpSolver) -> list[SesionIn]:
    """Sesiones de la solución; los cursos sin acomodar salen como `sin_acomodar` (patrón 0)."""
    fijadas = {(f.curso, f.patron, f.bloque): f for f in inst.req.fijadas}
    quien: dict[tuple[str, int, int], str] = {}
    for (b_id, p, d, o), tv in v.t.items():
        if solver.Value(tv):
            quien[(b_id, d, o)] = p
    out = []
    for (b_id, d, o), xv in v.x.items():
        if not solver.Value(xv):
            continue
        b = v.bloque[b_id]
        f = fijadas.get((b.curso, b.patron, b.indice))
        out.append(
            SesionIn(
                curso=b.curso,
                patron=b.patron,
                bloque=b.indice,
                dia=d,
                orden_inicio=o,
                espacio=f.espacio if f is not None else None,
                profesor=quien.get((b_id, d, o)),
                fijada=f is not None,
            )
        )
    for c in inst.req.cursos:
        if not solver.Value(v.acomodado[c.id]):
            out += [
                SesionIn(curso=c.id, patron=0, bloque=b.indice, sin_acomodar=True)
                for b in inst.bloques_por_curso[c.id][0]
            ]
    return sorted(out, key=lambda s: (s.curso, s.patron, s.bloque))


def _modelo(inst: Instance, con_supuestos: bool, solo_factibilidad: bool = False):
    m = cp_model.CpModel()
    v = build_vars(m, inst)
    sup = Supuestos(m)
    reglas = inst.req.parametros.reglas
    if solo_factibilidad:
        reglas = reglas_de_factibilidad(reglas)
    add_hard(m, inst, v, sup, inst.req.parametros.permitir_parcial)
    crudos = add_soft(m, inst, v, sup, reglas)
    if con_supuestos:
        sup.como_supuestos()
        return m, v, sup
    sup.fijar()
    if solo_factibilidad:
        if inst.req.parametros.permitir_parcial:
            m.Minimize(sum(1 - a for a in v.acomodado.values()))
    else:
        m.Minimize(build_objective(v, crudos, reglas))
    return m, v, sup


def _solver(tiempo_s: float, workers: int, semilla: int) -> cp_model.CpSolver:
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = tiempo_s
    solver.parameters.num_workers = workers
    solver.parameters.random_seed = semilla
    return solver


def _diagnosticar(inst: Instance, tiempo_limite_s: float) -> list[Diagnostico]:
    """Segunda pasada con los supuestos activos y 1 worker para leer el núcleo."""
    m, _, sup = _modelo(inst, con_supuestos=True)
    solver = _solver(min(LIMITE_DIAGNOSTICO_S, tiempo_limite_s), 1, inst.req.parametros.semilla)
    if solver.Solve(m) != cp_model.INFEASIBLE:
        return [
            Diagnostico(
                codigo="NUCLEO_TIEMPO",
                regla="",
                entidades=[],
                mensaje="Está comprobado que no hay horario que cumpla todas las reglas, pero "
                "no dio tiempo de aislar cuáles chocan; prueba con más tiempo",
            )
        ]
    explicacion = explain_core(inst, sup, list(solver.SufficientAssumptionsForInfeasibility()))
    return explicacion or [
        Diagnostico(
            codigo="NUCLEO_VACIO",
            regla="H1",
            entidades=[],
            mensaje="No hay forma de acomodar todos los bloques con su disponibilidad y sus "
            "inicios posibles; revisa las plantillas y la disponibilidad",
        )
    ]


def _respuesta(inst, estado, sesiones, diagnostico, inicio, estado_cp_sat, m) -> SolveResponse:
    puntaje, desglose = score(inst, sesiones)
    proto = m.Proto() if m is not None else None
    return SolveResponse(
        estado=estado,
        sesiones=sesiones,
        puntaje=puntaje,
        desglose=desglose,
        diagnostico=diagnostico,
        estadisticas=Estadisticas(
            tiempo_s=round(time.perf_counter() - inicio, 3),
            estado_cp_sat=estado_cp_sat,
            variables=len(proto.variables) if proto is not None else 0,
            restricciones=len(proto.constraints) if proto is not None else 0,
        ),
    )
