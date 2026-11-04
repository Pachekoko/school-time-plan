"""Escuela demo ficticia "Prepa Horizonte" para la demo pública y el benchmark.

Bachillerato general de 6 semestres (horas por materia según el plan UdeG 2025: 2, 3 o 4 h por
semana) más un Bachillerato Intensivo Semiescolarizado sabatino, en 3 planteles. Todos los
nombres son ficticios.

Uso: `uv run python bench/generate_demo.py` → contracts/fixtures/demo/prepa-horizonte.json
"""

import json
import math
import random
from pathlib import Path

from solver.contract import SolveRequest

DESTINO = Path(__file__).resolve().parents[3] / "contracts" / "fixtures" / "demo"

PLANTELES = [("CEN", "Plantel Centro"), ("NOR", "Plantel Norte"), ("SUR", "Plantel Sur")]
PATRONES = {2: [[2], [1, 1]], 3: [[2, 1]], 4: [[2, 2]]}

# (nombre, área, horas por semana, tipo de espacio). Inglés va por nivel en semestres impares.
PLAN = {
    1: [
        ("Pensamiento Matemático I", "MAT", 4, "aula"),
        ("Química I", "CIE", 4, "laboratorio"),
        ("Lengua y Comunicación I", "LEN", 3, "aula"),
        ("Inglés I", "ING", 3, "aula"),
        ("Cultura Digital I", "DIG", 3, "computo"),
        ("Humanidades I", "HUM", 4, "aula"),
        ("Ciencias Sociales I", "HUM", 3, "aula"),
        ("Actividad Física I", "FIS", 2, "cancha"),
        ("Tutoría I", "SOC", 2, "aula"),
    ],
    2: [
        ("Pensamiento Matemático II", "MAT", 4, "aula"),
        ("Física I", "CIE", 4, "laboratorio"),
        ("Lengua y Comunicación II", "LEN", 3, "aula"),
        ("Inglés II", "ING", 3, "aula"),
        ("Cultura Digital II", "DIG", 3, "computo"),
        ("Humanidades II", "HUM", 4, "aula"),
        ("Ciencias Sociales II", "HUM", 3, "aula"),
        ("Actividad Física II", "FIS", 2, "cancha"),
        ("Tutoría II", "SOC", 2, "aula"),
    ],
    3: [
        ("Pensamiento Matemático III", "MAT", 4, "aula"),
        ("Biología", "CIE", 4, "laboratorio"),
        ("Comprensión Lectora", "LEN", 3, "aula"),
        ("Inglés III", "ING", 3, "aula"),
        ("Historia de México", "HUM", 3, "aula"),
        ("Filosofía", "HUM", 3, "aula"),
        ("Educación Financiera", "MAT", 3, "aula"),
        ("Actividad Física III", "FIS", 2, "cancha"),
        ("Socioemocional III", "SOC", 2, "aula"),
    ],
    4: [
        ("Pensamiento Matemático IV", "MAT", 4, "aula"),
        ("Química II", "CIE", 4, "laboratorio"),
        ("Análisis Literario", "LEN", 3, "aula"),
        ("Inglés IV", "ING", 3, "aula"),
        ("Ciudadanía", "HUM", 3, "aula"),
        ("Arte", "HUM", 2, "aula"),
        ("Pensamiento Computacional", "DIG", 3, "computo"),
        ("Actividad Física IV", "FIS", 2, "cancha"),
        ("Socioemocional IV", "SOC", 2, "aula"),
    ],
    5: [
        ("Pensamiento Matemático V", "MAT", 4, "aula"),
        ("Física II", "CIE", 4, "laboratorio"),
        ("Redacción", "LEN", 3, "aula"),
        ("Inglés V", "ING", 3, "aula"),
        ("Ética", "HUM", 3, "aula"),
        ("Economía", "HUM", 3, "aula"),
        ("Programación", "DIG", 3, "computo"),
        ("Actividad Física V", "FIS", 2, "cancha"),
        ("Socioemocional V", "SOC", 2, "aula"),
    ],
    6: [
        ("Pensamiento Matemático VI", "MAT", 4, "aula"),
        ("Ecología", "CIE", 4, "laboratorio"),
        ("Literatura", "LEN", 3, "aula"),
        ("Inglés VI", "ING", 3, "aula"),
        ("Psicología", "HUM", 3, "aula"),
        ("Emprendimiento", "HUM", 3, "aula"),
        ("Ciencia de Datos", "DIG", 3, "computo"),
        ("Actividad Física VI", "FIS", 2, "cancha"),
        ("Socioemocional VI", "SOC", 2, "aula"),
    ],
}
BIS = [("BIS Matemáticas", 2), ("BIS Comunicación", 2), ("BIS Ciencias", 2)]
TURNO = {s: ("MAT" if s <= 3 else "VESP") for s in PLAN}
HORAS_POR_PROFESOR = 22  # demanda que cubre cada profesor (horas_max es 26)
PROB_DIA_LIBRE = 0.3  # profesores con un día de la semana que no vienen
PROB_PREFERENTE = 0.3  # profesores que prefieren las primeras horas
EN_DOS_PLANTELES = 15  # profesores que dan clase en dos planteles
TURNOS_SEPARADOS = False  # True: cada profesor en un solo turno

NOMBRES = [
    "Ana",
    "Luis",
    "María",
    "Jorge",
    "Sofía",
    "Raúl",
    "Elena",
    "Diego",
    "Paula",
    "Iván",
    "Carmen",
    "Hugo",
    "Lucía",
    "Óscar",
    "Rosa",
    "Andrés",
    "Julia",
    "Pablo",
    "Teresa",
    "Marco",
    "Irene",
    "Tomás",
    "Alma",
    "Rubén",
    "Nora",
    "Félix",
    "Silvia",
    "Germán",
]
APELLIDOS = [
    "Ríos",
    "Mora",
    "Vega",
    "Luna",
    "Soto",
    "Cruz",
    "Pardo",
    "Nava",
    "Salas",
    "Ibarra",
    "Rangel",
    "Ochoa",
    "Lara",
    "Meza",
    "Campos",
    "Duarte",
    "Fierro",
    "Robles",
    "Tapia",
    "Zúñiga",
    "Bravo",
    "Castro",
    "Durán",
    "Esquivel",
    "Galindo",
    "Herrera",
    "Juárez",
]


def _hhmm(minutos: int) -> str:
    return f"{minutos // 60:02d}:{minutos % 60:02d}"


def _franjas(
    dias, inicio: int, n: int, duracion: int, receso_tras: int | None = None, receso: int = 30
) -> list[dict]:
    out = []
    for d in dias:
        t, orden = inicio, 0
        for k in range(n):
            if k == receso_tras:
                out.append(
                    {
                        "dia": d,
                        "orden": orden,
                        "inicio": _hhmm(t),
                        "fin": _hhmm(t + receso),
                        "tipo": "receso",
                    }
                )
                t, orden = t + receso, orden + 1
            out.append(
                {
                    "dia": d,
                    "orden": orden,
                    "inicio": _hhmm(t),
                    "fin": _hhmm(t + duracion),
                    "tipo": "clase",
                }
            )
            t, orden = t + duracion, orden + 1
    return out


def _clave(nombre: str) -> str:
    limpio = nombre.upper().translate(str.maketrans("ÁÉÍÓÚÜÑ", "AEIOUUN"))
    return "".join(c for c in limpio if c.isalnum())


def build(seed: int = 42) -> SolveRequest:
    rng = random.Random(seed)
    plantillas = [
        {
            "id": "MAT",
            "nombre": "Matutino L–V",
            "franjas": _franjas(range(5), 7 * 60, 7, 50, receso_tras=3),
        },
        {
            "id": "VESP",
            "nombre": "Vespertino L–V",
            "franjas": _franjas(range(5), 13 * 60, 7, 50, receso_tras=3),
        },
        {"id": "SAB", "nombre": "Sabatino", "franjas": _franjas([5], 8 * 60, 6, 60)},
    ]
    materias, area_de = [], {}
    for sem, lista in PLAN.items():
        for nombre, area, horas, tipo in lista:
            mid = f"S{sem}-{_clave(nombre)}"
            materias.append(
                {
                    "id": mid,
                    "nombre": nombre,
                    "horas_semana": horas,
                    "patrones": PATRONES[horas],
                    "tipo_espacio": tipo,
                }
            )
            area_de[mid] = (area, sem)
    for nombre, horas in BIS:
        mid = f"BIS-{_clave(nombre)}"
        materias.append(
            {
                "id": mid,
                "nombre": nombre,
                "horas_semana": horas,
                "patrones": [[2]],
                "tipo_espacio": "aula",
            }
        )
        area_de[mid] = (mid, None)  # cada materia BIS con su propio profesor (S7 en sábado)

    planteles = [{"id": pid, "nombre": nombre} for pid, nombre in PLANTELES]
    espacios, grupos, cursos = [], [], []
    for pid, _ in PLANTELES:
        espacios += [
            {
                "id": f"{pid}-A{i}",
                "plantel": pid,
                "nombre": f"Aula {i}",
                "tipo": "aula",
                "capacidad": 45,
            }
            for i in range(1, 7)
        ]
        espacios += [
            {
                "id": f"{pid}-LAB",
                "plantel": pid,
                "nombre": "Laboratorio",
                "tipo": "laboratorio",
                "capacidad": 45,
            },
            {
                "id": f"{pid}-CMP",
                "plantel": pid,
                "nombre": "Sala de cómputo",
                "tipo": "computo",
                "capacidad": 45,
            },
            {
                "id": f"{pid}-CAN",
                "plantel": pid,
                "nombre": "Cancha",
                "tipo": "cancha",
                "capacidad": 60,
            },
        ]
        for sem, lista in PLAN.items():
            secciones = []
            for letra in "AB":
                gid = f"{pid}-{sem}{letra}"
                alumnos = rng.randint(25, 45)
                grupos.append(
                    {
                        "id": gid,
                        "nombre": f"{sem}{letra} {pid}",
                        "plantel": pid,
                        "plantilla": TURNO[sem],
                        "alumnos": alumnos,
                    }
                )
                secciones.append((gid, alumnos))
            for nombre, area, _, _ in lista:
                mid = f"S{sem}-{_clave(nombre)}"
                if area == "ING" and sem % 2 == 1:
                    ids = [g for g, _ in secciones]
                    por_nivel = math.ceil(sum(a for _, a in secciones) / 2)
                    for nivel in (1, 2):
                        cursos.append(
                            {
                                "id": f"{pid}-{mid}-N{nivel}",
                                "materia": mid,
                                "grupos": ids,
                                "sincronia": f"{pid}-ING{sem}",
                                "alumnos": por_nivel,
                            }
                        )
                else:
                    cursos += [
                        {"id": f"{gid}-{mid}", "materia": mid, "grupos": [gid]}
                        for gid, _ in secciones
                    ]
        gid = f"{pid}-BIS"
        grupos.append(
            {
                "id": gid,
                "nombre": f"BIS {pid}",
                "plantel": pid,
                "plantilla": "SAB",
                "alumnos": rng.randint(20, 35),
            }
        )
        cursos += [
            {"id": f"{gid}-{_clave(n)}", "materia": f"BIS-{_clave(n)}", "grupos": [gid]}
            for n, _ in BIS
        ]

    profesores = _profesores(rng, plantillas, materias, area_de, cursos, grupos)
    fis = [m["id"] for m in materias if area_de[m["id"]][0] == "FIS"]
    primera_hora = [{"plantilla": "MAT", "dia": d, "orden": 0} for d in range(5)]
    return SolveRequest.model_validate(
        {
            "schema_version": "1",
            "parametros": {
                "tiempo_limite_s": 60,
                "num_workers": 8,
                "semilla": seed,
                "minutos_traslado": 50,
                "reglas": {"S6_restricciones": [{"materias": fis, "franjas": primera_hora}]},
            },
            "plantillas": plantillas,
            "planteles": planteles,
            "espacios": espacios,
            "materias": materias,
            "grupos": grupos,
            "profesores": profesores,
            "cursos": cursos,
        }
    )


def _profesores(rng, plantillas, materias, area_de, cursos, grupos) -> list[dict]:
    """Profesores por asignatura según la demanda de horas por (área, plantel, jornada).

    Con `TURNOS_SEPARADOS` cada profesor da clase en un solo turno; si no, en matutino y
    vespertino de su plantel (lo común en preparatorias privadas)."""
    turno_de = {g["id"]: g["plantilla"] for g in grupos}
    plantel_de = {g["id"]: g["plantel"] for g in grupos}
    horas = {m["id"]: m["horas_semana"] for m in materias}

    def jornada(turno: str) -> str:
        return turno if TURNOS_SEPARADOS or turno == "SAB" else "LV"

    demanda: dict[tuple[str, str, str], int] = {}
    for c in cursos:
        g = c["grupos"][0]
        clave = (area_de[c["materia"]][0], plantel_de[g], jornada(turno_de[g]))
        demanda[clave] = demanda.get(clave, 0) + horas[c["materia"]]
    franjas = {pl["id"]: [f for f in pl["franjas"] if f["tipo"] == "clase"] for pl in plantillas}
    turnos = {"MAT": ["MAT"], "VESP": ["VESP"], "LV": ["MAT", "VESP"], "SAB": ["SAB"]}
    nombres = rng.sample([f"{n} {a}" for n in NOMBRES for a in APELLIDOS], 200)
    profesores = []
    for (area, plantel, jor), total in sorted(demanda.items()):
        habilitadas = [
            m
            for m, (a, sem) in area_de.items()
            if a == area and (sem is None or TURNO[sem] in turnos[jor])
        ]
        minimo = 2 if area == "ING" else 1  # los niveles de Inglés van a la misma hora
        for _ in range(max(minimo, math.ceil(total / HORAS_POR_PROFESOR))):
            no_disponible, preferente = [], []
            if jor != "SAB" and rng.random() < PROB_DIA_LIBRE:
                dia = rng.randrange(5)
                no_disponible = [
                    {"plantilla": t, "dia": f["dia"], "orden": f["orden"]}
                    for t in turnos[jor]
                    for f in franjas[t]
                    if f["dia"] == dia
                ]
            if rng.random() < PROB_PREFERENTE:
                t = turnos[jor][0]
                preferente = [
                    {"plantilla": t, "dia": f["dia"], "orden": f["orden"]}
                    for f in franjas[t]
                    if f["orden"] <= 2
                ]
            profesores.append(
                {
                    "id": f"PR{len(profesores) + 1:03d}",
                    "nombre": nombres[len(profesores)],
                    "planteles": [plantel],
                    "materias": habilitadas,
                    "horas_max": 26,
                    "max_dia": 7,
                    "no_disponible": no_disponible,
                    "preferente": preferente,
                }
            )
    ids_planteles = [p for p, _ in PLANTELES]
    for prof in rng.sample(
        [p for p in profesores if "BIS" not in p["materias"][0]], EN_DOS_PLANTELES
    ):
        otro = ids_planteles[(ids_planteles.index(prof["planteles"][0]) + 1) % 3]
        prof["planteles"].append(otro)  # da clase en dos planteles
    return profesores


def main() -> None:
    DESTINO.mkdir(parents=True, exist_ok=True)
    destino = DESTINO / "prepa-horizonte.json"
    datos = build(42).model_dump(mode="json")
    destino.write_text(json.dumps(datos, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Escuela demo escrita en {destino}")


if __name__ == "__main__":
    main()
