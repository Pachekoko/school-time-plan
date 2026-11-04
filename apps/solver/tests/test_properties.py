from itertools import product

from hypothesis import HealthCheck, given, settings
from ortools.sat.python import cp_model

from solver.contract import SesionIn
from solver.instance import build_instance
from solver.scoring import score
from solver.solve import _modelo, extract_sesiones, solve
from solver.validator import validate_schedule

from strategies import problemas, problemas_diminutos

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
def test_crudos_del_modelo_son_los_de_scoring(req):
    """El valor de cada regla en el modelo es el que calcula scoring sobre la solución."""
    inst = build_instance(req)
    m, v, _, crudos = _modelo(inst, con_supuestos=False)
    solver = cp_model.CpSolver()
    solver.parameters.num_workers = 1
    solver.parameters.max_time_in_seconds = 5
    if solver.Solve(m) not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return
    _, desglose = score(inst, extract_sesiones(inst, v, solver))
    assert {r: solver.Value(e) for r, e in crudos.items()} == {
        r: p.crudo for r, p in desglose.items()
    }


@AJUSTES
@given(problemas())
def test_parcial_solo_falla_por_bloques_sin_acomodar(req):
    resp = solve(req)
    if resp.estado == "factible_parcial":
        reglas = {v.regla for v in validate_schedule(build_instance(req), resp.sesiones)}
        assert reglas <= {"H1", "H7"}, reglas


def _fuerza_bruta(req) -> bool:
    """¿Existe algún horario completo que el validador independiente acepte?"""
    inst = build_instance(req)
    opciones = []
    for c in req.cursos:
        (bloque,) = inst.bloques_por_curso[c.id][0]
        tipo = inst.materias[c.materia].tipo_espacio
        espacios = [e.id for e in req.espacios if e.tipo == tipo]
        opciones.append(
            [
                SesionIn(
                    curso=c.id,
                    bloque=0,
                    dia=i.dia,
                    orden_inicio=i.orden,
                    espacio=e,
                    profesor=c.profesor,
                )
                for i in inst.inicios_validos(bloque)
                for e in espacios
            ]
        )
    return any(validate_schedule(inst, list(combo)) == [] for combo in product(*opciones))


@settings(max_examples=1500, deadline=None, suppress_health_check=[HealthCheck.too_slow])
@given(problemas_diminutos())
def test_sin_solucion_es_correcto_en_problemas_diminutos(req):
    resp = solve(req)
    existe = _fuerza_bruta(req)
    if resp.estado == "sin_solucion":
        assert not existe, [d.mensaje for d in resp.diagnostico]
    if existe:
        assert resp.estado == "ok", (resp.estado, [d.mensaje for d in resp.diagnostico])
