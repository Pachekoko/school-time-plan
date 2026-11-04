"""Formato legible de entidades y horas para los mensajes al usuario."""

from typing import TYPE_CHECKING

if TYPE_CHECKING:
    from solver.instance import Instance

DIAS = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"]


def hhmm(minutos: int) -> str:
    return f"{minutos // 60:02d}:{minutos % 60:02d}"


def hora(inst: "Instance", plantilla: str, dia: int, orden: int) -> str:
    """`"Lun 07:00"`: día y hora de inicio de la franja."""
    return f"{DIAS[dia]} {hhmm(inst.franjas[(plantilla, dia, orden)].inicio_min)}"


def nombre(inst: "Instance", tipo: str, id: str) -> str:
    """Nombre legible de una entidad; si no existe, el propio id."""
    if tipo == "curso" and id in inst.cursos:
        curso = inst.cursos[id]
        grupos = ", ".join(nombre(inst, "grupo", g) for g in curso.grupos)
        return f"{nombre(inst, 'materia', curso.materia)} ({grupos})"
    coleccion = {
        "grupo": inst.grupos,
        "profesor": inst.profesores,
        "plantel": inst.planteles,
        "espacio": inst.espacios,
        "materia": inst.materias,
        "plantilla": inst.plantillas,
    }.get(tipo, {})
    entidad = coleccion.get(id)
    return entidad.nombre if entidad is not None else id
