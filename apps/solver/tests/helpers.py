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


def hhmm(minutos: int) -> str:
    return f"{minutos // 60:02d}:{minutos % 60:02d}"


def franjas_seguidas(
    dias, inicio: str, n: int, duracion: int = 50, receso_tras: int | None = None, receso: int = 30
) -> list[dict]:
    """`n` franjas de clase por día, una tras otra; con `receso_tras = k`, inserta un receso
    después de la k-ésima franja (el receso ocupa un `orden`)."""
    h, m = inicio.split(":")
    out = []
    for d in dias:
        t, orden = int(h) * 60 + int(m), 0
        for k in range(n):
            if receso_tras is not None and k == receso_tras:
                out.append(
                    {
                        "dia": d,
                        "orden": orden,
                        "inicio": hhmm(t),
                        "fin": hhmm(t + receso),
                        "tipo": "receso",
                    }
                )
                t, orden = t + receso, orden + 1
            out.append(
                {
                    "dia": d,
                    "orden": orden,
                    "inicio": hhmm(t),
                    "fin": hhmm(t + duracion),
                    "tipo": "clase",
                }
            )
            t, orden = t + duracion, orden + 1
    return out


def con_receso_dict() -> dict:
    """Como `minimo`, pero cada día: franjas 0, 1, 2 de clase, 3 de receso y 4, 5, 6 de clase."""
    p = minimo_dict()
    p["plantillas"][0]["franjas"] = franjas_seguidas(range(5), "07:00", 6, receso_tras=3)
    return p


def mat_y_vesp_dict() -> dict:
    """Lunes con dos plantillas que se traslapan: MAT (franjas de 50 min cada hora desde las 7:00,
    la 6 es 13:00–13:50) y VESP (desde las 13:00). G1 es matutino y G2 vespertino; cada uno
    tiene un curso de 1 h con el mismo profesor fijo PR1."""
    p = minimo_dict()
    mat = [
        {
            "dia": 0,
            "orden": o,
            "inicio": hhmm(420 + 60 * o),
            "fin": hhmm(470 + 60 * o),
            "tipo": "clase",
        }
        for o in range(7)
    ]
    vesp = [
        {
            "dia": 0,
            "orden": o,
            "inicio": hhmm(780 + 60 * o),
            "fin": hhmm(830 + 60 * o),
            "tipo": "clase",
        }
        for o in range(7)
    ]
    p["plantillas"] = [
        {"id": "MAT", "nombre": "Matutino", "franjas": mat},
        {"id": "VESP", "nombre": "Vespertino", "franjas": vesp},
    ]
    p["espacios"].append(
        {"id": "A2", "plantel": "P1", "nombre": "Aula 2", "tipo": "aula", "capacidad": 40}
    )
    p["materias"][0].update(horas_semana=1, patrones=[[1]])
    p["grupos"].append(
        {"id": "G2", "nombre": "1V", "plantel": "P1", "plantilla": "VESP", "alumnos": 30}
    )
    p["cursos"] = [
        {"id": "C1", "materia": "M1", "grupos": ["G1"], "profesor": "PR1"},
        {"id": "C2", "materia": "M1", "grupos": ["G2"], "profesor": "PR1"},
    ]
    return p
