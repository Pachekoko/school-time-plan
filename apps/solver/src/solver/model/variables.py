"""Variables del modelo: inicio de cada bloque (x), patrón (sel), profesor (y) y quién da cada
inicio (t).

Si el curso tiene profesor fijo o un solo candidato, `t` es la misma variable que `x`.
"""

from collections import defaultdict
from dataclasses import dataclass

from ortools.sat.python import cp_model

from solver.instance import Bloque, Instance

IntVar = cp_model.IntVar


@dataclass
class Vars:
    x: dict[tuple[str, int, int], IntVar]  # (bloque, día, orden de inicio)
    y: dict[tuple[str, str], IntVar]  # (curso, profesor), solo con 2+ candidatos
    t: dict[tuple[str, str, int, int], IntVar]  # (bloque, profesor, día, orden de inicio)
    sel: dict[tuple[str, int], IntVar]  # (curso, patrón)
    acomodado: dict[str, IntVar]  # curso
    profesor_unico: dict[str, str | None]  # curso -> profesor fijo o único candidato
    bloque: dict[str, Bloque]
    ocupa_grupo: dict[tuple[str, int, int], list[IntVar]]  # (grupo, día, orden) -> x
    ocupa_prof: dict[tuple[str, int, int], list[IntVar]]  # (profesor, día, átomo) -> t

    def x_de(self, bloque_id: str) -> list[tuple[int, int, IntVar]]:
        return [(d, o, xv) for (b, d, o), xv in self.x.items() if b == bloque_id]


def build_vars(m: cp_model.CpModel, inst: Instance) -> Vars:
    x, y, t, sel, acomodado, unico = {}, {}, {}, {}, {}, {}
    for c in inst.req.cursos:
        acomodado[c.id] = m.NewBoolVar(f"acomodado[{c.id}]")
        candidatos = [] if c.vacante else inst.candidatos(c.id)
        unico[c.id] = candidatos[0] if len(candidatos) == 1 else None
        if len(candidatos) > 1:
            for p in candidatos:
                y[(c.id, p)] = m.NewBoolVar(f"y[{c.id},{p}]")
        for k, bloques in enumerate(inst.bloques_por_curso[c.id]):
            sel[(c.id, k)] = m.NewBoolVar(f"sel[{c.id},{k}]")
            for b in bloques:
                for ini in inst.inicios_validos(b):
                    xv = m.NewBoolVar(f"x[{b.id},{ini.dia},{ini.orden}]")
                    x[(b.id, ini.dia, ini.orden)] = xv
                    if unico[c.id] is not None:
                        t[(b.id, unico[c.id], ini.dia, ini.orden)] = xv
                    elif len(candidatos) > 1:
                        for p in candidatos:
                            t[(b.id, p, ini.dia, ini.orden)] = m.NewBoolVar(
                                f"t[{b.id},{p},{ini.dia},{ini.orden}]"
                            )
    bloque = {b.id: b for b in inst.bloques}
    return Vars(
        x=x,
        y=y,
        t=t,
        sel=sel,
        acomodado=acomodado,
        profesor_unico=unico,
        bloque=bloque,
        ocupa_grupo=_ocupa_grupo(inst, x, bloque),
        ocupa_prof=_ocupa_prof(inst, t, bloque),
    )


def _ocupa_grupo(inst: Instance, x, bloque) -> dict[tuple[str, int, int], list[IntVar]]:
    """En una sincronía solo cuenta el líder, que ocupa los grupos de todos sus cursos."""
    out: dict[tuple[str, int, int], list[IntVar]] = defaultdict(list)
    for (b_id, d, o), xv in x.items():
        b = bloque[b_id]
        sinc = inst.cursos[b.curso].sincronia
        if sinc is not None and inst.sincronias[sinc][0] != b.curso:
            continue
        for g in inst.grupos_ocupados(b.curso):
            for k in range(b.duracion):
                out[(g, d, o + k)].append(xv)
    return dict(out)


def _ocupa_prof(inst: Instance, t, bloque) -> dict[tuple[str, int, int], list[IntVar]]:
    out: dict[tuple[str, int, int], list[IntVar]] = defaultdict(list)
    plantilla = {}
    for (b_id, p, d, o), tv in t.items():
        b = bloque[b_id]
        pl = plantilla.setdefault(b.curso, inst.curso_plantilla(b.curso))
        for k in range(b.duracion):
            for a in inst.atomos_cubiertos(pl, d, o + k):
                out[(p, d, a)].append(tv)
    return dict(out)
