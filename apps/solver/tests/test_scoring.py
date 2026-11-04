from solver.contract import CursoIn, MateriaIn, RefFranja, ReglaConfig, RestriccionFranja, SesionIn
from solver.instance import build_instance
from solver.scoring import score


def _s(curso="C1", dia=0, orden=0, bloque=0, profesor="PR1"):
    return SesionIn(
        curso=curso, bloque=bloque, dia=dia, orden_inicio=orden, espacio="A1", profesor=profesor
    )


def _de_una_hora(minimo, n_cursos=1, mismo_grupo=True):
    """`n_cursos` materias de 1 h ([[1]]) del mismo profesor."""
    minimo.materias = [
        MateriaIn(id=f"M{i}", nombre=f"Materia {i}", horas_semana=1, patrones=[[1]])
        for i in range(1, n_cursos + 1)
    ]
    minimo.profesores[0].materias = [m.id for m in minimo.materias]
    if not mismo_grupo:
        for i in range(2, n_cursos + 1):
            minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": f"G{i}"}))
    minimo.cursos = [
        CursoIn(
            id=f"C{i}", materia=f"M{i}", grupos=["G1" if mismo_grupo else f"G{i}"], profesor="PR1"
        )
        for i in range(1, n_cursos + 1)
    ]
    return minimo


def test_score_huecos_grupo(minimo):
    inst = build_instance(_de_una_hora(minimo, 2))
    total, d = score(inst, [_s("C1", 0, 0), _s("C2", 0, 2)])
    assert d["S1"].crudo == 1 and d["S1"].penalizacion == 1 * 5 * 50


def test_s2_minutos_hueco_del_profesor(minimo):
    inst = build_instance(_de_una_hora(minimo, 2, mismo_grupo=False))
    _, d = score(inst, [_s("C1", 0, 0), _s("C2", 0, 2)])
    assert d["S2"].crudo == 50 and d["S2"].penalizacion == 50 * 7 * 1


def test_s3_dias_que_viene_el_profesor(minimo):
    inst = build_instance(minimo)
    _, d = score(inst, [_s(dia=0, bloque=0), _s(dia=2, bloque=1)])
    assert d["S3"].crudo == 2 and d["S3"].penalizacion == 2 * 5 * 100


def test_s4_franjas_no_preferentes(minimo):
    minimo.profesores[0].preferente = [RefFranja(plantilla="MAT", dia=0, orden=0)]
    inst = build_instance(minimo)
    _, d = score(inst, [_s(dia=0, orden=0, bloque=0), _s(dia=2, bloque=1)])
    assert d["S4"].crudo == 3 and d["S4"].penalizacion == 3 * 4 * 10


def test_s5_dias_consecutivos(minimo):
    inst = build_instance(minimo)
    _, d = score(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1)])
    assert d["S5"].crudo == 1 and d["S5"].penalizacion == 1 * 3 * 50
    _, d = score(inst, [_s(dia=0, bloque=0), _s(dia=2, bloque=1)])
    assert d["S5"].crudo == 0


def test_s6_reglas_de_franja(minimo):
    minimo.parametros.reglas.S6_restricciones = [
        RestriccionFranja(materias=["M1"], franjas=[RefFranja(plantilla="MAT", dia=0, orden=1)])
    ]
    inst = build_instance(minimo)
    _, d = score(inst, [_s(dia=0, orden=0, bloque=0), _s(dia=2, bloque=1)])
    assert d["S6"].crudo == 1 and d["S6"].penalizacion == 1 * 5 * 50


def test_s7_mismo_docente_dos_materias_mismo_dia(minimo):
    inst = build_instance(_de_una_hora(minimo, 3))
    _, d = score(inst, [_s("C1", 0, 0), _s("C2", 0, 1), _s("C3", 0, 2)])
    assert d["S7"].crudo == 2 and d["S7"].penalizacion == 2 * 5 * 100


def test_apagada_no_aparece_y_total_es_la_suma(minimo):
    minimo.parametros.reglas.S5 = ReglaConfig(modo="apagada")
    inst = build_instance(minimo)
    total, d = score(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1)])
    assert "S5" not in d
    assert list(d) == ["S1", "S2", "S3", "S4", "S6", "S7"]
    assert total == sum(p.penalizacion for p in d.values())


def test_vacante_no_cuenta_para_reglas_de_profesor(minimo):
    minimo.cursos[0].vacante = True
    inst = build_instance(minimo)
    _, d = score(inst, [_s(dia=0, bloque=0, profesor=None), _s(dia=2, bloque=1, profesor=None)])
    assert d["S3"].crudo == 0
