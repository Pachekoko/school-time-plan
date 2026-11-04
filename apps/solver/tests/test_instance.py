import pytest

from solver.contract import CursoIn, ProfesorIn, RefFranja, SesionIn, SolveRequest
from solver.instance import Inicio, InstanceError, build_instance
from solver.messages import DIAS, hora, nombre

from helpers import minimo_dict


def _errores(req: SolveRequest) -> list[str]:
    with pytest.raises(InstanceError) as e:
        build_instance(req)
    return e.value.errores


def test_bloques_desde_patron(minimo):
    inst = build_instance(minimo)
    assert [(b.id, b.duracion) for b in inst.bloques] == [("C1#0.0", 2), ("C1#0.1", 2)]


def test_patrones_alternativos(minimo):
    minimo.materias[0].horas_semana = 2
    minimo.materias[0].patrones = [[2], [1, 1]]
    assert [b.id for b in build_instance(minimo).bloques] == ["C1#0.0", "C1#1.0", "C1#1.1"]


def test_inicios_no_cruzan_receso(plantilla_con_receso):
    inst = build_instance(plantilla_con_receso)
    inicios = {(i.dia, i.orden) for i in inst.inicios_validos(inst.bloques[0])}
    assert (0, 2) not in inicios and (0, 1) in inicios and (0, 4) in inicios
    assert (0, 6) not in inicios  # no hay franja 7


def test_franjas_cubiertas(plantilla_con_receso):
    inst = build_instance(plantilla_con_receso)
    cubiertas = inst.franjas_cubiertas(inst.bloques[0], Inicio(dia=1, orden=4))
    assert [(f.dia, f.orden, f.inicio_min) for f in cubiertas] == [(1, 4, 600), (1, 5, 650)]


def test_atomos_con_plantillas_traslapadas(mat_y_vesp):
    inst = build_instance(mat_y_vesp)
    a = inst.atomos_cubiertos("MAT", 0, 6)
    b = inst.atomos_cubiertos("VESP", 0, 0)
    assert set(a) & set(b)
    assert not set(inst.atomos_cubiertos("MAT", 0, 5)) & set(b)


def test_atomos_cortan_en_todos_los_bordes(mat_y_vesp):
    p = mat_y_vesp
    p.plantillas[1].franjas[0].inicio = "13:20"  # VESP 0 = 13:20–13:50, MAT 6 = 13:00–13:50
    inst = build_instance(p)
    assert inst.atomos[0][6:8] == [(780, 800), (800, 830)]
    assert inst.atomos_cubiertos("MAT", 0, 6) == [6, 7]
    assert inst.atomos_cubiertos("VESP", 0, 0) == [7]


def test_patron_no_suma_horas(minimo):
    minimo.materias[0].patrones = [[2, 2], [2, 1]]
    assert "el patrón [2, 1] suma 3 y horas_semana es 4" in _errores(minimo)[0]


def test_referencia_inexistente(minimo):
    minimo.cursos[0].materia = "NOEXISTE"
    assert any("materia NOEXISTE" in m for m in _errores(minimo))


def test_curso_con_grupos_de_planteles_distintos():
    p = minimo_dict()
    p["planteles"].append({"id": "P2", "nombre": "Plantel Norte"})
    p["grupos"].append(
        {"id": "G2", "nombre": "1B", "plantel": "P2", "plantilla": "MAT", "alumnos": 20}
    )
    p["cursos"][0]["grupos"] = ["G1", "G2"]
    assert any("planteles distintos" in m for m in _errores(SolveRequest.model_validate(p)))


def test_id_duplicado(minimo):
    minimo.grupos.append(minimo.grupos[0].model_copy())
    assert any("id duplicado en grupos: G1" in m for m in _errores(minimo))


def test_ref_franja_inexistente(minimo):
    minimo.profesores[0].no_disponible = [RefFranja(plantilla="MAT", dia=0, orden=9)]
    assert any("franja MAT Lun 9 no existe" in m for m in _errores(minimo))


def test_franjas_traslapadas_en_una_plantilla(minimo):
    minimo.plantillas[0].franjas[1].inicio = "07:30"  # la 0 termina 07:50
    assert any("se traslapan" in m for m in _errores(minimo))


def test_fijada_con_bloque_inexistente(minimo):
    minimo.fijadas = [SesionIn(curso="C1", patron=0, bloque=5, dia=0, orden_inicio=0)]
    assert any("bloque 5 del patrón 0" in m for m in _errores(minimo))


def test_junta_todos_los_errores(minimo):
    minimo.cursos[0].materia = "NOEXISTE"
    minimo.grupos[0].plantilla = "OTRA"
    assert len(_errores(minimo)) >= 2


def test_candidatos(minimo):
    minimo.profesores.append(
        ProfesorIn(id="PR2", nombre="B", planteles=["P1"], materias=[], horas_max=9, max_dia=4)
    )
    minimo.profesores.append(
        ProfesorIn(id="PR3", nombre="C", planteles=["P1"], materias=["M1"], horas_max=9, max_dia=4)
    )
    assert build_instance(minimo).candidatos("C1") == ["PR1", "PR3"]
    minimo.cursos[0].profesor = "PR2"
    assert build_instance(minimo).candidatos("C1") == ["PR2"]
    minimo.cursos[0].profesor = None
    minimo.cursos[0].vacante = True
    assert build_instance(minimo).candidatos("C1") == []


def test_disponible(minimo):
    minimo.profesores[0].no_disponible = [RefFranja(plantilla="MAT", dia=0, orden=0)]
    minimo.profesores[0].preferente = [RefFranja(plantilla="MAT", dia=0, orden=1)]
    inst = build_instance(minimo)
    assert [inst.disponible("PR1", "MAT", 0, o) for o in range(3)] == ["N", "P", "D"]


def test_curso_alumnos_y_ubicacion(minimo):
    inst = build_instance(minimo)
    assert inst.curso_alumnos("C1") == 30
    assert (inst.curso_plantilla("C1"), inst.curso_plantel("C1")) == ("MAT", "P1")
    minimo.cursos[0].alumnos = 12
    assert build_instance(minimo).curso_alumnos("C1") == 12


def test_mensajes(minimo):
    inst = build_instance(minimo)
    assert DIAS[2] == "Mié"
    assert hora(inst, "MAT", 2, 1) == "Mié 07:50"
    assert nombre(inst, "profesor", "PR1") == "A. López"
    assert nombre(inst, "grupo", "G1") == "1A"
    assert nombre(inst, "curso", "C1") == "Matemáticas (1A)"
    assert nombre(inst, "grupo", "NOEXISTE") == "NOEXISTE"


def test_grupos_ocupados_por_sincronia(minimo):
    minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], sincronia="ING"),
        CursoIn(id="C2", materia="M1", grupos=["G2"], sincronia="ING"),
        CursoIn(id="C3", materia="M1", grupos=["G2"]),
    ]
    inst = build_instance(minimo)
    assert inst.sincronias == {"ING": ["C1", "C2"]}
    assert inst.grupos_ocupados("C1") == ["G1", "G2"]
    assert inst.grupos_ocupados("C3") == ["G2"]


def test_cursos_de_profesor(minimo):
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G1"], vacante=True))
    assert build_instance(minimo).cursos_de_profesor("PR1") == ["C1"]


def test_atomos_de_plantilla(plantilla_con_receso):
    inst = build_instance(plantilla_con_receso)
    assert inst.atomos_de_plantilla("MAT", 0) == [0, 1, 2, 3, 4, 5]


def test_franjas_clase_y_dias(plantilla_con_receso):
    inst = build_instance(plantilla_con_receso)
    assert [f.orden for f in inst.franjas_clase("MAT", 2)] == [0, 1, 2, 4, 5, 6]
    assert inst.dias("MAT") == [0, 1, 2, 3, 4]
