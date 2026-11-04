"""Traduce el núcleo de supuestos de CP-SAT a mensajes en español."""

from solver.contract import Diagnostico
from solver.instance import Instance
from solver.messages import DIAS, NOMBRES_REGLAS, hora, nombre
from solver.model.hard import Supuesto, Supuestos


def explain_core(inst: Instance, sup: Supuestos, core: list[int]) -> list[Diagnostico]:
    """Un diagnóstico por supuesto del núcleo (sin repetir), ordenado por regla y entidad."""
    vistos, out = set(), []
    for i in core:
        s = sup.por_indice.get(i)
        if s is None:
            continue
        clave = (s.regla, tuple((e.tipo, e.id) for e in s.entidades))
        if clave in vistos:
            continue
        vistos.add(clave)
        out.append(
            Diagnostico(
                codigo="NUCLEO", regla=s.regla, entidades=s.entidades, mensaje=_mensaje(inst, s)
            )
        )
    return sorted(out, key=lambda d: (d.regla, [e.id for e in d.entidades]))


def _mensaje(inst: Instance, s: Supuesto) -> str:
    ent = s.entidades[0]
    quien = nombre(inst, ent.tipo, ent.id)
    if s.regla == "H2":
        return f"El grupo {quien} no cabe en su semana con las demás reglas"
    if s.regla == "H3":
        return f"{quien}: sus cursos no caben sin empalmarse"
    if s.regla == "H4":
        return f"{quien}: no alcanzan los espacios de tipo {s.datos.get('tipo')}"
    if s.regla == "H6":
        return f"{quien}: su disponibilidad no alcanza para sus cursos con las demás reglas"
    if s.regla == "H7":
        p = inst.profesores[ent.id]
        return (
            f"{quien}: no se pueden cumplir sus límites de horas "
            f"({p.horas_min}–{p.horas_max} por semana, {p.max_dia} por día)"
        )
    if s.regla == "H8":
        return f"La sincronía {ent.id} no encuentra una hora común"
    if s.regla == "H9":
        return f"{quien} no tiene días distintos suficientes"
    if s.regla == "H10":
        return f"La sesión fijada de {_fijada(inst, ent.id)} choca con el resto"
    if s.regla == "H11":
        return f"{quien}: no hay tiempo de traslado entre sus planteles"
    if s.regla in NOMBRES_REGLAS:
        entidad = " en ".join(nombre(inst, e.tipo, e.id) for e in reversed(s.entidades))
        return (
            f"La regla obligatoria «{NOMBRES_REGLAS[s.regla]}» no se puede cumplir para {entidad}"
        )
    return f"La regla {s.regla} no se puede cumplir para {quien}"


def _fijada(inst: Instance, sesion_id: str) -> str:
    """`"Matemáticas (1A, Mar 08:40)"` para el id `"C1#0.0"` de una sesión fijada."""
    curso_id, _, resto = sesion_id.rpartition("#")  # el id del curso puede traer "#"
    patron, _, bloque = resto.partition(".")
    f = next(
        f
        for f in inst.req.fijadas
        if (f.curso, str(f.patron), str(f.bloque)) == (curso_id, patron, bloque)
    )
    curso = inst.cursos[curso_id]
    grupos = ", ".join(nombre(inst, "grupo", g) for g in curso.grupos)
    plantilla = inst.curso_plantilla(curso_id)
    if (plantilla, f.dia, f.orden_inicio) in inst.franjas:
        cuando = hora(inst, plantilla, f.dia, f.orden_inicio)
    else:
        cuando = f"{DIAS[f.dia]} franja {f.orden_inicio}"
    return f"{nombre(inst, 'materia', curso.materia)} ({grupos}, {cuando})"
