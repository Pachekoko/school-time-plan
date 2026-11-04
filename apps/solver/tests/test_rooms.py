import pytest

from solver.contract import CursoIn, EspacioIn, MateriaIn, SesionIn
from solver.instance import build_instance
from solver.model.rooms import EspaciosError, assign_rooms


def _s(curso, dia=0, orden=0, espacio=None, bloque=0, **kw):
    return SesionIn(
        curso=curso,
        bloque=bloque,
        dia=dia,
        orden_inicio=orden,
        espacio=espacio,
        profesor=None,
        **kw,
    )


def _dos_grupos(minimo, horas=(1, 1), tipo="aula"):
    minimo.materias = [
        MateriaIn(
            id=f"M{i}", nombre=f"Materia {i}", horas_semana=h, patrones=[[h]], tipo_espacio=tipo
        )
        for i, h in enumerate(horas, start=1)
    ]
    minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], vacante=True),
        CursoIn(id="C2", materia="M2", grupos=["G2"], vacante=True),
    ]
    return minimo


def test_asigna_lab_disponible(minimo):
    _dos_grupos(minimo, tipo="laboratorio")
    minimo.espacios.append(
        EspacioIn(id="L1", plantel="P1", nombre="Lab 1", tipo="laboratorio", capacidad=40)
    )
    out = assign_rooms(build_instance(minimo), [_s("C1", 0, 0), _s("C2", 0, 1)], 5)
    assert [s.espacio for s in out] == ["L1", "L1"]


def test_respeta_espacio_fijado(minimo):
    _dos_grupos(minimo)
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    out = assign_rooms(build_instance(minimo), [_s("C1"), _s("C2", espacio="A1")], 5)
    assert [s.espacio for s in out] == ["A2", "A1"]


def test_respeta_capacidad(minimo):
    _dos_grupos(minimo)
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A0", "capacidad": 10}))
    out = assign_rooms(build_instance(minimo), [_s("C1", 0, 0), _s("C2", 1, 0)], 5)
    assert [s.espacio for s in out] == ["A1", "A1"]


def test_un_bloque_conserva_espacio_en_sus_dos_franjas(minimo):
    _dos_grupos(minimo, horas=(2, 1))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    out = assign_rooms(build_instance(minimo), [_s("C1", 0, 0), _s("C2", 0, 1)], 5)
    assert out[0].espacio != out[1].espacio


def test_sin_acomodar_pasa_igual(minimo):
    _dos_grupos(minimo)
    sin = SesionIn(curso="C2", bloque=0, sin_acomodar=True)
    out = assign_rooms(build_instance(minimo), [_s("C1"), sin], 5)
    assert out[0].espacio == "A1" and out[1] == sin


def test_sin_reparto_posible_nombra_plantel_y_tipo(minimo):
    _dos_grupos(minimo)
    with pytest.raises(EspaciosError) as e:
        assign_rooms(build_instance(minimo), [_s("C1"), _s("C2")], 5)
    assert (e.value.plantel, e.value.tipo) == ("P1", "aula")
