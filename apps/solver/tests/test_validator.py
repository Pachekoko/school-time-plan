from solver.contract import CursoIn, MateriaIn, ProfesorIn, RefFranja, SesionIn
from solver.instance import build_instance
from solver.validator import validate_schedule


def _s(curso="C1", dia=0, orden=0, bloque=0, patron=0, espacio="A1", profesor="PR1", **kw):
    return SesionIn(
        curso=curso,
        patron=patron,
        bloque=bloque,
        dia=dia,
        orden_inicio=orden,
        espacio=espacio,
        profesor=profesor,
        **kw,
    )


def _reglas(viol):
    return sorted(v.regla for v in viol)


def test_horario_valido_no_tiene_violaciones(minimo):
    inst = build_instance(minimo)
    assert validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1)]) == []


def test_mensaje_h6_nombra_profesor_y_hora(minimo):
    minimo.profesores[0].no_disponible = [RefFranja(plantilla="MAT", dia=4, orden=0)]
    inst = build_instance(minimo)
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=4, bloque=1)])
    assert _reglas(viol) == ["H6"]
    assert viol[0].mensaje == "A. López no está disponible el Vie 07:00"
    assert [e.id for e in viol[0].entidades] == ["PR1"]


def test_mensaje_h3(minimo):
    minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G2"], profesor="PR1"))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    inst = build_instance(minimo)
    sesiones = [_s(dia=0, bloque=0), _s(dia=1, bloque=1)]
    sesiones += [_s("C2", dia=0, bloque=0, espacio="A2"), _s("C2", dia=2, bloque=1, espacio="A2")]
    viol = validate_schedule(inst, sesiones)
    assert _reglas(viol) == ["H3"]
    assert viol[0].mensaje == "A. López tiene dos clases a la vez el Lun 07:00"


def test_bloque_sin_acomodar_viola_h1(minimo):
    inst = build_instance(minimo)
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(bloque=1, sin_acomodar=True)])
    assert _reglas(viol) == ["H1"]


def test_mezclar_patrones_viola_h1(minimo):
    minimo.materias[0] = MateriaIn(
        id="M1", nombre="Matemáticas", horas_semana=4, patrones=[[2, 2], [1, 1, 2]]
    )
    inst = build_instance(minimo)
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1, patron=1)])
    assert "H1" in _reglas(viol)


def test_profesor_distinto_entre_bloques_viola_h5(minimo):
    minimo.profesores.append(minimo.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    inst = build_instance(minimo)
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1, profesor="PR2")])
    assert _reglas(viol) == ["H5"]


def test_vacante_no_aplica_reglas_de_profesor(minimo):
    minimo.cursos[0].vacante = True
    inst = build_instance(minimo)
    sesiones = [_s(dia=0, bloque=0, profesor=None), _s(dia=1, bloque=1, profesor=None)]
    assert validate_schedule(inst, sesiones) == []
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1, profesor=None)])
    assert _reglas(viol) == ["H5"]


def test_espacio_sin_capacidad_o_de_otro_tipo_viola_h4(minimo):
    minimo.espacios[0].capacidad = 20
    inst = build_instance(minimo)
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1)])
    assert _reglas(viol) == ["H4", "H4"]
    assert viol[0].mensaje == "Aula 1 tiene capacidad 20 y Matemáticas (1A) tiene 30 alumnos"


def test_minimo_semanal_viola_h7(minimo):
    minimo.profesores.append(
        ProfesorIn(
            id="PR2",
            nombre="B. Ruiz",
            planteles=["P1"],
            materias=[],
            horas_min=2,
            horas_max=9,
            max_dia=4,
        )
    )
    inst = build_instance(minimo)
    viol = validate_schedule(inst, [_s(dia=0, bloque=0), _s(dia=1, bloque=1)])
    assert _reglas(viol) == ["H7"]
    assert viol[0].mensaje == "B. Ruiz tiene 0 h a la semana y su mínimo es 2"


def test_sin_huecos_por_profesor_reporta_s2(minimo):
    minimo.materias[0] = MateriaIn(id="M1", nombre="Matemáticas", horas_semana=2, patrones=[[1, 1]])
    minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G2"], profesor="PR1"))
    minimo.profesores[0].sin_huecos = True
    inst = build_instance(minimo)
    sesiones = [_s(dia=0, bloque=0), _s(dia=1, bloque=1)]
    sesiones += [_s("C2", dia=0, orden=2, bloque=0), _s("C2", dia=1, orden=1, bloque=1)]
    viol = validate_schedule(inst, sesiones)
    assert _reglas(viol) == ["S2"]
    assert viol[0].entidades[0].id == "PR1"
