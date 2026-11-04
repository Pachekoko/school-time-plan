from solver.contract import (
    CursoIn,
    Entidad,
    EspacioIn,
    GrupoIn,
    MateriaIn,
    RefFranja,
    SesionIn,
    SolveRequest,
)
from solver.instance import build_instance
from solver.prechecks import run_prechecks

from helpers import minimo_dict


def _diag(req):
    return run_prechecks(build_instance(req))


def _solo_dias(req, dias):
    req.plantillas[0].franjas = [f for f in req.plantillas[0].franjas if f.dia in dias]
    return req


def _materia(id, nombre, horas, patrones, tipo="aula"):
    return MateriaIn(id=id, nombre=nombre, horas_semana=horas, patrones=patrones, tipo_espacio=tipo)


def _otro_profesor(req):
    """Un segundo profesor igual al primero: ningún curso tiene candidato único."""
    req.profesores.append(req.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))


def test_sin_cursos_no_hay_diagnostico(minimo):
    minimo.cursos = []
    assert _diag(minimo) == []


def test_problema_sano_no_tiene_diagnostico(minimo):
    assert _diag(minimo) == []


def test_horas_profesor_contra_disponibilidad(minimo):
    minimo.cursos[0].profesor = "PR1"
    minimo.profesores[0].no_disponible = [
        RefFranja(plantilla="MAT", dia=d, orden=o)
        for d in range(5)
        for o in range(4)
        if not (d == 0 and o < 3)
    ]
    (d,) = _diag(minimo)
    assert d.codigo == "PRE_HORAS_PROFESOR" and d.regla == "H6"
    assert d.entidades == [Entidad(tipo="profesor", id="PR1")]
    assert d.mensaje == "A. López necesita 4 h y solo tiene 3 franjas disponibles"


def test_disponibilidad_no_suma_dos_veces_plantillas_iguales(minimo):
    otra = minimo.plantillas[0].model_copy(update={"id": "MAT2", "nombre": "Matutino 2"})
    minimo.plantillas.append(otra)
    minimo.grupos.append(GrupoIn(id="G2", nombre="1B", plantel="P1", plantilla="MAT2", alumnos=30))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id=f"C{i}", materia="M1", grupos=[g], profesor="PR1")
        for i, g in enumerate(["G1", "G1", "G1", "G2", "G2", "G2"])
    ]
    minimo.profesores[0].horas_max = 30
    (d,) = [x for x in _diag(minimo) if x.codigo == "PRE_HORAS_PROFESOR"]
    assert d.mensaje == "A. López necesita 24 h y solo tiene 20 franjas disponibles"


def test_horas_asignadas_contra_maximo(minimo):
    minimo.cursos[0].profesor = "PR1"
    minimo.profesores[0].horas_max = 3
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_HORAS_MAX", "H7")
    assert d.mensaje == "A. López tiene 4 h asignadas y su máximo es 3 h"


def test_carga_del_grupo_contra_su_plantilla(minimo):
    minimo.cursos = [CursoIn(id=f"C{i}", materia="M1", grupos=["G1"]) for i in range(6)]
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.profesores[0].horas_max = 30
    _otro_profesor(minimo)
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_HORAS_GRUPO", "H2")
    assert d.entidades == [Entidad(tipo="grupo", id="G1")]
    assert (
        d.mensaje == "El grupo 1A tiene 24 h de carga y su plantilla solo tiene 20 franjas de clase"
    )


def test_sincronia_cuenta_una_sola_vez_para_el_grupo(minimo):
    minimo.cursos = [CursoIn(id=f"C{i}", materia="M1", grupos=["G1"]) for i in range(4)]
    minimo.cursos += [
        CursoIn(id=f"I{i}", materia="M1", grupos=["G1"], sincronia="ING") for i in range(2)
    ]
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.profesores[0].horas_max = 30
    _otro_profesor(minimo)
    assert [x.codigo for x in _diag(minimo)] == []


def test_demanda_de_laboratorio(minimo):
    minimo.materias = [_materia("M2", "Química", 4, [[2, 2]], "laboratorio")]
    minimo.profesores[0].materias = ["M2"]
    minimo.profesores[0].horas_max = 40
    minimo.espacios = [
        EspacioIn(id="L1", plantel="P1", nombre="Lab 1", tipo="laboratorio", capacidad=40)
    ]
    minimo.grupos = [
        GrupoIn(id=f"G{i}", nombre=f"{i}A", plantel="P1", plantilla="MAT", alumnos=30)
        for i in range(6)
    ]
    minimo.cursos = [CursoIn(id=f"C{i}", materia="M2", grupos=[f"G{i}"]) for i in range(6)]
    _otro_profesor(minimo)
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_ESPACIO", "H4")
    assert d.entidades == [Entidad(tipo="plantel", id="P1")]
    assert d.mensaje == "Plantel Centro: se necesitan 24 franjas de laboratorio y solo hay 20"


def test_ningun_espacio_con_capacidad(minimo):
    minimo.espacios[0].capacidad = 20
    (d,) = _diag(minimo)
    assert d.codigo == "PRE_ESPACIO"
    assert d.entidades == [Entidad(tipo="plantel", id="P1"), Entidad(tipo="curso", id="C1")]
    assert d.mensaje == (
        "Plantel Centro: ningún espacio de tipo aula tiene capacidad para 30 alumnos "
        "(Matemáticas (1A))"
    )


def test_curso_sin_profesor_habilitado(minimo):
    minimo.profesores[0].materias = []
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_SIN_PROFESOR", "H5")
    assert d.entidades == [Entidad(tipo="curso", id="C1")]
    assert (
        d.mensaje
        == "El curso Matemáticas de 1A no tiene ningún profesor habilitado en Plantel Centro"
    )


def test_vacante_no_necesita_profesor(minimo):
    minimo.profesores[0].materias = []
    minimo.cursos[0].vacante = True
    assert _diag(minimo) == []


def test_sincronia_con_bloques_distintos(minimo):
    minimo.materias.append(_materia("M2", "Inglés", 4, [[1, 1, 2]]))
    minimo.profesores[0].materias = ["M1", "M2"]
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], sincronia="ING"),
        CursoIn(id="C2", materia="M2", grupos=["G1"], sincronia="ING"),
    ]
    _otro_profesor(minimo)
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_SINCRONIA", "H8")
    assert d.entidades == [Entidad(tipo="sincronia", id="ING")]
    assert d.mensaje == "La sincronía ING mezcla cursos con plantillas o bloques distintos"


def test_bloque_largo_con_receso(plantilla_con_receso):
    plantilla_con_receso.materias[0].patrones = [[4]]
    (d,) = _diag(plantilla_con_receso)
    assert (d.codigo, d.regla) == ("PRE_BLOQUE_LARGO", "H1")
    assert d.entidades == [Entidad(tipo="curso", id="C1")]
    assert d.mensaje == (
        "Matemáticas tiene un bloque de 4 h y la plantilla Matutino no tiene 4 franjas de clase "
        "seguidas"
    )


def test_bloque_largo_con_otro_patron_posible(plantilla_con_receso):
    plantilla_con_receso.materias[0].patrones = [[4], [2, 2]]
    assert _diag(plantilla_con_receso) == []


def test_mas_bloques_que_dias(minimo):
    _solo_dias(minimo, {0, 1})
    minimo.materias[0].patrones = [[1, 1, 1, 1]]
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_DIAS", "H9")
    assert d.entidades == [Entidad(tipo="curso", id="C1")]
    assert d.mensaje == "Matemáticas (1A) tiene 4 bloques y solo hay 2 días con espacio"


def test_mismo_docente_necesita_mas_dias_de_los_que_hay(minimo):
    _solo_dias(minimo, {0, 1})
    minimo.materias = [_materia(f"M{i}", f"Materia {i}", 1, [[1]]) for i in range(3)]
    minimo.profesores[0].materias = [m.id for m in minimo.materias]
    minimo.cursos = [
        CursoIn(id=f"C{i}", materia=f"M{i}", grupos=["G1"], profesor="PR1") for i in range(3)
    ]
    (d,) = _diag(minimo)
    assert (d.codigo, d.regla) == ("PRE_MISMO_DOCENTE", "S7")
    assert d.entidades == [Entidad(tipo="profesor", id="PR1"), Entidad(tipo="grupo", id="G1")]
    assert d.mensaje == (
        "A. López da 3 materias a 1A y necesita 3 días distintos; la semana solo tiene 2"
    )


def test_mismo_docente_no_aplica_si_s7_no_es_obligatoria(minimo):
    _solo_dias(minimo, {0, 1})
    minimo.materias = [_materia(f"M{i}", f"Materia {i}", 1, [[1]]) for i in range(3)]
    minimo.profesores[0].materias = [m.id for m in minimo.materias]
    minimo.cursos = [
        CursoIn(id=f"C{i}", materia=f"M{i}", grupos=["G1"], profesor="PR1") for i in range(3)
    ]
    minimo.parametros.reglas.S7.modo = "preferencia"
    assert _diag(minimo) == []


def test_fijada_en_receso(plantilla_con_receso):
    plantilla_con_receso.fijadas = [
        SesionIn(curso="C1", patron=0, bloque=0, dia=0, orden_inicio=3, fijada=True)
    ]
    (d,) = _diag(plantilla_con_receso)
    assert (d.codigo, d.regla) == ("PRE_FIJADA", "H10")
    assert d.entidades[0] == Entidad(tipo="sesion", id="C1#0.0")
    assert d.mensaje == (
        "La sesión fijada de Matemáticas (1A, Lun 09:30) no es válida: "
        "Matemáticas (1A) no puede empezar el Lun 09:30"
    )


def test_fijada_con_profesor_no_disponible(minimo):
    minimo.profesores[0].no_disponible = [RefFranja(plantilla="MAT", dia=0, orden=0)]
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0, profesor="PR1")]
    (d,) = _diag(minimo)
    assert d.codigo == "PRE_FIJADA"
    assert d.mensaje == (
        "La sesión fijada de Matemáticas (1A, Lun 07:00) no es válida: "
        "A. López no está disponible el Lun 07:00"
    )


def test_fijada_sin_profesor_es_valida(minimo):
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0)]
    assert _diag(minimo) == []


def test_fijadas_que_chocan_entre_si(minimo):
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G1"]))
    minimo.fijadas = [
        SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0),
        SesionIn(curso="C2", bloque=0, dia=0, orden_inicio=1),
    ]
    (d,) = _diag(minimo)
    assert d.codigo == "PRE_FIJADA"
    assert d.entidades == [Entidad(tipo="grupo", id="G1")]
    assert d.mensaje == (
        "Las sesiones fijadas no son compatibles: "
        "El grupo 1A tiene dos clases a la vez el Lun 07:50"
    )


def test_orden_estable_por_codigo():
    p = minimo_dict()
    p["profesores"][0]["materias"] = []
    p["espacios"][0]["capacidad"] = 10
    codigos = [d.codigo for d in _diag(SolveRequest.model_validate(p))]
    assert codigos == sorted(codigos) == ["PRE_ESPACIO", "PRE_SIN_PROFESOR"]


def test_unico_candidato_cuenta_como_fijo(minimo):
    _solo_dias(minimo, {0, 1})
    minimo.materias = [_materia(f"M{i}", f"Materia {i}", 1, [[1]]) for i in range(3)]
    minimo.profesores[0].materias = [m.id for m in minimo.materias]
    minimo.cursos = [CursoIn(id=f"C{i}", materia=f"M{i}", grupos=["G1"]) for i in range(3)]
    (d,) = _diag(minimo)
    assert d.codigo == "PRE_MISMO_DOCENTE"


def test_horas_de_unico_candidato_contra_maximo(minimo):
    minimo.profesores[0].horas_max = 3
    (d,) = _diag(minimo)
    assert d.codigo == "PRE_HORAS_MAX"
    assert d.mensaje == "A. López tiene 4 h asignadas y su máximo es 3 h"


def test_sincronia_sin_profesores_distintos_suficientes(minimo):
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id=f"I{i}", materia="M1", grupos=["G1"], sincronia="ING", alumnos=15)
        for i in range(2)
    ]
    (d,) = [x for x in _diag(minimo) if x.codigo == "PRE_SINCRONIA_PROFESORES"]
    assert d.regla == "H3"
    assert d.entidades == [Entidad(tipo="sincronia", id="ING")]
    assert d.mensaje == (
        "La sincronía ING necesita 2 profesores distintos a la misma hora y sus candidatos "
        "solo alcanzan para 1"
    )
    _otro_profesor(minimo)
    assert not [x for x in _diag(minimo) if x.codigo == "PRE_SINCRONIA_PROFESORES"]


def test_sincronia_con_vacantes_no_necesita_profesor(minimo):
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id="I0", materia="M1", grupos=["G1"], sincronia="ING", alumnos=15),
        CursoIn(id="I1", materia="M1", grupos=["G1"], sincronia="ING", alumnos=15, vacante=True),
    ]
    assert not [x for x in _diag(minimo) if x.codigo == "PRE_SINCRONIA_PROFESORES"]
