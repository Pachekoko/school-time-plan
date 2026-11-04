"""Datos y rutas compartidas por las pruebas."""

import copy
from pathlib import Path

REPO = Path(__file__).resolve().parents[3]
CONTRACTS = REPO / "contracts"
FIXTURES = CONTRACTS / "fixtures"

_DIAS_LV = range(5)
_FRANJAS_MAT = [("07:00", "07:50"), ("07:50", "08:40"), ("08:40", "09:30"), ("09:30", "10:20")]

_MINIMO = {
    "schema_version": "1",
    "parametros": {},
    "plantillas": [
        {
            "id": "MAT",
            "nombre": "Matutino",
            "franjas": [
                {"dia": d, "orden": o, "inicio": i, "fin": f, "tipo": "clase"}
                for d in _DIAS_LV
                for o, (i, f) in enumerate(_FRANJAS_MAT)
            ],
        }
    ],
    "planteles": [{"id": "P1", "nombre": "Plantel Centro"}],
    "espacios": [
        {"id": "A1", "plantel": "P1", "nombre": "Aula 1", "tipo": "aula", "capacidad": 40}
    ],
    "materias": [{"id": "M1", "nombre": "Matemáticas", "horas_semana": 4, "patrones": [[2, 2]]}],
    "grupos": [{"id": "G1", "nombre": "1A", "plantel": "P1", "plantilla": "MAT", "alumnos": 30}],
    "profesores": [
        {
            "id": "PR1",
            "nombre": "A. López",
            "planteles": ["P1"],
            "materias": ["M1"],
            "horas_max": 20,
            "max_dia": 6,
        }
    ],
    "cursos": [{"id": "C1", "materia": "M1", "grupos": ["G1"]}],
}


def minimo_dict() -> dict:
    """Problema mínimo válido: 1 grupo, 1 materia de 4 h en [2, 2], 1 profesor y 1 aula."""
    return copy.deepcopy(_MINIMO)
