import json

import pytest
from ortools.sat.python import cp_model

from solver.contract import (
    CursoIn,
    RefFranja,
    SesionIn,
    SolveRequest,
    ValidateRequest,
)
from solver.instance import InstanceError, build_instance
from solver.scoring import score
from solver.solve import map_status, mejor_de, reglas_de_factibilidad, solve, validate
from solver.validator import validate_schedule

from helpers import FIXTURES


def _fixture(nombre: str) -> SolveRequest:
    caso = json.loads((FIXTURES / "solve" / f"{nombre}.json").read_text(encoding="utf-8"))
    return SolveRequest.model_validate(caso["problema"])


def test_solucion_pasa_validador():
    req = _fixture("factible_basico")
    resp = solve(req)
    assert resp.estado == "ok"
    assert validate_schedule(build_instance(req), resp.sesiones) == []
    assert all(s.espacio and not s.sin_acomodar for s in resp.sesiones)
    assert all(s.profesor for s in resp.sesiones)


def test_puntaje_coincide_con_score():
    req = _fixture("factible_basico")
    resp = solve(req)
    total, desglose = score(build_instance(req), resp.sesiones)
    assert resp.puntaje == total and resp.desglose == desglose


def test_reproducible_con_un_worker():
    req = _fixture("factible_basico")
    assert solve(req).sesiones == solve(req).sesiones


def test_map_status():
    assert map_status(cp_model.OPTIMAL, False) == "ok"
    assert map_status(cp_model.FEASIBLE, False) == "ok"
    assert map_status(cp_model.FEASIBLE, True) == "factible_parcial"
    assert map_status(cp_model.INFEASIBLE, False) == "sin_solucion"
    assert map_status(cp_model.UNKNOWN, False) == "tiempo_agotado"
    with pytest.raises(RuntimeError):
        map_status(cp_model.MODEL_INVALID, False)


def test_infactible_da_nucleo_con_mensaje():
    resp = solve(_fixture("infactible_disponibilidad"))
    assert resp.estado == "sin_solucion" and resp.sesiones == []
    assert resp.diagnostico and all(d.mensaje and d.entidades for d in resp.diagnostico)
    assert {d.codigo for d in resp.diagnostico} == {"NUCLEO"}
    assert "El grupo 1A no cabe en su semana con las demás reglas" in [
        d.mensaje for d in resp.diagnostico
    ]


def test_vacio_ok():
    resp = solve(_fixture("vacio"))
    assert resp.estado == "ok" and resp.model_dump()["sesiones"] == [] and resp.puntaje == 0


def test_prechecks_cortan_antes_del_modelo(minimo):
    minimo.profesores[0].materias = []
    resp = solve(minimo)
    assert resp.estado == "sin_solucion"
    assert [d.codigo for d in resp.diagnostico] == ["PRE_SIN_PROFESOR"]
    assert resp.estadisticas.estado_cp_sat == "NO_EJECUTADO"


def test_parcial_sigue_aunque_haya_prechecks():
    resp = solve(_fixture("parcial"))
    assert resp.estado == "factible_parcial"
    assert [d.codigo for d in resp.diagnostico] == ["PRE_HORAS_PROFESOR"]
    pendientes = [s for s in resp.sesiones if s.sin_acomodar]
    assert [(s.curso, s.dia, s.profesor) for s in pendientes] == [("C1", None, None)]
    assert [s.curso for s in resp.sesiones if not s.sin_acomodar] == ["C2"]


def test_fijada_conserva_su_espacio_y_se_marca(minimo):
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2", "nombre": "Aula 2"}))
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=1, orden_inicio=0, espacio="A2")]
    minimo.parametros.num_workers = 1
    resp = solve(minimo)
    assert resp.estado == "ok"
    (fija,) = [s for s in resp.sesiones if s.bloque == 0]
    assert (fija.dia, fija.orden_inicio, fija.espacio, fija.fijada) == (1, 0, "A2", True)
    (otra,) = [s for s in resp.sesiones if s.bloque == 1]
    assert otra.fijada is False


def test_profesor_minimo_sin_cursos_se_explica(minimo):
    minimo.profesores.append(
        minimo.profesores[0].model_copy(
            update={"id": "PR2", "nombre": "B. Ruiz", "materias": [], "horas_min": 2}
        )
    )
    resp = solve(minimo)
    assert resp.estado == "sin_solucion"
    assert [(d.regla, d.mensaje) for d in resp.diagnostico] == [
        ("H7", "B. Ruiz: no se pueden cumplir sus límites de horas (2–20 por semana, 6 por día)")
    ]


def test_referencia_rota_lanza_instance_error(minimo):
    minimo.cursos[0].materia = "NOEXISTE"
    with pytest.raises(InstanceError):
        solve(minimo)


def test_validate_reporta_violaciones(minimo):
    minimo.profesores[0].no_disponible = [RefFranja(plantilla="MAT", dia=0, orden=0)]
    sesiones = [
        SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0, espacio="A1", profesor="PR1"),
        SesionIn(curso="C1", bloque=1, dia=2, orden_inicio=0, espacio="A1", profesor="PR1"),
    ]
    resp = validate(ValidateRequest(schema_version="1", problema=minimo, sesiones=sesiones))
    assert [v.regla for v in resp.violaciones] == ["H6"]
    assert resp.puntaje == sum(p.penalizacion for p in resp.desglose.values())


def test_curso_extra_sin_fijar_se_acomoda(minimo):
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G1"]))
    minimo.parametros.num_workers = 1
    resp = solve(minimo)
    assert resp.estado == "ok" and len(resp.sesiones) == 4


def test_fase_a_conserva_solo_las_obligatorias(minimo):
    reglas = minimo.parametros.reglas
    reglas.S2 = reglas.S2.model_copy(update={"modo": "obligatoria"})
    a = reglas_de_factibilidad(reglas)
    assert {r: getattr(a, r).modo for r in ("S1", "S2", "S3", "S4", "S5", "S6", "S7")} == {
        "S1": "obligatoria",
        "S2": "obligatoria",
        "S3": "apagada",
        "S4": "apagada",
        "S5": "apagada",
        "S6": "apagada",
        "S7": "obligatoria",
    }
    assert reglas.S3.modo == "preferencia"  # no modifica las originales


def test_dos_fases_optimizan_preferencias(minimo):
    minimo.plantillas[0].franjas = [f for f in minimo.plantillas[0].franjas if f.dia == 0]
    minimo.materias[0] = minimo.materias[0].model_copy(
        update={"horas_semana": 1, "patrones": [[1]]}
    )
    minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], profesor="PR1"),
        CursoIn(id="C2", materia="M1", grupos=["G2"], profesor="PR1"),
    ]
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0)]
    minimo.parametros.num_workers = 1
    resp = solve(minimo)
    assert resp.estado == "ok" and resp.desglose["S2"].crudo == 0
    assert {s.curso: s.orden_inicio for s in resp.sesiones} == {"C1": 0, "C2": 1}


def test_parcial_no_ignora_sincronias_mal_formadas(minimo):
    minimo.parametros.permitir_parcial = True
    minimo.materias.append(
        minimo.materias[0].model_copy(update={"id": "M2", "patrones": [[1, 1, 2]]})
    )
    minimo.profesores[0].materias = ["M1", "M2"]
    minimo.profesores.append(minimo.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], sincronia="ING", alumnos=15),
        CursoIn(id="C2", materia="M2", grupos=["G1"], sincronia="ING", alumnos=15),
    ]
    resp = solve(minimo)
    assert resp.estado == "sin_solucion"
    assert "PRE_SINCRONIA" in [d.codigo for d in resp.diagnostico]


def test_se_queda_con_el_mejor_horario(minimo):
    inst = build_instance(minimo)
    bueno = [
        SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0, profesor="PR1"),
        SesionIn(curso="C1", bloque=1, dia=2, orden_inicio=0, profesor="PR1"),
    ]
    malo = [
        SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0, profesor="PR1"),
        SesionIn(curso="C1", bloque=1, dia=1, orden_inicio=0, profesor="PR1"),
    ]  # S5
    assert mejor_de(inst, bueno, malo) == bueno
    assert mejor_de(inst, malo, bueno) == bueno
    parcial = [
        SesionIn(curso="C1", bloque=0, sin_acomodar=True),
        SesionIn(curso="C1", bloque=1, sin_acomodar=True),
    ]
    assert mejor_de(inst, parcial, malo) == malo


def test_fase_a_usa_toda_la_ventana_de_busqueda(minimo, monkeypatch):
    import solver.solve as S

    pedidos = []
    original = S._solver
    monkeypatch.setattr(S, "_solver", lambda t, w, s: pedidos.append(t) or original(t, w, s))
    minimo.parametros.tiempo_limite_s = 40
    solve(minimo)
    assert pedidos[0] >= 0.9 * 40


def test_un_worker_usa_tiempo_determinista():
    import solver.solve as S

    uno = S._solver(7, 1, 3)
    assert uno.parameters.max_deterministic_time == 7
    assert uno.parameters.max_time_in_seconds > 7
    varios = S._solver(7, 8, 3)
    assert varios.parameters.max_time_in_seconds == 7


def test_espacios_sin_reparto_no_es_sin_solucion(minimo, monkeypatch):
    import solver.solve as S
    from solver.model.rooms import EspaciosError

    def falla(*_):
        raise EspaciosError("P1", "aula")

    monkeypatch.setattr(S, "assign_rooms", falla)
    minimo.parametros.num_workers = 1
    resp = solve(minimo)
    assert resp.estado == "tiempo_agotado"
    (d,) = resp.diagnostico
    assert (d.codigo, d.regla) == ("ESPACIOS", "H4")
    assert d.entidades[0].id == "P1" and "aula" in d.mensaje


def test_curso_con_gato_y_fijada_imposible_se_explica(minimo):
    minimo.cursos[0].id = "C#1"
    minimo.profesores[0].no_disponible = [RefFranja(plantilla="MAT", dia=1, orden=0)]
    minimo.fijadas = [SesionIn(curso="C#1", bloque=0, dia=1, orden_inicio=0)]
    minimo.parametros.num_workers = 1
    resp = solve(minimo)
    assert resp.estado == "sin_solucion"
    assert any("La sesión fijada de Matemáticas" in d.mensaje for d in resp.diagnostico)
