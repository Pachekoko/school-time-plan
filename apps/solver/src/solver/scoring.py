"""Penalizaciones S1–S7 de un horario dado, independientes del modelo CP-SAT.

`penalizacion = crudo * peso * FACTORES[regla]`. El modelo (Tarea 6) reproduce estos mismos
`crudo`, y `solve()` reporta siempre el puntaje calculado aquí.
"""

from collections import defaultdict

from solver.colocacion import Colocada, colocar, profesor_de
from solver.contract import PenalizacionRegla, RestriccionFranja, SesionIn
from solver.instance import Instance

REGLAS_S = ["S1", "S2", "S3", "S4", "S5", "S6", "S7"]
FACTORES = {"S1": 50, "S2": 1, "S3": 100, "S4": 10, "S5": 50, "S6": 50, "S7": 100}

# Llave de entidad: tupla de (tipo, id); S7 usa ((grupo), (profesor)).
Clave = tuple[tuple[str, str], ...]


def score(inst: Instance, sesiones: list[SesionIn]) -> tuple[int, dict[str, PenalizacionRegla]]:
    """Puntaje total y desglose de las reglas que no están `apagada`."""
    crudos = crudos_por_entidad(inst, sesiones)
    reglas = inst.req.parametros.reglas
    desglose: dict[str, PenalizacionRegla] = {}
    for regla in REGLAS_S:
        config = getattr(reglas, regla)
        if config.modo == "apagada":
            continue
        crudo = sum(crudos[regla].values())
        desglose[regla] = PenalizacionRegla(
            crudo=crudo, peso=config.peso, penalizacion=crudo * config.peso * FACTORES[regla]
        )
    return sum(p.penalizacion for p in desglose.values()), desglose


def crudos_por_entidad(inst: Instance, sesiones: list[SesionIn]) -> dict[str, dict[Clave, int]]:
    """`crudo` de cada regla S, por entidad, sin importar su modo."""
    colocadas, _ = colocar(inst, sesiones)
    por_profesor: dict[str, list[Colocada]] = defaultdict(list)
    for c in colocadas:
        p = profesor_de(inst, c)
        if p is not None and p in inst.profesores:
            por_profesor[p].append(c)
    return {
        "S1": _huecos_grupos(inst, colocadas),
        "S2": _huecos_profesores(inst, por_profesor),
        "S3": {(("profesor", p),): len({c.dia for c in cs}) for p, cs in por_profesor.items()},
        "S4": _no_preferentes(inst, por_profesor),
        "S5": _dias_consecutivos(colocadas),
        "S6": _reglas_de_franja(inst, colocadas),
        "S7": _mismo_docente(inst, por_profesor),
    }


def huecos(ocupados: list[bool], peso: list[int] | None = None) -> int:
    """Suma (con `peso` por posición) de las posiciones libres entre la primera y la última
    ocupada."""
    marcados = [i for i, o in enumerate(ocupados) if o]
    if not marcados:
        return 0
    peso = peso or [1] * len(ocupados)
    return sum(peso[i] for i in range(marcados[0], marcados[-1]) if not ocupados[i])


def _huecos_grupos(inst: Instance, colocadas: list[Colocada]) -> dict[Clave, int]:
    ocupado: dict[str, set[tuple[int, int]]] = defaultdict(set)
    for c in colocadas:
        for g in inst.grupos_ocupados(c.curso):
            ocupado[g].update((f.dia, f.orden) for f in c.franjas)
    out: dict[Clave, int] = {}
    for g, grupo in inst.grupos.items():
        total = 0
        for dia in inst.dias(grupo.plantilla):
            franjas = inst.franjas_clase(grupo.plantilla, dia)
            total += huecos([(dia, f.orden) in ocupado[g] for f in franjas])
        out[(("grupo", g),)] = total
    return out


def plantillas_de_profesor(inst: Instance, profesor: str, suyas: list[Colocada]) -> set[str]:
    return {inst.curso_plantilla(c) for c in inst.cursos_de_profesor(profesor)} | {
        c.plantilla for c in suyas
    }


def _huecos_profesores(inst: Instance, por_profesor: dict[str, list[Colocada]]) -> dict[Clave, int]:
    out: dict[Clave, int] = {}
    for p, suyas in por_profesor.items():
        plantillas = plantillas_de_profesor(inst, p, suyas)
        total = 0
        for dia, atomos_dia in inst.atomos.items():
            ocupados = {a for c in suyas if c.dia == dia for a in c.atomos}
            relevantes = sorted(
                {a for pl in plantillas for a in inst.atomos_de_plantilla(pl, dia)} | ocupados
            )
            duracion = [atomos_dia[a][1] - atomos_dia[a][0] for a in relevantes]
            total += huecos([a in ocupados for a in relevantes], duracion)
        out[(("profesor", p),)] = total
    return out


def _no_preferentes(inst: Instance, por_profesor: dict[str, list[Colocada]]) -> dict[Clave, int]:
    out: dict[Clave, int] = {}
    for p, suyas in por_profesor.items():
        if not inst.profesores[p].preferente:
            continue
        out[(("profesor", p),)] = sum(
            1
            for c in suyas
            for f in c.franjas
            if inst.disponible(p, f.plantilla, f.dia, f.orden) == "D"
        )
    return out


def _dias_consecutivos(colocadas: list[Colocada]) -> dict[Clave, int]:
    dias: dict[str, set[int]] = defaultdict(set)
    for c in colocadas:
        dias[c.curso].add(c.dia)
    return {(("curso", k),): sum(1 for d in ds if d + 1 in ds) for k, ds in dias.items()}


def aplica(r: RestriccionFranja, materia: str, tipo_espacio: str) -> bool:
    if not r.materias and not r.tipos_espacio:
        return True
    return materia in r.materias or tipo_espacio in r.tipos_espacio


def _reglas_de_franja(inst: Instance, colocadas: list[Colocada]) -> dict[Clave, int]:
    restricciones = [
        (r, {(f.plantilla, f.dia, f.orden) for f in r.franjas})
        for r in inst.req.parametros.reglas.S6_restricciones
    ]
    out: dict[Clave, int] = defaultdict(int)
    for c in colocadas:
        materia = inst.materias[inst.cursos[c.curso].materia]
        for f in c.franjas:
            if any(
                aplica(r, materia.id, materia.tipo_espacio)
                and (f.plantilla, f.dia, f.orden) in refs
                for r, refs in restricciones
            ):
                out[(("curso", c.curso),)] += 1
    return dict(out)


def _mismo_docente(inst: Instance, por_profesor: dict[str, list[Colocada]]) -> dict[Clave, int]:
    cursos: dict[tuple[str, str, int], set[str]] = defaultdict(set)
    for p, suyas in por_profesor.items():
        for c in suyas:
            for g in inst.cursos[c.curso].grupos:
                cursos[(g, p, c.dia)].add(c.curso)
    out: dict[Clave, int] = defaultdict(int)
    for (g, p, _), cs in cursos.items():
        out[(("grupo", g), ("profesor", p))] += max(0, len(cs) - 1)
    return dict(out)
