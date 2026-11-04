"""Generador de problemas pequeños y válidos para las pruebas de propiedades."""

from hypothesis import strategies as st

from solver.contract import SolveRequest

from helpers import franjas_seguidas

PATRONES = {1: [[[1]]], 2: [[[2]], [[1, 1]], [[2], [1, 1]]], 3: [[[2, 1]]], 4: [[[2, 2]]]}


@st.composite
def problemas(draw) -> SolveRequest:
    plantillas = []
    for i in range(draw(st.integers(1, 2))):
        dias = range(draw(st.sampled_from([1, 2, 3, 3, 3])))
        n = draw(st.integers(3, 6))
        receso = draw(st.none() | st.integers(1, n - 1))
        inicio = draw(st.sampled_from(["07:00", "08:00", "11:00"]))
        plantillas.append(
            {
                "id": f"PL{i}",
                "nombre": f"Plantilla {i}",
                "franjas": franjas_seguidas(dias, inicio, n, receso_tras=receso),
            }
        )
    franjas = [(pl["id"], f["dia"], f["orden"]) for pl in plantillas for f in pl["franjas"]]

    planteles = [{"id": f"P{i}", "nombre": f"Plantel {i}"} for i in range(draw(st.integers(1, 2)))]
    espacios = []
    for p in planteles:
        for j in range(draw(st.integers(2, 3))):
            espacios.append(
                {
                    "id": f"{p['id']}A{j}",
                    "plantel": p["id"],
                    "nombre": f"Aula {j}",
                    "tipo": "aula",
                    "capacidad": draw(st.sampled_from([30, 40])),
                }
            )
        espacios.append(
            {
                "id": f"{p['id']}L",
                "plantel": p["id"],
                "nombre": "Lab",
                "tipo": "laboratorio",
                "capacidad": 40,
            }
        )

    materias = []
    for i in range(draw(st.integers(1, 4))):
        horas = draw(st.integers(1, 4))
        materias.append(
            {
                "id": f"M{i}",
                "nombre": f"Materia {i}",
                "horas_semana": horas,
                "patrones": draw(st.sampled_from(PATRONES[horas])),
                "tipo_espacio": draw(st.sampled_from(["aula", "aula", "aula", "laboratorio"])),
            }
        )
    ids_materias = [m["id"] for m in materias]

    grupos = [
        {
            "id": f"G{i}",
            "nombre": f"{i}A",
            "plantel": draw(st.sampled_from(planteles))["id"],
            "plantilla": draw(st.sampled_from(plantillas))["id"],
            "alumnos": draw(st.integers(20, 40)),
        }
        for i in range(draw(st.integers(1, 4)))
    ]

    def refs(maximo):
        elegidas = draw(st.lists(st.sampled_from(franjas), max_size=maximo, unique=True))
        return [{"plantilla": pl, "dia": d, "orden": o} for pl, d, o in elegidas]

    profesores = []
    for i in range(draw(st.integers(1, 3))):
        ids_planteles = [p["id"] for p in planteles]
        profesores.append(
            {
                "id": f"PR{i}",
                "nombre": f"Profesor {i}",
                # PR0 puede dar todo en todos lados: así casi no hay cursos sin candidato
                "planteles": ids_planteles
                if i == 0
                else draw(st.lists(st.sampled_from(ids_planteles), min_size=1, unique=True)),
                "materias": ids_materias
                if i == 0
                else draw(st.lists(st.sampled_from(ids_materias), min_size=1, unique=True)),
                "horas_max": draw(st.integers(4, 20)),
                "max_dia": draw(st.integers(2, 6)),
                "no_disponible": refs(6),
                "preferente": refs(8),
                "sin_huecos": draw(st.integers(0, 3)) == 0,
            }
        )

    cursos = []
    for i in range(draw(st.integers(2, 8))):
        vacante = draw(st.integers(0, 4)) == 0
        profesor = None if vacante else draw(st.none() | st.sampled_from(profesores))
        cursos.append(
            {
                "id": f"C{i}",
                "materia": draw(st.sampled_from(ids_materias)),
                "grupos": [draw(st.sampled_from(grupos))["id"]],
                "profesor": profesor["id"] if profesor else None,
                "vacante": vacante,
            }
        )
    if len(cursos) >= 1 and draw(st.booleans()):
        base = cursos[0]
        cursos.append(
            {
                **base,
                "id": "CS",
                "profesor": None,
                "vacante": False,
                "sincronia": "SINC",
                "alumnos": 20,
            }
        )
        base.update(sincronia="SINC", alumnos=20)

    modo = st.sampled_from(["preferencia", "obligatoria"])
    return SolveRequest.model_validate(
        {
            "schema_version": "1",
            "parametros": {
                "tiempo_limite_s": 5,
                "num_workers": 1,
                "minutos_traslado": draw(st.sampled_from([0, 30, 60])),
                "permitir_parcial": draw(st.integers(0, 3)) == 0,
                "reglas": {"S1": {"modo": draw(modo)}, "S7": {"modo": draw(modo)}},
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


@st.composite
def problemas_diminutos(draw) -> SolveRequest:
    """Un día, un plantel, 1–3 cursos de un bloque y 1–2 espacios: tan chicos que se pueden
    resolver por fuerza bruta."""
    n = draw(st.integers(2, 4))
    tipo = draw(st.sampled_from(["aula", "laboratorio"]))
    espacios = [
        {
            "id": f"E{i}",
            "plantel": "P1",
            "nombre": f"Espacio {i}",
            "tipo": tipo,
            "capacidad": draw(st.sampled_from([25, 45])),
        }
        for i in range(draw(st.sampled_from([1, 2, 2, 2])))
    ]
    grupos = [
        {
            "id": f"G{i}",
            "nombre": f"{i}A",
            "plantel": "P1",
            "plantilla": "MAT",
            "alumnos": draw(st.sampled_from([20, 40])),
        }
        for i in range(draw(st.integers(1, 3)))
    ]
    materias = [
        {
            "id": f"M{h}",
            "nombre": f"Materia {h}",
            "horas_semana": h,
            "patrones": [[h]],
            "tipo_espacio": tipo,
        }
        for h in (1, 2)
    ]
    franjas = [(0, o) for o in range(n)]
    profesores = [
        {
            "id": f"PR{i}",
            "nombre": f"Profesor {i}",
            "planteles": ["P1"],
            "materias": ["M1", "M2"],
            "horas_max": 8,
            "max_dia": 8,
            "preferente": [
                {"plantilla": "MAT", "dia": d, "orden": o}
                for d, o in draw(st.lists(st.sampled_from(franjas), max_size=3))
            ],
        }
        for i in range(draw(st.integers(1, 3)))
    ]
    cursos = []
    for i in range(draw(st.integers(2, 3))):
        vacante = draw(st.integers(0, 4)) == 0
        cursos.append(
            {
                "id": f"C{i}",
                "materia": f"M{draw(st.integers(1, 2))}",
                "grupos": [draw(st.sampled_from(grupos))["id"]],
                "vacante": vacante,
                "profesor": None if vacante else draw(st.sampled_from(profesores))["id"],
            }
        )
    fijadas = []
    if draw(st.integers(0, 3)) > 0:
        c = draw(st.sampled_from(cursos))
        horas = int(c["materia"][1])
        if horas <= n:
            fijadas.append(
                {
                    "curso": c["id"],
                    "bloque": 0,
                    "dia": 0,
                    "orden_inicio": draw(st.integers(0, n - horas)),
                    "espacio": draw(st.sampled_from([None, *espacios, *espacios])),
                }
            )
            if fijadas[0]["espacio"] is not None:
                fijadas[0]["espacio"] = fijadas[0]["espacio"]["id"]
    return SolveRequest.model_validate(
        {
            "schema_version": "1",
            "parametros": {
                "tiempo_limite_s": 5,
                "num_workers": 1,
                "reglas": {"S1": {"modo": draw(st.sampled_from(["preferencia", "obligatoria"]))}},
            },
            "plantillas": [
                {"id": "MAT", "nombre": "Matutino", "franjas": franjas_seguidas([0], "07:00", n)}
            ],
            "planteles": [{"id": "P1", "nombre": "Plantel"}],
            "espacios": espacios,
            "materias": materias,
            "grupos": grupos,
            "profesores": profesores,
            "cursos": cursos,
            "fijadas": fijadas,
        }
    )
