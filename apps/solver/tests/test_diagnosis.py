from ortools.sat.python import cp_model

from solver.contract import CursoIn, Entidad, SesionIn
from solver.diagnosis import explain_core
from solver.instance import build_instance
from solver.model.hard import Supuestos


def _e(tipo, id):
    return Entidad(tipo=tipo, id=id)


def test_mensajes_por_regla(minimo):
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G1"], sincronia="ING"))
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=1, orden_inicio=2)]
    inst = build_instance(minimo)
    sup = Supuestos(cp_model.CpModel())
    lits = [
        sup.lit("H2", [_e("grupo", "G1")]),
        sup.lit("H3", [_e("profesor", "PR1")]),
        sup.lit("H4", [_e("plantel", "P1")], tipo="laboratorio"),
        sup.lit("H6", [_e("profesor", "PR1")]),
        sup.lit("H7", [_e("profesor", "PR1")]),
        sup.lit("H8", [_e("sincronia", "ING")]),
        sup.lit("H9", [_e("curso", "C1")]),
        sup.lit("H10", [_e("sesion", "C1#0.0")]),
        sup.lit("H11", [_e("profesor", "PR1")]),
        sup.lit("S1", [_e("grupo", "G1")]),
        sup.lit("S7", [_e("grupo", "G1"), _e("profesor", "PR1")]),
    ]
    diag = explain_core(inst, sup, [lit.Index() for lit in lits])
    assert {d.codigo for d in diag} == {"NUCLEO"}
    assert {d.regla: d.mensaje for d in diag} == {
        "H2": "El grupo 1A no cabe en su semana con las demás reglas",
        "H3": "A. López: sus cursos no caben sin empalmarse",
        "H4": "Plantel Centro: no alcanzan los espacios de tipo laboratorio",
        "H6": "A. López: su disponibilidad no alcanza para sus cursos con las demás reglas",
        "H7": "A. López: no se pueden cumplir sus límites de horas (0–20 por semana, 6 por día)",
        "H8": "La sincronía ING no encuentra una hora común",
        "H9": "Matemáticas (1A) no tiene días distintos suficientes",
        "H10": "La sesión fijada de Matemáticas (1A, Mar 08:40) choca con el resto",
        "H11": "A. López: no hay tiempo de traslado entre sus planteles",
        "S1": "La regla obligatoria «Sin huecos para los grupos» no se puede cumplir para 1A",
        "S7": "La regla obligatoria «Un docente, una materia por día en cada grupo» no se puede "
        "cumplir para A. López en 1A",
    }


def test_nucleo_vacio_no_inventa_nada(minimo):
    inst = build_instance(minimo)
    assert explain_core(inst, Supuestos(cp_model.CpModel()), []) == []
