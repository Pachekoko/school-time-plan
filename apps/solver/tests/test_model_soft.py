from ortools.sat.python import cp_model

from solver.contract import (
    CursoIn,
    MateriaIn,
    RefFranja,
    ReglaConfig,
    RestriccionFranja,
    SesionIn,
    SolveRequest,
)
from solver.instance import build_instance
from solver.model.hard import Supuestos, add_hard
from solver.model.soft import add_soft, build_objective
from solver.model.variables import build_vars
from solver.scoring import score

FACTIBLE = (cp_model.OPTIMAL, cp_model.FEASIBLE)


def _resolver(req: SolveRequest):
    inst = build_instance(req)
    m = cp_model.CpModel()
    v = build_vars(m, inst)
    sup = Supuestos(m)
    add_hard(m, inst, v, sup, permitir_parcial=False)
    crudos = add_soft(m, inst, v, sup, req.parametros.reglas)
    m.Minimize(build_objective(v, crudos, req.parametros.reglas))
    sup.fijar()
    solver = cp_model.CpSolver()
    solver.parameters.num_workers = 1
    solver.parameters.max_time_in_seconds = 20
    return solver.Solve(m), solver, inst, v, crudos


def _sesiones(solver, inst, v) -> list[SesionIn]:
    """Extracción mínima para comparar con scoring (sin espacios)."""
    out = []
    for (b_id, d, o), xv in v.x.items():
        if not solver.Value(xv):
            continue
        b = v.bloque[b_id]
        prof = next(
            (
                p
                for (bb, p, dd, oo), tv in v.t.items()
                if (bb, dd, oo) == (b_id, d, o) and solver.Value(tv)
            ),
            None,
        )
        out.append(
            SesionIn(
                curso=b.curso,
                patron=b.patron,
                bloque=b.indice,
                dia=d,
                orden_inicio=o,
                profesor=prof,
            )
        )
    return out


def _donde(solver, v) -> dict[str, tuple[int, int]]:
    return {b: (d, o) for (b, d, o), x in v.x.items() if solver.Value(x)}


def _dias(req, dias):
    req.plantillas[0].franjas = [f for f in req.plantillas[0].franjas if f.dia in dias]


def _solo(req, profesor, permitidas):
    prof = next(p for p in req.profesores if p.id == profesor)
    prof.no_disponible = [
        RefFranja(plantilla="MAT", dia=f.dia, orden=f.orden)
        for f in req.plantillas[0].franjas
        if (f.dia, f.orden) not in permitidas
    ]


def _dos_cursos(req, mismo_grupo: bool, mismo_profesor: bool = True):
    """C1 y C2 de 1 h (materias M1 y M2)."""
    req.materias = [
        MateriaIn(id=f"M{i}", nombre=f"Materia {i}", horas_semana=1, patrones=[[1]]) for i in (1, 2)
    ]
    req.profesores[0].materias = ["M1", "M2"]
    req.profesores.append(req.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    if not mismo_grupo:
        req.grupos.append(req.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
        req.espacios.append(req.espacios[0].model_copy(update={"id": "A2"}))
    req.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], profesor="PR1"),
        CursoIn(
            id="C2",
            materia="M2",
            grupos=["G1" if mismo_grupo else "G2"],
            profesor="PR1" if mismo_profesor else "PR2",
        ),
    ]
    return req


def test_s1_obligatoria_sin_huecos(minimo):
    _dias(minimo, {0})
    _dos_cursos(minimo, mismo_grupo=True, mismo_profesor=False)
    _solo(minimo, "PR1", {(0, 0)})
    _solo(minimo, "PR2", {(0, 2), (0, 3)})
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE
    minimo.parametros.reglas.S1 = ReglaConfig(modo="preferencia", peso=5)
    status, solver, _, _, crudos = _resolver(minimo)
    assert status in FACTIBLE and solver.Value(crudos["S1"]) == 1
    minimo.parametros.reglas.S1 = ReglaConfig(modo="obligatoria")
    _solo(minimo, "PR2", {(0, 1), (0, 2), (0, 3)})
    status, solver, _, v, _ = _resolver(minimo)
    assert status in FACTIBLE and _donde(solver, v)["C2#0.0"] == (0, 1)


def test_s2_prefiere_compactar_profesor(minimo):
    _dias(minimo, {0})
    _dos_cursos(minimo, mismo_grupo=False)
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0)]
    status, solver, _, v, crudos = _resolver(minimo)
    assert status == cp_model.OPTIMAL
    assert _donde(solver, v)["C2#0.0"] == (0, 1) and solver.Value(crudos["S2"]) == 0


def test_s3_menos_dias(minimo):
    _dias(minimo, {0, 1})
    _dos_cursos(minimo, mismo_grupo=False)
    status, solver, _, v, crudos = _resolver(minimo)
    assert status == cp_model.OPTIMAL
    dias = {d for d, _ in _donde(solver, v).values()}
    assert len(dias) == 1 and solver.Value(crudos["S3"]) == 1


def test_s4_usa_preferentes(minimo):
    minimo.materias[0] = MateriaIn(id="M1", nombre="M", horas_semana=1, patrones=[[1]])
    minimo.profesores[0].preferente = [RefFranja(plantilla="MAT", dia=1, orden=2)]
    status, solver, _, v, crudos = _resolver(minimo)
    assert status == cp_model.OPTIMAL
    assert _donde(solver, v)["C1#0.0"] == (1, 2) and solver.Value(crudos["S4"]) == 0


def test_s5_reparte_en_la_semana(minimo):
    status, solver, _, v, crudos = _resolver(minimo)
    assert status == cp_model.OPTIMAL
    d1, d2 = sorted(d for d, _ in _donde(solver, v).values())
    assert d2 - d1 >= 2 and solver.Value(crudos["S5"]) == 0


def test_s6_ed_fisica_no_primera_hora(minimo):
    minimo.materias[0] = MateriaIn(id="M1", nombre="Ed. Física", horas_semana=1, patrones=[[1]])
    minimo.parametros.reglas.S6_restricciones = [
        RestriccionFranja(
            materias=["M1"], franjas=[RefFranja(plantilla="MAT", dia=d, orden=0) for d in range(5)]
        )
    ]
    _solo(minimo, "PR1", {(0, 0), (0, 1)})
    status, solver, _, v, crudos = _resolver(minimo)
    assert status == cp_model.OPTIMAL
    assert _donde(solver, v)["C1#0.0"] == (0, 1) and solver.Value(crudos["S6"]) == 0


def test_s7_mismo_docente_un_curso_por_dia(minimo):
    _dias(minimo, {0, 1})
    _dos_cursos(minimo, mismo_grupo=True)
    status, solver, _, v, crudos = _resolver(minimo)
    assert status in FACTIBLE
    donde = _donde(solver, v)
    assert donde["C1#0.0"][0] != donde["C2#0.0"][0] and solver.Value(crudos["S7"]) == 0
    _dias(minimo, {0})
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE
    minimo.parametros.reglas.S7 = ReglaConfig(modo="preferencia", peso=5)
    status, solver, _, _, crudos = _resolver(minimo)
    assert status in FACTIBLE and solver.Value(crudos["S7"]) == 1


def test_sin_huecos_por_profesor(minimo):
    _dias(minimo, {0})
    _dos_cursos(minimo, mismo_grupo=False)
    minimo.parametros.reglas.S2 = ReglaConfig(modo="preferencia", peso=1)
    minimo.fijadas = [SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0)]
    _solo(minimo, "PR1", {(0, 0), (0, 2), (0, 3)})
    assert _resolver(minimo)[0] in FACTIBLE
    minimo.profesores[0].sin_huecos = True
    assert _resolver(minimo)[0] == cp_model.INFEASIBLE


def test_apagada_no_aparece(minimo):
    minimo.parametros.reglas.S5 = ReglaConfig(modo="apagada")
    *_, crudos = _resolver(minimo)
    assert "S5" not in crudos and set(crudos) == {"S1", "S2", "S3", "S4", "S6", "S7"}


def test_crudos_del_modelo_coinciden_con_scoring(minimo):
    """Caso con penalizaciones distintas de cero en todas las reglas."""
    _dias(minimo, {0, 1, 2})
    minimo.materias = [
        MateriaIn(id="M1", nombre="Matemáticas", horas_semana=2, patrones=[[1, 1]]),
        MateriaIn(id="M2", nombre="Química", horas_semana=1, patrones=[[1]]),
        MateriaIn(id="M3", nombre="Historia", horas_semana=2, patrones=[[2], [1, 1]]),
    ]
    minimo.profesores[0].materias = ["M1", "M2", "M3"]
    minimo.profesores[0].preferente = [RefFranja(plantilla="MAT", dia=0, orden=3)]
    minimo.profesores.append(minimo.profesores[0].model_copy(update={"id": "PR2", "nombre": "B"}))
    minimo.grupos.append(minimo.grupos[0].model_copy(update={"id": "G2", "nombre": "1B"}))
    minimo.espacios.append(minimo.espacios[0].model_copy(update={"id": "A2"}))
    minimo.cursos = [
        CursoIn(id="C1", materia="M1", grupos=["G1"], profesor="PR1"),
        CursoIn(id="C2", materia="M2", grupos=["G1"], profesor="PR1"),
        CursoIn(id="C3", materia="M3", grupos=["G2"]),
        CursoIn(id="C4", materia="M1", grupos=["G2"], profesor="PR2"),
    ]
    reglas = minimo.parametros.reglas
    reglas.S1 = ReglaConfig(modo="preferencia", peso=1)
    reglas.S7 = ReglaConfig(modo="preferencia", peso=1)
    reglas.S6_restricciones = [
        RestriccionFranja(franjas=[RefFranja(plantilla="MAT", dia=d, orden=1) for d in range(3)])
    ]
    minimo.fijadas = [
        SesionIn(curso="C1", bloque=0, dia=0, orden_inicio=0),
        SesionIn(curso="C1", bloque=1, dia=1, orden_inicio=2),
        SesionIn(curso="C2", bloque=0, dia=0, orden_inicio=2),
        SesionIn(curso="C4", bloque=0, dia=1, orden_inicio=1),
    ]
    status, solver, inst, v, crudos = _resolver(minimo)
    assert status in FACTIBLE
    _, desglose = score(inst, _sesiones(solver, inst, v))
    assert {r: solver.Value(e) for r, e in crudos.items()} == {
        r: p.crudo for r, p in desglose.items()
    }
    assert sum(solver.Value(e) for e in crudos.values()) > 0
