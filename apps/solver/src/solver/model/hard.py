"""Reglas duras H1–H11.

Cada familia de reglas, por entidad, se activa con un literal de `Supuestos`. En la pasada
normal los literales se fijan a 1 (presolve los elimina); para explicar una infactibilidad se
vuelven supuestos de CP-SAT y su núcleo dice qué reglas chocan.
"""

from collections import defaultdict
from dataclasses import dataclass, field

from ortools.sat.python import cp_model

from solver.contract import Entidad
from solver.instance import Instance
from solver.model.variables import IntVar, Vars


@dataclass
class Supuesto:
    regla: str
    entidades: list[Entidad]
    datos: dict[str, object] = field(default_factory=dict)


class Supuestos:
    def __init__(self, m: cp_model.CpModel):
        self.m = m
        self.por_indice: dict[int, Supuesto] = {}
        self._por_clave: dict[tuple, IntVar] = {}

    def lit(self, regla: str, entidades: list[Entidad], **datos) -> IntVar:
        """Literal de la regla para esas entidades (se crea una sola vez)."""
        clave = (regla, tuple((e.tipo, e.id) for e in entidades), tuple(sorted(datos.items())))
        if clave not in self._por_clave:
            nombre = "_".join([regla, *(e.id for e in entidades), *map(str, datos.values())])
            v = self.m.NewBoolVar(f"sup[{nombre}]")
            self._por_clave[clave] = v
            self.por_indice[v.Index()] = Supuesto(regla, list(entidades), dict(datos))
        return self._por_clave[clave]

    def literales(self) -> list[IntVar]:
        return list(self._por_clave.values())

    def fijar(self) -> None:
        for v in self.literales():
            self.m.Add(v == 1)

    def como_supuestos(self) -> None:
        self.m.AddAssumptions(self.literales())


def _e(tipo: str, id: str) -> Entidad:
    return Entidad(tipo=tipo, id=id)


def add_hard(
    m: cp_model.CpModel, inst: Instance, v: Vars, sup: Supuestos, permitir_parcial: bool
) -> None:
    _patrones_y_bloques(m, inst, v, permitir_parcial)
    _profesor_elegido(m, inst, v)
    _sin_dobles(m, v.ocupa_grupo, lambda g: sup.lit("H2", [_e("grupo", g)]))
    _sin_dobles(m, v.ocupa_prof, lambda p: sup.lit("H3", [_e("profesor", p)]))
    _capacidad_espacios(m, inst, v, sup)
    _disponibilidad(m, inst, v, sup)
    _horas_profesor(m, inst, v, sup)
    _sincronias(m, inst, v, sup)
    _dias_distintos(m, inst, v, sup)
    _fijadas(m, inst, v, sup)
    _traslados(m, inst, v, sup)


def _patrones_y_bloques(m, inst: Instance, v: Vars, permitir_parcial: bool) -> None:
    """H1: un patrón por curso acomodado; cada bloque de ese patrón, exactamente una vez."""
    x_por_bloque: dict[str, list[IntVar]] = defaultdict(list)
    for (b, _, _), xv in v.x.items():
        x_por_bloque[b].append(xv)
    for c in inst.req.cursos:
        patrones = inst.bloques_por_curso[c.id]
        m.Add(sum(v.sel[(c.id, k)] for k in range(len(patrones))) == v.acomodado[c.id])
        for k, bloques in enumerate(patrones):
            for b in bloques:
                m.Add(sum(x_por_bloque[b.id]) == v.sel[(c.id, k)])
        if not permitir_parcial:
            m.Add(v.acomodado[c.id] == 1)


def _profesor_elegido(m, inst: Instance, v: Vars) -> None:
    """Con 2+ candidatos: un solo profesor por curso y quien da cada inicio es ese profesor."""
    y_por_curso: dict[str, list[tuple[str, IntVar]]] = defaultdict(list)
    for (c, p), yv in v.y.items():
        y_por_curso[c].append((p, yv))
    t_por_x: dict[tuple[str, int, int], list[tuple[str, IntVar]]] = defaultdict(list)
    for (b, p, d, o), tv in v.t.items():
        t_por_x[(b, d, o)].append((p, tv))
    for c, ys in y_por_curso.items():
        m.Add(sum(yv for _, yv in ys) == v.acomodado[c])
    for (b, d, o), xv in v.x.items():
        curso = inst.cursos[v.bloque[b].curso]
        if curso.vacante or v.profesor_unico[curso.id] is not None:
            continue
        ts = t_por_x.get((b, d, o), [])
        m.Add(sum(tv for _, tv in ts) == xv)  # sin candidatos, el bloque no se acomoda
        ys = dict(y_por_curso[curso.id])
        for p, tv in ts:
            m.AddImplication(tv, ys[p])


def _sin_dobles(m, ocupacion: dict[tuple, list[IntVar]], lit) -> None:
    """H2 (grupo, día, franja) y H3 (profesor, día, átomo): a lo sumo una clase."""
    for (quien, _, _), lits in ocupacion.items():
        if len(lits) > 1:
            m.Add(sum(lits) <= 1).OnlyEnforceIf(lit(quien))


def _capacidad_espacios(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H4: por (plantel, tipo, día, átomo), los bloques con k o más alumnos no superan a los
    espacios con capacidad k o más (condición de Hall para capacidades anidadas).

    Si todos los espacios del tipo les caben a todos sus bloques y ninguno está fijado, ese
    conteo basta: los bloques son intervalos y siempre se pueden repartir (la etapa 2 elige
    cuál). Si no, se agrega la asignación exacta de espacio por bloque (`v.z`)."""
    activos: dict[tuple[str, str], dict[tuple[int, int], list[tuple[int, str, IntVar]]]] = (
        defaultdict(lambda: defaultdict(list))
    )
    for (b_id, d, o), xv in v.x.items():
        b = v.bloque[b_id]
        tipo = inst.materias[inst.cursos[b.curso].materia].tipo_espacio
        plantel, plantilla = inst.curso_plantel(b.curso), inst.curso_plantilla(b.curso)
        alumnos = inst.curso_alumnos(b.curso)
        atomos = {a for k in range(b.duracion) for a in inst.atomos_cubiertos(plantilla, d, o + k)}
        for a in atomos:
            activos[(plantel, tipo)][(d, a)].append((alumnos, b_id, xv))
    fijos = {f"{f.curso}#{f.patron}.{f.bloque}": f for f in inst.req.fijadas if f.espacio}
    x_por_bloque: dict[str, list[IntVar]] = defaultdict(list)
    for (b_id, _, _), xv in v.x.items():
        x_por_bloque[b_id].append(xv)
    for (plantel, tipo), por_ranura in activos.items():
        espacios = [e for e in inst.espacios.values() if e.plantel == plantel and e.tipo == tipo]
        lit = sup.lit("H4", [_e("plantel", plantel)], tipo=tipo)
        umbrales = sorted({n for lista in por_ranura.values() for n, _, _ in lista})
        for lista in por_ranura.values():
            for k in umbrales:
                grandes = [xv for n, _, xv in lista if n >= k]
                cupo = sum(1 for e in espacios if e.capacidad >= k)
                if len(grandes) > cupo:
                    m.Add(sum(grandes) <= cupo).OnlyEnforceIf(lit)
        alumnos = {b_id: n for lista in por_ranura.values() for n, b_id, _ in lista}
        if any(b in fijos for b in alumnos) or any(
            n > e.capacidad for n in alumnos.values() for e in espacios
        ):
            _espacios_exactos(m, v, sup, lit, espacios, alumnos, por_ranura, x_por_bloque, fijos)


def _espacios_exactos(m, v: Vars, sup, lit, espacios, alumnos, por_ranura, x_por_bloque, fijos):
    """Cada bloque acomodado usa un espacio que le cabe (el fijado, si lo trae) y ningún
    espacio tiene dos bloques en el mismo átomo."""
    for b_id, n in alumnos.items():
        zs = []
        for e in espacios:
            if e.capacidad >= n:
                v.z[(b_id, e.id)] = m.NewBoolVar(f"z[{b_id},{e.id}]")
                zs.append(v.z[(b_id, e.id)])
        colocado = sum(x_por_bloque[b_id])
        m.Add(sum(zs) <= colocado)
        m.Add(sum(zs) >= colocado).OnlyEnforceIf(lit)
        if b_id in fijos:
            lit_fija = sup.lit("H10", [_e("sesion", b_id)])
            z_fijo = v.z.get((b_id, fijos[b_id].espacio))
            if z_fijo is None:
                m.AddBoolOr([lit_fija.Not()])
            else:
                m.Add(z_fijo == 1).OnlyEnforceIf(lit_fija)
    for lista in por_ranura.values():
        for e in espacios:
            por_bloque: dict[str, list[IntVar]] = defaultdict(list)
            for _, b_id, xv in lista:
                if (b_id, e.id) in v.z:
                    por_bloque[b_id].append(xv)
            if len(por_bloque) < 2:
                continue
            ocupa = []
            for b_id, xs in por_bloque.items():
                y = m.NewBoolVar("")
                m.Add(y >= v.z[(b_id, e.id)] + sum(xs) - 1)
                ocupa.append(y)
            m.Add(sum(ocupa) <= 1).OnlyEnforceIf(lit)


def _disponibilidad(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H6: nadie da clase en una franja que marcó como no disponible."""
    for (b_id, p, d, o), tv in v.t.items():
        b = v.bloque[b_id]
        plantilla = inst.curso_plantilla(b.curso)
        if any(inst.disponible(p, plantilla, d, o + k) == "N" for k in range(b.duracion)):
            m.Add(tv == 0).OnlyEnforceIf(sup.lit("H6", [_e("profesor", p)]))


def _horas_profesor(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H7: máximo por día; mínimo y máximo por semana."""
    por_profesor: dict[str, list[tuple[int, int, IntVar]]] = defaultdict(list)
    for (b_id, p, d, _), tv in v.t.items():
        por_profesor[p].append((d, v.bloque[b_id].duracion, tv))
    for p, prof in inst.profesores.items():
        terminos = por_profesor.get(p, [])
        lit = sup.lit("H7", [_e("profesor", p)])
        por_dia: dict[int, list[tuple[int, IntVar]]] = defaultdict(list)
        for d, dur, tv in terminos:
            por_dia[d].append((dur, tv))
        for lista in por_dia.values():
            if sum(dur for dur, _ in lista) > prof.max_dia:
                m.Add(sum(dur * tv for dur, tv in lista) <= prof.max_dia).OnlyEnforceIf(lit)
        total = sum(dur * tv for _, dur, tv in terminos)
        if sum(dur for _, dur, _ in terminos) > prof.horas_max:
            m.Add(total <= prof.horas_max).OnlyEnforceIf(lit)
        if prof.horas_min > 0:
            if terminos:
                m.Add(total >= prof.horas_min).OnlyEnforceIf(lit)
            else:
                m.AddBoolOr([lit.Not()])


def _sincronias(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H8: los cursos de una sincronía eligen el mismo patrón y empiezan a la misma hora."""
    for sinc, cursos in inst.sincronias.items():
        lider, lit = cursos[0], sup.lit("H8", [_e("sincronia", sinc)])
        for c in cursos[1:]:
            for k, (b_lider, b_otro) in enumerate(
                zip(inst.bloques_por_curso[lider], inst.bloques_por_curso[c], strict=False)
            ):
                m.Add(v.sel[(c, k)] == v.sel[(lider, k)]).OnlyEnforceIf(lit)
                for bl, bo in zip(b_lider, b_otro, strict=False):
                    xl = {(d, o): xv for d, o, xv in v.x_de(bl.id)}
                    xo = {(d, o): xv for d, o, xv in v.x_de(bo.id)}
                    for ranura in xl.keys() | xo.keys():
                        if ranura in xl and ranura in xo:
                            m.Add(xl[ranura] == xo[ranura]).OnlyEnforceIf(lit)
                        else:
                            sola = xl[ranura] if ranura in xl else xo[ranura]
                            m.Add(sola == 0).OnlyEnforceIf(lit)


def _dias_distintos(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H9: a lo sumo un bloque de cada curso por día."""
    por_dia: dict[tuple[str, int], list[IntVar]] = defaultdict(list)
    for (b_id, d, _), xv in v.x.items():
        por_dia[(v.bloque[b_id].curso, d)].append(xv)
    for (curso, _), lits in por_dia.items():
        if len(lits) > 1:
            m.Add(sum(lits) <= 1).OnlyEnforceIf(sup.lit("H9", [_e("curso", curso)]))


def _fijadas(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H10: las sesiones fijadas no se mueven (ni cambian de profesor, si lo traen)."""
    for f in inst.req.fijadas:
        lit = sup.lit("H10", [_e("sesion", f"{f.curso}#{f.patron}.{f.bloque}")])
        b = inst.bloques_por_curso[f.curso][f.patron][f.bloque]
        requeridas = [v.x.get((b.id, f.dia, f.orden_inicio))]
        if f.profesor is not None:
            requeridas.append(v.t.get((b.id, f.profesor, f.dia, f.orden_inicio)))
        for var in requeridas:
            if var is None:
                m.AddBoolOr([lit.Not()])
            else:
                m.Add(var == 1).OnlyEnforceIf(lit)


def _traslados(m, inst: Instance, v: Vars, sup: Supuestos) -> None:
    """H11: entre clases de planteles distintos el mismo día, al menos `minutos_traslado`."""
    traslado = inst.req.parametros.minutos_traslado
    if traslado <= 0:
        return
    presencias: dict[str, dict[tuple[int, int, str], list[IntVar]]] = defaultdict(
        lambda: defaultdict(list)
    )
    for (b_id, p, d, o), tv in v.t.items():
        b = v.bloque[b_id]
        plantel, plantilla = inst.curso_plantel(b.curso), inst.curso_plantilla(b.curso)
        for k in range(b.duracion):
            for a in inst.atomos_cubiertos(plantilla, d, o + k):
                presencias[p][(d, a, plantel)].append(tv)
    for p, por_lugar in presencias.items():
        if len({plantel for _, _, plantel in por_lugar}) < 2:
            continue
        lit = sup.lit("H11", [_e("profesor", p)])
        presente = {}
        for clave, ts in por_lugar.items():
            presente[clave] = m.NewBoolVar(f"presente[{p},{clave}]")
            for tv in ts:
                m.AddImplication(tv, presente[clave])
        for (d1, a1, k1), p1 in presente.items():
            for (d2, a2, k2), p2 in presente.items():
                if d1 != d2 or k1 == k2 or a1 >= a2:
                    continue
                if inst.atomos[d1][a2][0] - inst.atomos[d1][a1][1] < traslado:
                    m.AddBoolOr([p1.Not(), p2.Not()]).OnlyEnforceIf(lit)
