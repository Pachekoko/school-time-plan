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
from solver.solve import map_status, solve, validate
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
