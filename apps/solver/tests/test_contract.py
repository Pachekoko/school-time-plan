import pytest
from pydantic import ValidationError

from solver.contract import CursoIn, FranjaIn, ProfesorIn, SesionIn, SolveRequest
from solver.export_schema import write_all

from helpers import CONTRACTS, minimo_dict


def test_request_minimo_valido():
    req = SolveRequest.model_validate(minimo_dict())
    assert req.parametros.tiempo_limite_s == 60
    assert req.parametros.reglas.S1.modo == "obligatoria"
    assert req.parametros.reglas.S2.peso == 7
    assert req.parametros.reglas.S7.modo == "obligatoria"
    assert req.materias[0].tipo_espacio == "aula"


def test_rechaza_version_desconocida():
    with pytest.raises(ValidationError):
        SolveRequest.model_validate({**minimo_dict(), "schema_version": "2"})


def test_rechaza_campos_extra():
    with pytest.raises(ValidationError):
        SolveRequest.model_validate({**minimo_dict(), "sobrante": 1})


def test_tiempo_limite_fuera_de_rango():
    with pytest.raises(ValidationError):
        SolveRequest.model_validate({**minimo_dict(), "parametros": {"tiempo_limite_s": 301}})


def test_vacante_con_profesor_es_invalido():
    with pytest.raises(ValidationError, match="vacante"):
        CursoIn(id="C1", materia="M1", grupos=["G1"], profesor="PR1", vacante=True)


def test_sesion_acomodada_requiere_hora():
    with pytest.raises(ValidationError, match="dia y orden_inicio"):
        SesionIn(curso="C1", bloque=0)
    sin_hora = SesionIn(curso="C1", bloque=0, sin_acomodar=True)
    assert sin_hora.dia is None and sin_hora.orden_inicio is None


def test_franja_que_termina_antes_de_empezar():
    with pytest.raises(ValidationError, match="posterior"):
        FranjaIn(dia=0, orden=0, inicio="08:00", fin="07:00", tipo="clase")


def test_horas_min_mayor_que_max():
    with pytest.raises(ValidationError, match="horas_min"):
        ProfesorIn(
            id="PR1", nombre="X", planteles=[], materias=[], horas_min=10, horas_max=5, max_dia=6
        )


def test_schemas_en_contracts_estan_al_dia(tmp_path):
    write_all(dest=tmp_path)
    for f in tmp_path.glob("*.schema.json"):
        assert f.read_bytes() == (CONTRACTS / f.name).read_bytes(), f.name
    assert len(list(tmp_path.glob("*.schema.json"))) == 4
