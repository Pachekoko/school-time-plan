"""Reglas configurables S1–S7 como expresiones del modelo.

Cada `crudo` está reificado de forma exacta (OR, AND y huecos con igualdades), así que su valor
coincide con `solver.scoring` en cualquier solución factible, no solo en la óptima.
"""

from collections import defaultdict

from ortools.sat.python import cp_model

from solver.contract import Entidad, ReglasConfig
from solver.instance import Instance
from solver.model.hard import Supuestos
from solver.model.variables import IntVar, Vars
from solver.scoring import FACTORES, REGLAS_S, aplica

PENAL_SIN_ACOMODAR = 1_000_000

Lit = IntVar | int  # 0 representa "falso" constante
Clave = tuple[tuple[str, str], ...]


def add_soft(
    m: cp_model.CpModel, inst: Instance, v: Vars, sup: Supuestos, reglas: ReglasConfig
) -> dict[str, cp_model.LinearExpr]:
    """Agrega las reglas que no están `apagada` y devuelve el `crudo` de cada una."""
    activa = {r: getattr(reglas, r).modo != "apagada" for r in REGLAS_S}
    t_por_prof_dia: dict[tuple[str, int], list[tuple[str, int, IntVar]]] = defaultdict(list)
    for (b_id, p, d, o), tv in v.t.items():
        t_por_prof_dia[(p, d)].append((b_id, o, tv))

    por_entidad: dict[str, dict[Clave, cp_model.LinearExpr]] = {}
    if activa["S1"]:
        por_entidad["S1"] = _huecos_grupos(m, inst, v)
    sin_huecos = [p for p, prof in inst.profesores.items() if prof.sin_huecos]
    s2 = _huecos_profesores(m, inst, v, list(inst.profesores) if activa["S2"] else sin_huecos)
    if activa["S2"]:
        por_entidad["S2"] = s2
    if activa["S3"]:
        por_entidad["S3"] = _dias_por_profesor(m, inst, t_por_prof_dia)
    if activa["S4"]:
        por_entidad["S4"] = _no_preferentes(inst, v)
    if activa["S5"]:
        por_entidad["S5"] = _dias_consecutivos(m, inst, v)
    if activa["S6"]:
        por_entidad["S6"] = _reglas_de_franja(inst, v, reglas)
    if activa["S7"]:
        por_entidad["S7"] = _mismo_docente(m, inst, v)

    for regla, exprs in por_entidad.items():
        if getattr(reglas, regla).modo == "obligatoria":
            for clave, expr in exprs.items():
                ents = [Entidad(tipo=t, id=i) for t, i in clave]
                m.Add(expr == 0).OnlyEnforceIf(sup.lit(regla, ents))
    for p in sin_huecos:
        clave = (("profesor", p),)
        if clave in s2:
            m.Add(s2[clave] == 0).OnlyEnforceIf(sup.lit("S2", [Entidad(tipo="profesor", id=p)]))
    return {regla: sum(exprs.values()) for regla, exprs in por_entidad.items()}


def build_objective(
    v: Vars, crudos: dict[str, cp_model.LinearExpr], reglas: ReglasConfig
) -> cp_model.LinearExpr:
    ponderado = sum(getattr(reglas, r).peso * FACTORES[r] * e for r, e in crudos.items())
    return ponderado + PENAL_SIN_ACOMODAR * sum(1 - a for a in v.acomodado.values())


# --- Utilidades booleanas ---------------------------------------------------


def _or(m: cp_model.CpModel, lits: list[Lit]) -> Lit:
    lits = [x for x in lits if not isinstance(x, int)]
    if not lits:
        return 0
    if len(lits) == 1:
        return lits[0]
    b = m.NewBoolVar("")
    m.AddMaxEquality(b, lits)
    return b


def _and(m: cp_model.CpModel, lits: list[Lit]) -> Lit:
    if any(isinstance(x, int) for x in lits):
        return 0
    b = m.NewBoolVar("")
    m.AddMinEquality(b, lits)
    return b


def _huecos(m: cp_model.CpModel, ocupado: list[Lit], peso: list[int]) -> cp_model.LinearExpr:
    """Suma de `peso` de las posiciones libres entre la primera y la última ocupada."""
    n = len(ocupado)
    antes: list[Lit] = [0] * n
    despues: list[Lit] = [0] * n
    for i in range(1, n):
        antes[i] = _or(m, [antes[i - 1], ocupado[i - 1]])
    for i in range(n - 2, -1, -1):
        despues[i] = _or(m, [despues[i + 1], ocupado[i + 1]])
    total = []
    for i in range(1, n - 1):
        if isinstance(antes[i], int) or isinstance(despues[i], int):
            continue
        libre = 1 if isinstance(ocupado[i], int) else None
        h = m.NewBoolVar("")
        partes = [antes[i], despues[i]] + ([] if libre else [ocupado[i].Not()])
        m.AddBoolAnd(partes).OnlyEnforceIf(h)
        m.AddBoolOr([p.Not() for p in partes]).OnlyEnforceIf(h.Not())
        total.append(peso[i] * h)
    return sum(total)


# --- Reglas -----------------------------------------------------------------


def _huecos_grupos(m, inst: Instance, v: Vars) -> dict[Clave, cp_model.LinearExpr]:
    """S1: franjas de clase libres entre la primera y la última clase del grupo, por día."""
    out = {}
    for g, grupo in inst.grupos.items():
        total = 0
        for d in inst.dias(grupo.plantilla):
            franjas = inst.franjas_clase(grupo.plantilla, d)
            ocupado = [_or(m, v.ocupa_grupo.get((g, d, f.orden), [])) for f in franjas]
            total += _huecos(m, ocupado, [1] * len(franjas))
        out[(("grupo", g),)] = total
    return out


def _huecos_profesores(m, inst: Instance, v: Vars, profesores: list[str]):
    """S2: minutos libres entre la primera y la última clase del profesor, por día, contando
    los átomos de clase de las plantillas de sus cursos."""
    out = {}
    for p in profesores:
        plantillas = {inst.curso_plantilla(c) for c in inst.cursos_de_profesor(p)}
        if not plantillas:
            continue
        total = 0
        for d, atomos in inst.atomos.items():
            relevantes = sorted({a for pl in plantillas for a in inst.atomos_de_plantilla(pl, d)})
            ocupado = [_or(m, v.ocupa_prof.get((p, d, a), [])) for a in relevantes]
            duracion = [atomos[a][1] - atomos[a][0] for a in relevantes]
            total += _huecos(m, ocupado, duracion)
        out[(("profesor", p),)] = total
    return out


def _dias_por_profesor(m, inst: Instance, t_por_prof_dia) -> dict[Clave, cp_model.LinearExpr]:
    """S3: días en que el profesor tiene al menos una clase."""
    out: dict[Clave, cp_model.LinearExpr] = defaultdict(int)
    for (p, _), ts in t_por_prof_dia.items():
        out[(("profesor", p),)] += _or(m, [tv for _, _, tv in ts])
    return dict(out)


def _no_preferentes(inst: Instance, v: Vars) -> dict[Clave, cp_model.LinearExpr]:
    """S4: franjas impartidas en estado D por profesores que marcaron alguna P."""
    out: dict[Clave, cp_model.LinearExpr] = defaultdict(int)
    for (b_id, p, d, o), tv in v.t.items():
        if not inst.profesores[p].preferente:
            continue
        b = v.bloque[b_id]
        pl = inst.curso_plantilla(b.curso)
        n = sum(1 for k in range(b.duracion) if inst.disponible(p, pl, d, o + k) == "D")
        if n:
            out[(("profesor", p),)] += n * tv
    return dict(out)


def _dias_consecutivos(m, inst: Instance, v: Vars) -> dict[Clave, cp_model.LinearExpr]:
    """S5: pares de días seguidos en que un curso tiene clase ambos días."""
    por_dia: dict[str, dict[int, list[IntVar]]] = defaultdict(lambda: defaultdict(list))
    for (b_id, d, _), xv in v.x.items():
        por_dia[v.bloque[b_id].curso][d].append(xv)
    out = {}
    for curso, dias in por_dia.items():
        usa = {d: _or(m, lits) for d, lits in dias.items()}
        out[(("curso", curso),)] = sum(_and(m, [usa[d], usa[d + 1]]) for d in usa if d + 1 in usa)
    return out


def _reglas_de_franja(inst: Instance, v: Vars, reglas: ReglasConfig):
    """S6: franjas impartidas dentro de una restricción que aplica a la materia."""
    restricciones = [
        (r, {(f.plantilla, f.dia, f.orden) for f in r.franjas}) for r in reglas.S6_restricciones
    ]
    out: dict[Clave, cp_model.LinearExpr] = defaultdict(int)
    for (b_id, d, o), xv in v.x.items():
        b = v.bloque[b_id]
        materia = inst.materias[inst.cursos[b.curso].materia]
        pl = inst.curso_plantilla(b.curso)
        n = sum(
            1
            for k in range(b.duracion)
            if any(
                aplica(r, materia.id, materia.tipo_espacio) and (pl, d, o + k) in refs
                for r, refs in restricciones
            )
        )
        if n:
            out[(("curso", b.curso),)] += n * xv
    return dict(out)


def _mismo_docente(m, inst: Instance, v: Vars) -> dict[Clave, cp_model.LinearExpr]:
    """S7: por (grupo, profesor, día), cursos distintos de más: Σ da − OR(da)."""
    da: dict[tuple[str, str, int], list[IntVar]] = defaultdict(list)  # (curso, prof, día)
    for (b_id, p, d, _), tv in v.t.items():
        da[(v.bloque[b_id].curso, p, d)].append(tv)
    por_grupo: dict[tuple[str, str, int], list[Lit]] = defaultdict(list)
    for (curso, p, d), ts in da.items():
        for g in inst.cursos[curso].grupos:
            por_grupo[(g, p, d)].append(_or(m, ts))
    out: dict[Clave, cp_model.LinearExpr] = defaultdict(int)
    for (g, p, _), cursos in por_grupo.items():
        if len(cursos) > 1:
            out[(("grupo", g), ("profesor", p))] += sum(cursos) - _or(m, cursos)
    return dict(out)
