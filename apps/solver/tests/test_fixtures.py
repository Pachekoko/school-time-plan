"""Casos compartidos con el validador de TypeScript del editor (contracts/fixtures)."""

import json

import pytest

from solver.contract import SesionIn, SolveRequest
from solver.instance import build_instance
from solver.solve import solve
from solver.validator import validate_schedule

from helpers import FIXTURES


@pytest.mark.parametrize("path", sorted(FIXTURES.glob("validate/*.json")), ids=lambda p: p.stem)
def test_fixture_validate(path):
    caso = json.loads(path.read_text(encoding="utf-8"))
    inst = build_instance(SolveRequest.model_validate(caso["problema"]))
    viol = validate_schedule(inst, [SesionIn.model_validate(s) for s in caso["sesiones"]])
    assert sorted({v.regla for v in viol}) == sorted(caso["esperado"]["reglas_violadas"]), [
        v.mensaje for v in viol
    ]


def _caso(path):
    return json.loads(path.read_text(encoding="utf-8"))


@pytest.mark.parametrize("path", sorted(FIXTURES.glob("solve/*.json")), ids=lambda p: p.stem)
def test_fixture_solve(path):
    caso = _caso(path)
    resp = solve(SolveRequest.model_validate(caso["problema"]))
    esperado = caso["esperado"]
    assert resp.estado == esperado["estado"], [d.mensaje for d in resp.diagnostico]
    if "codigos_diagnostico" in esperado:
        assert sorted({d.regla for d in resp.diagnostico}) == esperado["codigos_diagnostico"]
