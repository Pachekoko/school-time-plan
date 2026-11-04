"""Caso real de carga horaria (UTEG, ciclo 27-A), anonimizado.

Las pruebas leen el fixture JSON versionado; ni el .xlsx original ni docentes.local.json están
en el repositorio.
"""

import json
import re
from collections import Counter
from pathlib import Path

import pytest

from solver.contract import SolveRequest
from solver.instance import build_instance
from solver.prechecks import run_prechecks
from solver.solve import solve
from solver.validator import validate_schedule

from from_carga_xlsx import normaliza_materia
from helpers import FIXTURES

FIXTURE = FIXTURES / "real" / "uteg-27a-anon.json"
DOCENTES_LOCAL = Path(__file__).resolve().parents[1] / "bench" / "docentes.local.json"


def caso_real() -> SolveRequest:
    return SolveRequest.model_validate(json.loads(FIXTURE.read_text(encoding="utf-8")))


def _horas_por_grupo(req):
    horas = {m.id: m.horas_semana for m in req.materias}
    total = Counter()
    for c in req.cursos:
        total[c.grupos[0]] += horas[c.materia]
    return total


def test_caso_real_tiene_13_grupos_y_124_cursos():
    req = caso_real()
    assert len(req.grupos) == 13 and len(req.cursos) == 124
    vacantes = [c for c in req.cursos if c.vacante]  # las filas GENERICO
    assert len(vacantes) == 11 and len({c.grupos[0] for c in vacantes}) == 8


def test_grupos_1y2_tienen_30h_sin_holgura():
    req = caso_real()
    horas = _horas_por_grupo(req)
    franjas = {pl.id: sum(f.tipo == "clase" for f in pl.franjas) for pl in req.plantillas}
    de_1y2 = [g for g in req.grupos if g.plantilla == "1y2"]
    assert len(de_1y2) == 6
    assert all(horas[g.id] == franjas["1y2"] == 30 for g in de_1y2)


def test_docente_f_tiene_30h_y_solo_hasta_las_14():
    req = caso_real()
    (f,) = [p for p in req.profesores if p.nombre == "Docente F"]
    assert f.horas_min == f.horas_max == 30
    assert {(r.plantilla, r.orden) for r in f.no_disponible} == {("3a6", 6)}


def test_docente_n_solo_de_miercoles_a_viernes_y_sin_huecos():
    (n,) = [p for p in caso_real().profesores if p.nombre == "Docente N"]
    assert n.sin_huecos and {r.dia for r in n.no_disponible} == {0, 1}


def test_fixture_sin_nombres_reales():
    texto = FIXTURE.read_text(encoding="utf-8")
    assert all(re.fullmatch(r"Docente ([0-9]{2}|[A-Z])", p.nombre) for p in caso_real().profesores)
    req = caso_real()
    assert all(re.fullmatch(r"G[0-9]{2}", g.id) for g in req.grupos)  # sin códigos reales
    assert "GENERICO" not in texto
    if DOCENTES_LOCAL.exists():
        for real in json.loads(DOCENTES_LOCAL.read_text(encoding="utf-8")):
            assert real.lower() not in texto.lower()


def test_caso_real_pasa_prechecks():
    assert run_prechecks(build_instance(caso_real())) == []


def test_normaliza_numeral_con_pipes():
    assert normaliza_materia("Tutoria de trayectoria||") == "Tutoria de trayectoria II"
    assert normaliza_materia("Comprension Lectora ||") == "Comprension Lectora II"
    assert normaliza_materia("  Analisis  literario| ") == "Analisis literario I"
    assert normaliza_materia("Historia e Identidad Universitaria ") == (
        "Historia e Identidad Universitaria"
    )


@pytest.mark.slow
def test_caso_real_resultado():
    req = caso_real()
    resp = solve(req)
    assert resp.estado in ("ok", "sin_solucion"), resp.estado
    if resp.estado == "sin_solucion":
        assert resp.diagnostico  # el motor explica qué choca (este caso no tiene holgura)
    else:
        assert validate_schedule(build_instance(req), resp.sesiones) == []
