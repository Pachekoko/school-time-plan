from hypothesis import HealthCheck, given, settings

from solver.instance import build_instance
from solver.scoring import score
from solver.solve import solve
from solver.validator import validate_schedule

from strategies import problemas

AJUSTES = settings(max_examples=100, deadline=None, suppress_health_check=[HealthCheck.too_slow])


@AJUSTES
@given(problemas())
def test_toda_solucion_es_valida(req):
    resp = solve(req)
    if resp.estado == "ok":
        assert validate_schedule(build_instance(req), resp.sesiones) == []


@AJUSTES
@given(problemas())
def test_sin_solucion_siempre_explica(req):
    resp = solve(req)
    if resp.estado == "sin_solucion":
        assert len(resp.diagnostico) >= 1


@AJUSTES
@given(problemas())
def test_puntaje_es_el_de_scoring(req):
    resp = solve(req)
    if resp.estado in ("ok", "factible_parcial"):
        assert resp.puntaje == score(build_instance(req), resp.sesiones)[0]


@AJUSTES
@given(problemas())
def test_parcial_solo_falla_por_bloques_sin_acomodar(req):
    resp = solve(req)
    if resp.estado == "factible_parcial":
        reglas = {v.regla for v in validate_schedule(build_instance(req), resp.sesiones)}
        assert reglas <= {"H1", "H7"}, reglas
