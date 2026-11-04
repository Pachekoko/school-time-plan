"""Ubica las sesiones de un horario en sus bloques, franjas y átomos.

Lo comparten el validador y el puntaje; no depende del modelo CP-SAT.
"""

from dataclasses import dataclass
from typing import Literal

from solver.contract import SesionIn
from solver.instance import Bloque, Franja, Inicio, Instance

Motivo = Literal["bloque", "inicio"]


@dataclass(frozen=True)
class Colocada:
    sesion: SesionIn
    bloque: Bloque
    plantilla: str
    plantel: str
    franjas: tuple[Franja, ...]
    atomos: tuple[int, ...]

    @property
    def curso(self) -> str:
        return self.bloque.curso

    @property
    def dia(self) -> int:
        return self.franjas[0].dia

    @property
    def inicio_min(self) -> int:
        return self.franjas[0].inicio_min

    @property
    def fin_min(self) -> int:
        return self.franjas[-1].fin_min


def bloque_de(inst: Instance, sesion: SesionIn) -> Bloque | None:
    por_patron = inst.bloques_por_curso.get(sesion.curso)
    if por_patron is None or sesion.patron >= len(por_patron):
        return None
    bloques = por_patron[sesion.patron]
    return bloques[sesion.bloque] if sesion.bloque < len(bloques) else None


def colocar(
    inst: Instance, sesiones: list[SesionIn]
) -> tuple[list[Colocada], list[tuple[SesionIn, Motivo]]]:
    """Sesiones acomodadas en un inicio válido, y las acomodadas que no se pudieron ubicar."""
    colocadas: list[Colocada] = []
    rechazadas: list[tuple[SesionIn, Motivo]] = []
    for s in sesiones:
        if s.sin_acomodar:
            continue
        bloque = bloque_de(inst, s)
        if bloque is None:
            rechazadas.append((s, "bloque"))
            continue
        inicio = Inicio(dia=s.dia, orden=s.orden_inicio)
        if inicio not in inst.inicios_validos(bloque):
            rechazadas.append((s, "inicio"))
            continue
        franjas = tuple(inst.franjas_cubiertas(bloque, inicio))
        atomos = tuple(
            a for f in franjas for a in inst.atomos_cubiertos(f.plantilla, f.dia, f.orden)
        )
        colocadas.append(
            Colocada(
                sesion=s,
                bloque=bloque,
                plantilla=inst.curso_plantilla(bloque.curso),
                plantel=inst.curso_plantel(bloque.curso),
                franjas=franjas,
                atomos=atomos,
            )
        )
    return colocadas, rechazadas


def profesor_de(inst: Instance, c: Colocada) -> str | None:
    """Profesor que imparte la sesión; un curso vacante no tiene."""
    if inst.cursos[c.curso].vacante:
        return None
    return c.sesion.profesor
