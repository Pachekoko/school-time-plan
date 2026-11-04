"""Instancia normalizada del problema: índices, bloques, átomos de tiempo e inicios válidos.

Los "átomos" son los intervalos que resultan de cortar, por día, todas las franjas de clase
de todas las plantillas en sus bordes. Dos sesiones chocan en tiempo real si comparten un
átomo, aunque pertenezcan a plantillas distintas (matutino y vespertino).
"""

from dataclasses import dataclass, field
from typing import Literal

from solver.contract import (
    CursoIn,
    EspacioIn,
    GrupoIn,
    MateriaIn,
    PlantelIn,
    PlantillaIn,
    ProfesorIn,
    RefFranja,
    SolveRequest,
    minutos,
)
from solver.messages import DIAS

Disponibilidad = Literal["D", "P", "N"]


class InstanceError(Exception):
    """El problema no es coherente (ids repetidos, referencias rotas, patrones que no suman)."""

    def __init__(self, errores: list[str]):
        super().__init__("; ".join(errores))
        self.errores = errores


@dataclass(frozen=True)
class Franja:
    plantilla: str
    dia: int
    orden: int
    inicio_min: int
    fin_min: int
    es_clase: bool


@dataclass(frozen=True)
class Bloque:
    id: str  # f"{curso}#{patron}.{indice}"
    curso: str
    patron: int
    indice: int
    duracion: int


@dataclass(frozen=True)
class Inicio:
    dia: int
    orden: int


@dataclass
class Instance:
    req: SolveRequest
    franjas: dict[tuple[str, int, int], Franja]
    atomos: dict[int, list[tuple[int, int]]]
    bloques: list[Bloque]
    plantillas: dict[str, PlantillaIn]
    planteles: dict[str, PlantelIn]
    espacios: dict[str, EspacioIn]
    materias: dict[str, MateriaIn]
    grupos: dict[str, GrupoIn]
    profesores: dict[str, ProfesorIn]
    cursos: dict[str, CursoIn]
    bloques_por_curso: dict[str, list[list[Bloque]]]  # [patron][indice]
    _no_disponible: dict[str, set[tuple[str, int, int]]] = field(repr=False)
    _preferente: dict[str, set[tuple[str, int, int]]] = field(repr=False)
    _cache_inicios: dict[tuple[str, int], list[Inicio]] = field(default_factory=dict, repr=False)
    _cache_atomos: dict[tuple[str, int, int], list[int]] = field(default_factory=dict, repr=False)

    def curso_plantilla(self, curso_id: str) -> str:
        return self.grupos[self.cursos[curso_id].grupos[0]].plantilla

    def curso_plantel(self, curso_id: str) -> str:
        return self.grupos[self.cursos[curso_id].grupos[0]].plantel

    def curso_alumnos(self, curso_id: str) -> int:
        curso = self.cursos[curso_id]
        if curso.alumnos is not None:
            return curso.alumnos
        return sum(self.grupos[g].alumnos for g in curso.grupos)

    def inicios_validos(self, bloque: Bloque) -> list[Inicio]:
        """Inicios donde las `duracion` franjas con `orden` consecutivo existen y son de clase."""
        plantilla = self.curso_plantilla(bloque.curso)
        clave = (plantilla, bloque.duracion)
        if clave not in self._cache_inicios:
            inicios = []
            for (pl, dia, orden), franja in sorted(self.franjas.items()):
                if pl != plantilla or not franja.es_clase:
                    continue
                seguidas = (self.franjas.get((pl, dia, orden + k)) for k in range(bloque.duracion))
                if all(f is not None and f.es_clase for f in seguidas):
                    inicios.append(Inicio(dia=dia, orden=orden))
            self._cache_inicios[clave] = inicios
        return self._cache_inicios[clave]

    def franjas_cubiertas(self, bloque: Bloque, inicio: Inicio) -> list[Franja]:
        plantilla = self.curso_plantilla(bloque.curso)
        return [
            self.franjas[(plantilla, inicio.dia, inicio.orden + k)] for k in range(bloque.duracion)
        ]

    def atomos_cubiertos(self, plantilla: str, dia: int, orden: int) -> list[int]:
        """Índices de los átomos del día que caen dentro de la franja."""
        clave = (plantilla, dia, orden)
        if clave not in self._cache_atomos:
            f = self.franjas[clave]
            self._cache_atomos[clave] = [
                i
                for i, (a, b) in enumerate(self.atomos.get(dia, []))
                if a >= f.inicio_min and b <= f.fin_min
            ]
        return self._cache_atomos[clave]

    def candidatos(self, curso_id: str) -> list[str]:
        """El profesor fijo, o los habilitados para la materia en el plantel del curso."""
        curso = self.cursos[curso_id]
        if curso.vacante:
            return []
        if curso.profesor is not None:
            return [curso.profesor]
        plantel = self.curso_plantel(curso_id)
        return [
            p.id
            for p in self.req.profesores
            if curso.materia in p.materias and plantel in p.planteles
        ]

    def disponible(self, profesor_id: str, plantilla: str, dia: int, orden: int) -> Disponibilidad:
        clave = (plantilla, dia, orden)
        if clave in self._no_disponible[profesor_id]:
            return "N"
        if clave in self._preferente[profesor_id]:
            return "P"
        return "D"


def _indexar(nombre: str, items: list, errores: list[str]) -> dict:
    indice = {}
    for item in items:
        if item.id in indice:
            errores.append(f"id duplicado en {nombre}: {item.id}")
        indice[item.id] = item
    return indice


def _ref_franja(ref: RefFranja) -> str:
    return f"{ref.plantilla} {DIAS[ref.dia]} {ref.orden}"


def build_instance(req: SolveRequest) -> Instance:
    """Normaliza el request. Junta todos los errores de integridad antes de lanzar."""
    errores: list[str] = []
    plantillas = _indexar("plantillas", req.plantillas, errores)
    planteles = _indexar("planteles", req.planteles, errores)
    espacios = _indexar("espacios", req.espacios, errores)
    materias = _indexar("materias", req.materias, errores)
    grupos = _indexar("grupos", req.grupos, errores)
    profesores = _indexar("profesores", req.profesores, errores)
    cursos = _indexar("cursos", req.cursos, errores)

    franjas = _franjas(req, errores)

    def falta(donde: str, tipo: str, ref: str | None, indice: dict) -> None:
        if ref is not None and ref not in indice:
            errores.append(f"{donde}: {tipo} {ref} no existe")

    def falta_franja(donde: str, ref: RefFranja) -> None:
        if (ref.plantilla, ref.dia, ref.orden) not in franjas:
            errores.append(f"{donde}: la franja {_ref_franja(ref)} no existe")

    for e in req.espacios:
        falta(f"espacio {e.id}", "el plantel", e.plantel, planteles)
    for g in req.grupos:
        falta(f"grupo {g.id}", "el plantel", g.plantel, planteles)
        falta(f"grupo {g.id}", "la plantilla", g.plantilla, plantillas)
    for p in req.profesores:
        for pl in p.planteles:
            falta(f"profesor {p.id}", "el plantel", pl, planteles)
        for m in p.materias:
            falta(f"profesor {p.id}", "la materia", m, materias)
        for ref in [*p.no_disponible, *p.preferente]:
            falta_franja(f"profesor {p.id}", ref)
    for m in req.materias:
        for patron in m.patrones:
            if sum(patron) != m.horas_semana:
                errores.append(
                    f"materia {m.id}: el patrón {patron} suma {sum(patron)} "
                    f"y horas_semana es {m.horas_semana}"
                )
    for c in req.cursos:
        falta(f"curso {c.id}", "la materia", c.materia, materias)
        falta(f"curso {c.id}", "el profesor", c.profesor, profesores)
        for g in c.grupos:
            falta(f"curso {c.id}", "el grupo", g, grupos)
        suyos = [grupos[g] for g in c.grupos if g in grupos]
        if len({g.plantel for g in suyos}) > 1:
            errores.append(f"curso {c.id}: sus grupos tienen planteles distintos")
        if len({g.plantilla for g in suyos}) > 1:
            errores.append(f"curso {c.id}: sus grupos tienen plantillas distintas")
    for r in req.parametros.reglas.S6_restricciones:
        for m in r.materias:
            falta("regla S6", "la materia", m, materias)
        for ref in r.franjas:
            falta_franja("regla S6", ref)
    for s in req.fijadas:
        _revisar_fijada(s, cursos, materias, espacios, profesores, errores)

    if errores:
        raise InstanceError(errores)

    bloques_por_curso = {
        c.id: [
            [Bloque(f"{c.id}#{k}.{i}", c.id, k, i, dur) for i, dur in enumerate(patron)]
            for k, patron in enumerate(materias[c.materia].patrones)
        ]
        for c in req.cursos
    }
    return Instance(
        req=req,
        franjas=franjas,
        atomos=_atomos(franjas),
        bloques=[b for por_patron in bloques_por_curso.values() for p in por_patron for b in p],
        plantillas=plantillas,
        planteles=planteles,
        espacios=espacios,
        materias=materias,
        grupos=grupos,
        profesores=profesores,
        cursos=cursos,
        bloques_por_curso=bloques_por_curso,
        _no_disponible={
            p.id: {(r.plantilla, r.dia, r.orden) for r in p.no_disponible} for p in req.profesores
        },
        _preferente={
            p.id: {(r.plantilla, r.dia, r.orden) for r in p.preferente} for p in req.profesores
        },
    )


def _franjas(req: SolveRequest, errores: list[str]) -> dict[tuple[str, int, int], Franja]:
    franjas: dict[tuple[str, int, int], Franja] = {}
    for pl in req.plantillas:
        for f in pl.franjas:
            clave = (pl.id, f.dia, f.orden)
            if clave in franjas:
                errores.append(
                    f"plantilla {pl.id}: la franja {DIAS[f.dia]} {f.orden} está repetida"
                )
            franjas[clave] = Franja(
                pl.id, f.dia, f.orden, minutos(f.inicio), minutos(f.fin), f.tipo == "clase"
            )
    por_dia: dict[tuple[str, int], list[Franja]] = {}
    for f in franjas.values():
        por_dia.setdefault((f.plantilla, f.dia), []).append(f)
    for (pl, dia), lista in sorted(por_dia.items()):
        lista.sort(key=lambda f: f.orden)
        for antes, despues in zip(lista, lista[1:], strict=False):
            if despues.inicio_min < antes.fin_min:
                errores.append(
                    f"plantilla {pl}: las franjas {DIAS[dia]} {antes.orden} y "
                    f"{DIAS[dia]} {despues.orden} se traslapan"
                )
    return franjas


def _atomos(franjas: dict[tuple[str, int, int], Franja]) -> dict[int, list[tuple[int, int]]]:
    clase_por_dia: dict[int, list[Franja]] = {}
    for f in franjas.values():
        if f.es_clase:
            clase_por_dia.setdefault(f.dia, []).append(f)
    atomos = {}
    for dia, lista in sorted(clase_por_dia.items()):
        bordes = sorted({m for f in lista for m in (f.inicio_min, f.fin_min)})
        atomos[dia] = [
            (a, b)
            for a, b in zip(bordes, bordes[1:], strict=False)
            if any(f.inicio_min <= a and b <= f.fin_min for f in lista)
        ]
    return atomos


def _revisar_fijada(s, cursos, materias, espacios, profesores, errores: list[str]) -> None:
    if s.curso not in cursos:
        errores.append(f"fijada: el curso {s.curso} no existe")
        return
    materia = materias.get(cursos[s.curso].materia)
    if materia is not None:
        if s.patron >= len(materia.patrones):
            errores.append(f"fijada: el curso {s.curso} no tiene patrón {s.patron}")
        elif s.bloque >= len(materia.patrones[s.patron]):
            errores.append(
                f"fijada: el curso {s.curso} no tiene bloque {s.bloque} del patrón {s.patron}"
            )
    if s.espacio is not None and s.espacio not in espacios:
        errores.append(f"fijada: el espacio {s.espacio} no existe")
    if s.profesor is not None and s.profesor not in profesores:
        errores.append(f"fijada: el profesor {s.profesor} no existe")
