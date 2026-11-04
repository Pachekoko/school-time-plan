"""Convierte la carga horaria de UTEG (hoja `HORARIOS` de un .xlsx) en un SolveRequest.

Uso:
    uv run python bench/from_carga_xlsx.py carga.xlsx --docentes docentes.local.json -o salida.json

`docentes.local.json` (nunca se versiona) da a ciertos docentes un alias y disponibilidad:

    {"NOMBRE REAL": {"alias": "Docente F", "sin_huecos": false,
                     "no_disponible": [{"plantilla": "*", "dias": [0, 1], "desde": "14:00"}]}}

`plantilla` es "1y2", "3a6" o "*" (ambas); `dias` (0 = lunes) y `desde`/`hasta` son opcionales.
"""

import argparse
import json
import re
from pathlib import Path

from openpyxl import load_workbook

from solver.contract import SolveRequest

PATRONES = {1: [[1]], 2: [[2], [1, 1]], 3: [[2, 1]], 4: [[2, 2]]}
HORARIOS = {"1y2": ("1.º y 2.º semestre", 8, 14), "3a6": ("3.º a 6.º semestre", 8, 15)}
VACANTE = "GENERICO"


def normaliza(texto: str) -> str:
    return " ".join(str(texto).split())


def normaliza_materia(nombre: str) -> str:
    """Espacios de más fuera, y `|` final como número romano: `"Tutoria||"` → `"Tutoria II"`."""
    limpio = normaliza(nombre)
    m = re.fullmatch(r"(.*?)\s*(\|+)", limpio)
    return f"{m.group(1)} {'I' * len(m.group(2))}" if m else limpio


def semestre(grupo: str) -> int:
    """Los grupos `09…` son 6.º semestre del plan anterior (traen "Lengua Extranjera VI")."""
    return 6 if grupo.startswith("09") else int(grupo[:2])


def leer_carga(path: Path) -> list[dict]:
    """Filas (grupo, materia, horas, docente) de la hoja HORARIOS (encabezados en la fila 2)."""
    hoja = load_workbook(path, data_only=True)["HORARIOS"]
    filas = []
    for fila in hoja.iter_rows(min_row=3, values_only=True):
        grupo, materia, horas, docente = fila[1], fila[2], fila[3], fila[7]
        if not grupo or not materia:
            continue
        filas.append(
            {
                "grupo": normaliza(grupo),
                "materia": normaliza_materia(materia),
                "horas": int(horas),
                "docente": normaliza(docente or VACANTE),
            }
        )
    return filas


def convert(path: Path, docentes: dict | None = None, anonimizar: bool = True) -> SolveRequest:
    filas = leer_carga(path)
    docentes = {normaliza(k): v for k, v in (docentes or {}).items()}
    plantillas = [_plantilla(pid) for pid in HORARIOS]
    clase = {pl["id"]: [f for f in pl["franjas"] if f["tipo"] == "clase"] for pl in plantillas}

    grupos_reales = sorted({f["grupo"] for f in filas}, key=lambda g: (semestre(g), g))
    gid = {g: f"G{i:02d}" for i, g in enumerate(grupos_reales, start=1)}
    grupos = [
        {
            "id": gid[g],
            "nombre": f"{gid[g]} ({semestre(g)}.º)",
            "plantel": "P1",
            "plantilla": "1y2" if semestre(g) <= 2 else "3a6",
            "alumnos": 30,
        }
        for g in grupos_reales
    ]
    espacios = [
        {"id": f"A{i:02d}", "plantel": "P1", "nombre": f"Aula {i}", "tipo": "aula", "capacidad": 45}
        for i in range(1, len(grupos) + 1)
    ]

    claves_materia = sorted({(f["materia"], f["horas"]) for f in filas})
    mid = {k: f"M{i:02d}" for i, k in enumerate(claves_materia, start=1)}
    materias = [
        {"id": mid[(n, h)], "nombre": n, "horas_semana": h, "patrones": PATRONES[h]}
        for n, h in claves_materia
    ]

    reales = sorted({f["docente"] for f in filas} - {VACANTE})
    sin_alias = iter(range(1, len(reales) + 1))
    pid, nombre_de = {}, {}
    for i, real in enumerate(reales, start=1):
        pid[real] = f"P{i:02d}"
        alias = docentes.get(real, {}).get("alias")
        nombre_de[real] = alias or (f"Docente {next(sin_alias):02d}" if anonimizar else real)
    profesores = []
    for real in reales:
        suyas = [f for f in filas if f["docente"] == real]
        carga = sum(f["horas"] for f in suyas)
        extra = docentes.get(real, {})
        profesores.append(
            {
                "id": pid[real],
                "nombre": nombre_de[real],
                "planteles": ["P1"],
                "materias": sorted({mid[(f["materia"], f["horas"])] for f in suyas}),
                "horas_min": carga,
                "horas_max": carga,
                "max_dia": 7,
                "no_disponible": _no_disponible(extra.get("no_disponible", []), clase),
                "sin_huecos": bool(extra.get("sin_huecos", False)),
            }
        )

    cursos = []
    for i, f in enumerate(filas, start=1):
        vacante = f["docente"] == VACANTE
        cursos.append(
            {
                "id": f"C{i:03d}",
                "materia": mid[(f["materia"], f["horas"])],
                "grupos": [gid[f["grupo"]]],
                "vacante": vacante,
                "profesor": None if vacante else pid[f["docente"]],
            }
        )
    return SolveRequest.model_validate(
        {
            "schema_version": "1",
            "parametros": {
                "tiempo_limite_s": 60,
                "num_workers": 8,
                "reglas": {
                    "S1": {"modo": "obligatoria"},
                    "S7": {"modo": "obligatoria"},
                    "S2": {"modo": "preferencia", "peso": 8},
                },
            },
            "plantillas": plantillas,
            "planteles": [{"id": "P1", "nombre": "Plantel"}],
            "espacios": espacios,
            "materias": materias,
            "grupos": grupos,
            "profesores": profesores,
            "cursos": cursos,
        }
    )


def _plantilla(pid: str) -> dict:
    nombre, desde, hasta = HORARIOS[pid]
    franjas = [
        {"dia": d, "orden": o, "inicio": f"{h:02d}:00", "fin": f"{h + 1:02d}:00", "tipo": "clase"}
        for d in range(5)
        for o, h in enumerate(range(desde, hasta))
    ]
    return {"id": pid, "nombre": nombre, "franjas": franjas}


def _no_disponible(reglas: list[dict], clase: dict[str, list[dict]]) -> list[dict]:
    out = []
    for r in reglas:
        for pl, franjas in clase.items():
            if r.get("plantilla", "*") not in ("*", pl):
                continue
            for f in franjas:
                if f["dia"] in r.get("dias", range(5)) and r.get("desde", "00:00") <= f[
                    "inicio"
                ] < r.get("hasta", "24:00"):
                    out.append({"plantilla": pl, "dia": f["dia"], "orden": f["orden"]})
    return sorted(
        {tuple(x.items()): x for x in out}.values(),
        key=lambda x: (x["plantilla"], x["dia"], x["orden"]),
    )


def main(argv: list[str] | None = None) -> None:
    args = argparse.ArgumentParser(
        description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter
    )
    args.add_argument("xlsx", type=Path)
    args.add_argument("--docentes", type=Path, help="alias y disponibilidad (no se versiona)")
    args.add_argument("-o", "--salida", type=Path)
    args.add_argument("--sin-anonimizar", action="store_true")
    a = args.parse_args(argv)
    docentes = json.loads(a.docentes.read_text(encoding="utf-8")) if a.docentes else {}
    req = convert(a.xlsx, docentes, anonimizar=not a.sin_anonimizar)
    texto = json.dumps(req.model_dump(mode="json"), indent=2, ensure_ascii=False) + "\n"
    if a.salida:
        a.salida.parent.mkdir(parents=True, exist_ok=True)
        a.salida.write_text(texto, encoding="utf-8")
        print(f"{len(req.grupos)} grupos, {len(req.cursos)} cursos → {a.salida}")
    else:
        print(texto)


if __name__ == "__main__":
    main()
