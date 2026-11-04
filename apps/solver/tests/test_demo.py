import json

import pytest

from solver.instance import build_instance
from solver.prechecks import run_prechecks
from solver.solve import solve
from solver.validator import validate_schedule

from generate_demo import build
from helpers import FIXTURES


def test_demo_es_valida_y_determinista():
    a, b = build(42), build(42)
    assert a == b
    inst = build_instance(a)
    assert len(inst.planteles) == 3
    assert 36 <= len(inst.grupos) <= 40
    assert 55 <= len(inst.profesores) <= 90
    assert inst.sincronias, "Inglés por nivel debe generar sincronías"


def test_demo_pasa_prechecks():
    assert run_prechecks(build_instance(build(42))) == []


def test_json_versionado_es_el_generado():
    guardado = json.loads((FIXTURES / "demo" / "prepa-horizonte.json").read_text(encoding="utf-8"))
    assert guardado == build(42).model_dump(mode="json")


def test_ninguna_marca_real():
    texto = build(42).model_dump_json().lower()
    assert "uteg" not in texto


@pytest.mark.slow
def test_demo_se_resuelve_en_60s():
    req = build(42)
    resp = solve(req)
    assert resp.estado == "ok", [d.mensaje for d in resp.diagnostico]
    assert resp.estadisticas.tiempo_s <= 65
    assert validate_schedule(build_instance(req), resp.sesiones) == []
