from ortools.sat.python import cp_model

from solver.contract import (
    CursoIn,
    EspacioIn,
    GrupoIn,
    MateriaIn,
    PlantelIn,
    RefFranja,
    SesionIn,
    SolveRequest,
)
from solver.instance import build_instance
from solver.model.hard import Supuestos, add_hard
from solver.model.variables import build_vars

FACTIBLE = (cp_model.OPTIMAL, cp_model.FEASIBLE)


def _resolver(req: SolveRequest, permitir_parcial: bool = False, con_supuestos: bool = False):
    inst = build_instance(req)
    m = cp_model.CpModel()
    v = build_vars(m, inst)
    sup = Supuestos(m)
    add_hard(m, inst, v, sup, permitir_parcial)
    if con_supuestos:
        sup.como_supuestos()
    else:
        sup.fijar()
    solver = cp_model.CpSolver()
    solver.parameters.num_workers = 1
    solver.parameters.max_time_in_seconds = 10
    return solver.Solve(m), solver, v, sup


def _elegidos(solver, v) -> dict[str, tuple[int, int]]:
    """bloque -> (día, orden) de las x que quedaron en 1."""
    return {b: (d, o) for (b, d, o), x in v.x.items() if solver.Value(x)}


def _solo(req, profesor, permitidas):
    """El profesor solo está disponible en las franjas `permitidas` [(plantilla, día, orden)]."""
    prof = next(p for p in req.profesores if p.id == profesor)
    prof.no_disponible = [
        RefFranja(plantilla=pl.id, dia=f.dia, orden=f.orden)
        for pl in req.plantillas
        for f in pl.franjas
        if (pl.id, f.dia, f.orden) not in permitidas
    ]


def _dias(req, dias):
    for pl in req.plantillas:
        pl.franjas = [f for f in pl.franjas if f.dia in dias]
    return req


def _uno(id="M1", nombre="Matemáticas", horas=1, patrones=([1],), tipo="aula"):
    return MateriaIn(
        id=id, nombre=nombre, horas_semana=horas, patrones=list(patrones), tipo_espacio=tipo
    )


def _grupo(id, plantel="P1", plantilla="MAT", alumnos=30):
    return GrupoIn(id=id, nombre=id, plantel=plantel, plantilla=plantilla, alumnos=alumnos)


def test_dos_cursos_del_mismo_grupo_no_se_traslapan(minimo):
    _dias(minimo, {0, 1})
    minimo.cursos.append(CursoIn(id="C2", materia="M1", grupos=["G1"]))
    status, solver, v, _ = _resolver(minimo)
    assert status in FACTIBLE
    elegidos = _elegidos(solver, v)
    ocupadas = [(d, o + k) for (d, o) in elegidos.values() for k in range(2)]
    assert len(elegidos) == 4 and len(ocupadas) == len(set(ocupadas)) == 8


def test_profesor_compartido_entre_plantillas_traslapadas(mat_y_vesp):
    _solo(mat_y_vesp, "PR1", {("MAT", 0, 6), ("VESP", 0, 0)})  # ambas a las 13:00
    status, *_ = _resolver(mat_y_vesp)
    assert status == cp_model.INFEASIBLE
    _solo(mat_y_vesp, "PR1", {("MAT", 0, 6), ("VESP", 0, 1)})  # 13:00 y 14:00
    status, *_ = _resolver(mat_y_vesp)
    assert status in FACTIBLE


def test_nucleo_de_supuestos(mat_y_vesp):
    _solo(mat_y_vesp, "PR1", {("MAT", 0, 6), ("VESP", 0, 0)})
    status, solver, _, sup = _resolver(mat_y_vesp, con_supuestos=True)
    assert status == cp_model.INFEASIBLE
    nucleo = [sup.por_indice[i] for i in solver.SufficientAssumptionsForInfeasibility()]
    assert nucleo and {s.regla for s in nucleo} <= {"H3", "H6"}
    assert all(s.entidades[0].id == "PR1" for s in nucleo)


def test_sincronia_misma_hora(minimo):
    minimo.materias = [_uno()]
    minimo.profesores[0].materias = ["M1"]
    minimo.profesores.append(minimo.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    minimo.grupos.append(_grupo("G2"))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(
            id="C1", materia="M1", grupos=["G1", "G2"], profesor="PR1", sincronia="ING", alumnos=30
        ),
        CursoIn(
            id="C2", materia="M1", grupos=["G1", "G2"], profesor="PR2", sincronia="ING", alumnos=30
        ),
    ]
    _solo(minimo, "PR2", {("MAT", 3, 2)})
    status, solver, v, _ = _resolver(minimo)
    assert status in FACTIBLE
    elegidos = _elegidos(solver, v)
    assert elegidos["C1#0.0"] == elegidos["C2#0.0"] == (3, 2)


def test_fijada_se_respeta(minimo):
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=2, orden_inicio=2, fijada=True)]
    status, solver, v, _ = _resolver(minimo)
    assert status in FACTIBLE
    assert _elegidos(solver, v)["C1#0.0"] == (2, 2)


def test_traslado_entre_planteles(minimo):
    _dias(minimo, {0})
    minimo.materias = [_uno()]
    minimo.planteles.append(PlantelIn(id="P2", nombre="Plantel Norte"))
    minimo.espacios.append(
        EspacioIn(id="B1", plantel="P2", nombre="Aula Norte", tipo="aula", capacidad=40)
    )
    minimo.grupos.append(_grupo("G2", plantel="P2"))
    minimo.profesores[0].planteles = ["P1", "P2"]
    minimo.profesores[0].materias = ["M1"]
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], profesor="PR1"),
        CursoIn(id="C2", materia="M1", grupos=["G2"], profesor="PR1"),
    ]
    _solo(minimo, "PR1", {("MAT", 0, 0), ("MAT", 0, 1)})  # dos horas seguidas
    minimo.parametros.minutos_traslado = 50
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE
    minimo.parametros.minutos_traslado = 0
    assert _resolver(minimo)[0] in FACTIBLE


def test_profesor_elegido_es_unico(minimo):
    minimo.profesores.append(minimo.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    status, solver, v, _ = _resolver(minimo)
    assert status in FACTIBLE
    assert sum(solver.Value(y) for (c, _), y in v.y.items() if c == "C1") == 1
    quienes = {p for (b, p, d, o), t in v.t.items() if solver.Value(t)}
    assert len(quienes) == 1


def test_capacidad_por_umbral(minimo):
    _dias(minimo, {0})
    minimo.materias = [_uno("M2", "Química", tipo="laboratorio")]
    minimo.profesores[0].materias = ["M2"]
    minimo.profesores.append(minimo.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    minimo.grupos = [_grupo("G1", alumnos=40), _grupo("G2", alumnos=40)]
    minimo.espacios = [
        EspacioIn(id="L1", plantel="P1", nombre="Lab 1", tipo="laboratorio", capacidad=45),
        EspacioIn(id="L2", plantel="P1", nombre="Lab 2", tipo="laboratorio", capacidad=30),
    ]
    minimo.cursos = [
        CursoIn(id="C1", materia="M2", grupos=["G1"], profesor="PR1"),
        CursoIn(id="C2", materia="M2", grupos=["G2"], profesor="PR2"),
    ]
    _solo(minimo, "PR1", {("MAT", 0, 0)})
    _solo(minimo, "PR2", {("MAT", 0, 0)})
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE
    minimo.espacios[1].capacidad = 40
    assert _resolver(minimo)[0] in FACTIBLE


def test_no_disponible_se_respeta(minimo):
    _solo(minimo, "PR1", {("MAT", d, o) for d in (3, 4) for o in range(4)})
    status, solver, v, _ = _resolver(minimo)
    assert status in FACTIBLE
    assert {d for d, _ in _elegidos(solver, v).values()} == {3, 4}


def test_maximo_por_dia(minimo):
    _dias(minimo, {0})
    minimo.materias = [_uno(horas=2, patrones=([2],))]
    minimo.grupos.append(_grupo("G2"))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], profesor="PR1"),
        CursoIn(id="C2", materia="M1", grupos=["G2"], profesor="PR1"),
    ]
    minimo.profesores[0].max_dia = 3
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE
    minimo.profesores[0].max_dia = 4
    assert _resolver(minimo)[0] in FACTIBLE


def test_minimo_semanal_sin_cursos(minimo):
    minimo.profesores.append(
        minimo.profesores[0].model_copy(update={"id": "PR2", "materias": [], "horas_min": 2})
    )
    status, solver, _, sup = _resolver(minimo, con_supuestos=True)
    assert status == cp_model.INFEASIBLE
    nucleo = [sup.por_indice[i] for i in solver.SufficientAssumptionsForInfeasibility()]
    assert [(s.regla, s.entidades[0].id) for s in nucleo] == [("H7", "PR2")]


def test_dos_bloques_de_un_curso_en_dias_distintos(minimo):
    _dias(minimo, {0})
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE


def test_parcial_permite_no_acomodar(minimo):
    _solo(minimo, "PR1", set())
    status, solver, v, _ = _resolver(minimo, permitir_parcial=True)
    assert status in FACTIBLE
    assert solver.Value(v.acomodado["C1"]) == 0 and not _elegidos(solver, v)


def test_elige_el_patron_que_cabe(minimo):
    minimo.materias = [_uno(horas=2, patrones=([2], [1, 1]))]
    _solo(minimo, "PR1", {("MAT", d, 0) for d in range(5)})  # solo franjas sueltas
    status, solver, v, _ = _resolver(minimo)
    assert status in FACTIBLE
    assert solver.Value(v.sel[("C1", 1)]) == 1 and solver.Value(v.sel[("C1", 0)]) == 0


def test_vacantes_no_chocan_entre_si(minimo):
    _dias(minimo, {0})
    minimo.materias = [_uno()]
    minimo.grupos = [_grupo(f"G{i}") for i in range(5)]
    minimo.espacios = [
        EspacioIn(id=f"A{i}", plantel="P1", nombre=f"Aula {i}", tipo="aula", capacidad=40)
        for i in range(5)
    ]
    minimo.cursos = [
        CursoIn(id=f"C{i}", materia="M1", grupos=[f"G{i}"], vacante=True) for i in range(5)
    ]
    status, solver, v, _ = _resolver(minimo)  # 5 cursos y 4 franjas: sin profesor no hay choque
    assert status in FACTIBLE and not v.t
