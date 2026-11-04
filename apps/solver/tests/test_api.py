import pytest
from fastapi.testclient import TestClient

from solver.api import create_app

from helpers import minimo_dict

AUTH = {"Authorization": "Bearer secreto"}


@pytest.fixture
def cliente() -> TestClient:
    return TestClient(create_app(token="secreto"))


def _problema() -> dict:
    p = minimo_dict()
    p["parametros"] = {"num_workers": 1, "tiempo_limite_s": 5}
    return p


def test_health_sin_token(cliente):
    r = cliente.get("/health")
    assert r.status_code == 200 and r.json() == {"status": "ok", "schema_version": "1"}


def test_solve_sin_token_o_con_otro_token_401(cliente):
    assert cliente.post("/solve", json=_problema()).status_code == 401
    r = cliente.post("/solve", json=_problema(), headers={"Authorization": "Bearer otro"})
    assert r.status_code == 401 and r.json() == {"detail": "Token inválido"}


def test_solve_token_correcto_200(cliente):
    r = cliente.post("/solve", json=_problema(), headers=AUTH)
    assert r.status_code == 200
    cuerpo = r.json()
    assert cuerpo["estado"] == "ok" and len(cuerpo["sesiones"]) == 2


def test_validate_200(cliente):
    sesiones = [
        {
            "curso": "C1",
            "bloque": 0,
            "dia": 0,
            "orden_inicio": 0,
            "espacio": "A1",
            "profesor": "PR1",
        },
        {
            "curso": "C1",
            "bloque": 1,
            "dia": 2,
            "orden_inicio": 0,
            "espacio": "A1",
            "profesor": "PR1",
        },
    ]
    r = cliente.post(
        "/validate",
        headers=AUTH,
        json={"schema_version": "1", "problema": _problema(), "sesiones": sesiones},
    )
    assert r.status_code == 200 and r.json()["violaciones"] == []


def test_instance_error_422_con_mensajes(cliente):
    p = _problema()
    p["cursos"][0]["materia"] = "NOEXISTE"
    r = cliente.post("/solve", json=p, headers=AUTH)
    assert r.status_code == 422
    assert r.json() == {"detail": [{"msg": "curso C1: la materia NOEXISTE no existe"}]}


def test_version_desconocida_422(cliente):
    r = cliente.post("/solve", json={**_problema(), "schema_version": "2"}, headers=AUTH)
    assert r.status_code == 422


def test_respuesta_conserva_acentos(cliente):
    p = _problema()
    p["profesores"][0]["no_disponible"] = [{"plantilla": "MAT", "dia": 2, "orden": 0}]
    sesiones = [
        {
            "curso": "C1",
            "bloque": 0,
            "dia": 0,
            "orden_inicio": 0,
            "espacio": "A1",
            "profesor": "PR1",
        },
        {
            "curso": "C1",
            "bloque": 1,
            "dia": 2,
            "orden_inicio": 0,
            "espacio": "A1",
            "profesor": "PR1",
        },
    ]
    r = cliente.post(
        "/validate", headers=AUTH, json={"schema_version": "1", "problema": p, "sesiones": sesiones}
    )
    assert "Mié 07:00" in r.text and "\\u00e9" not in r.text


def test_sin_token_no_arranca(monkeypatch):
    monkeypatch.delenv("SOLVER_TOKEN", raising=False)
    with pytest.raises(RuntimeError, match="SOLVER_TOKEN"):
        create_app()
