"""Etapa 2: con día, hora y profesor ya fijos, asigna un espacio a cada sesión.

Un modelo CP-SAT pequeño por (plantel, tipo de espacio). La etapa 1 ya garantizó, por conteo,
que en cada átomo alcanzan los espacios; aquí se elige cuál, sin cambiarlo dentro de un bloque.
"""

from collections import defaultdict

from ortools.sat.python import cp_model

from solver.colocacion import Colocada, colocar
from solver.contract import SesionIn
from solver.instance import Instance


def assign_rooms(
    inst: Instance, sesiones: list[SesionIn], tiempo_limite_s: float
) -> list[SesionIn] | None:
    """Las sesiones con `espacio` asignado, o `None` si no hay asignación posible."""
    colocadas, _ = colocar(inst, sesiones)
    grupos: dict[tuple[str, str], list[Colocada]] = defaultdict(list)
    for c in colocadas:
        tipo = inst.materias[inst.cursos[c.curso].materia].tipo_espacio
        grupos[(c.plantel, tipo)].append(c)
    asignado: dict[int, str] = {}  # id(sesión) -> espacio
    tiempo = tiempo_limite_s / max(1, len(grupos))
    for (plantel, tipo), lista in sorted(grupos.items()):
        espacios = [e for e in inst.espacios.values() if e.plantel == plantel and e.tipo == tipo]
        resultado = _resolver(inst, lista, espacios, tiempo)
        if resultado is None:
            return None
        asignado.update(resultado)
    return [
        s.model_copy(update={"espacio": asignado[id(s)]}) if id(s) in asignado else s
        for s in sesiones
    ]


def _resolver(inst: Instance, lista: list[Colocada], espacios, tiempo: float):
    m = cp_model.CpModel()
    z: dict[tuple[int, str], cp_model.IntVar] = {}
    for i, c in enumerate(lista):
        alumnos = inst.curso_alumnos(c.curso)
        opciones = [e.id for e in espacios if e.capacidad >= alumnos]
        if c.sesion.espacio is not None:
            opciones = [e for e in opciones if e == c.sesion.espacio]
        if not opciones:
            return None
        for e in opciones:
            z[(i, e)] = m.NewBoolVar(f"z[{i},{e}]")
        m.AddExactlyOne(z[(i, e)] for e in opciones)
    por_ranura: dict[tuple[str, int, int], list[cp_model.IntVar]] = defaultdict(list)
    for (i, e), zv in z.items():
        for a in lista[i].atomos:
            por_ranura[(e, lista[i].dia, a)].append(zv)
    for lits in por_ranura.values():
        if len(lits) > 1:
            m.AddAtMostOne(lits)
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = max(0.1, tiempo)
    solver.parameters.num_workers = 1
    if solver.Solve(m) not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return None
    return {id(lista[i].sesion): e for (i, e), zv in z.items() if solver.Value(zv)}
