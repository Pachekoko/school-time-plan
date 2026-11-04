"""Violaciones de las reglas duras H1–H11 (y de las S obligatorias) en un horario dado.

Es independiente del modelo CP-SAT: sirve para validar horarios editados a mano y para
comprobar que todo horario que devuelve el motor es válido.
"""

from collections import defaultdict
from collections.abc import Hashable

from solver.colocacion import Colocada, colocar, profesor_de
from solver.contract import Entidad, SesionIn, Violacion
from solver.instance import Instance
from solver.messages import DIAS, NOMBRES_REGLAS, hhmm, hora, nombre
from solver.scoring import REGLAS_S, crudos_por_entidad

Ranura = tuple[int, int]  # (día, átomo)


def validate_schedule(inst: Instance, sesiones: list[SesionIn]) -> list[Violacion]:
    colocadas, rechazadas = colocar(inst, sesiones)
    return [
        *_h1(inst, sesiones, colocadas, rechazadas),
        *_h2(inst, colocadas),
        *_h3(inst, colocadas),
        *_h4(inst, colocadas),
        *_h5(inst, colocadas),
        *_h6(inst, colocadas),
        *_h7(inst, colocadas),
        *_h8(inst, colocadas),
        *_h9(inst, colocadas),
        *_h10(inst, sesiones),
        *_h11(inst, colocadas),
        *_obligatorias(inst, sesiones),
    ]


def validate_partial(inst: Instance, sesiones: list[SesionIn]) -> list[Violacion]:
    """Solo lo que es imposible en cualquier horario que contenga estas sesiones (p. ej. las
    fijadas): no exige que estén todos los bloques, ni profesor, ni espacio, ni cargas."""
    colocadas, rechazadas = colocar(inst, sesiones)
    return [
        *_rechazos(inst, rechazadas),
        *_h2(inst, colocadas),
        *_h3(inst, colocadas),
        *_h4(inst, colocadas, exigir=False),
        *_h5(inst, colocadas, exigir=False),
        *_h6(inst, colocadas),
        *_h8(inst, colocadas),
        *_h9(inst, colocadas),
        *_h11(inst, colocadas),
    ]


def _e(tipo: str, id: str) -> Entidad:
    return Entidad(tipo=tipo, id=id)


def _v(regla: str, entidades: list[Entidad], mensaje: str) -> Violacion:
    return Violacion(regla=regla, entidades=entidades, mensaje=mensaje)


def _cuando(inst: Instance, ranura: Ranura) -> str:
    dia, atomo = ranura
    return f"{DIAS[dia]} {hhmm(inst.atomos[dia][atomo][0])}"


def _ranuras(c: Colocada) -> set[Ranura]:
    return {(c.dia, a) for a in c.atomos}


def _choques(unidades: dict[Hashable, set[Ranura]]) -> list[Ranura]:
    """Primera ranura compartida de cada par de unidades que se traslapan."""
    claves = list(unidades)
    out = []
    for i, a in enumerate(claves):
        for b in claves[i + 1 :]:
            comunes = unidades[a] & unidades[b]
            if comunes:
                out.append(min(comunes))
    return sorted(out)


def _h1(inst, sesiones, colocadas, rechazadas) -> list[Violacion]:
    return [*_rechazos(inst, rechazadas), *_completos(inst, sesiones, colocadas)]


def _rechazos(inst, rechazadas) -> list[Violacion]:
    out = []
    for s, motivo in rechazadas:
        curso = _e("curso", s.curso)
        if motivo == "bloque":
            out.append(
                _v(
                    "H1",
                    [curso],
                    f"La sesión {s.curso}#{s.patron}.{s.bloque} no corresponde a ningún bloque",
                )
            )
        else:
            plantilla = inst.curso_plantilla(s.curso)
            if (plantilla, s.dia, s.orden_inicio) in inst.franjas:
                cuando = hora(inst, plantilla, s.dia, s.orden_inicio)
            else:
                cuando = f"{DIAS[s.dia]} franja {s.orden_inicio}"
            out.append(
                _v("H1", [curso], f"{nombre(inst, 'curso', s.curso)} no puede empezar el {cuando}")
            )
    return out


def _completos(inst, sesiones, colocadas) -> list[Violacion]:
    out = []
    usados: dict[str, set[int]] = defaultdict(set)
    for s in sesiones:
        usados[s.curso].add(s.patron)
    puestos: dict[str, list[int]] = defaultdict(list)
    for c in colocadas:
        puestos[c.curso].append(c.bloque.indice)
    for curso_id, por_patron in inst.bloques_por_curso.items():
        curso, legible = _e("curso", curso_id), nombre(inst, "curso", curso_id)
        if len(usados[curso_id]) > 1:
            out.append(_v("H1", [curso], f"{legible} mezcla bloques de repartos distintos"))
            continue
        patron = min(usados[curso_id], default=0)
        if patron >= len(por_patron):
            continue
        bloques = por_patron[patron]
        for i in range(len(bloques)):
            n = puestos[curso_id].count(i)
            if n == 0:
                out.append(
                    _v(
                        "H1",
                        [curso],
                        f"A {legible} le falta acomodar el bloque {i + 1} de {len(bloques)}",
                    )
                )
            elif n > 1:
                out.append(_v("H1", [curso], f"{legible} tiene repetido el bloque {i + 1}"))
    return out


def _h2(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    unidades: dict[str, dict[Hashable, set[Ranura]]] = defaultdict(dict)
    for c in colocadas:
        sinc = inst.cursos[c.curso].sincronia
        if sinc is not None:
            clave = ("S", sinc, c.bloque.patron, c.bloque.indice, c.dia, c.sesion.orden_inicio)
        else:
            clave = ("C", c.bloque.id, c.dia, c.sesion.orden_inicio)
        for g in inst.grupos_ocupados(c.curso):
            unidades[g].setdefault(clave, set()).update(_ranuras(c))
    return [
        _v(
            "H2",
            [_e("grupo", g)],
            f"El grupo {nombre(inst, 'grupo', g)} tiene dos clases a la vez el {_cuando(inst, r)}",
        )
        for g, u in unidades.items()
        for r in _choques(u)
    ]


def _por_profesor(inst: Instance, colocadas: list[Colocada]) -> dict[str, list[Colocada]]:
    out: dict[str, list[Colocada]] = defaultdict(list)
    for c in colocadas:
        p = profesor_de(inst, c)
        if p is not None and p in inst.profesores:
            out[p].append(c)
    return out


def _h3(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    return [
        _v(
            "H3",
            [_e("profesor", p)],
            f"{nombre(inst, 'profesor', p)} tiene dos clases a la vez el {_cuando(inst, r)}",
        )
        for p, suyas in _por_profesor(inst, colocadas).items()
        for r in _choques({i: _ranuras(c) for i, c in enumerate(suyas)})
    ]


def _h4(inst: Instance, colocadas: list[Colocada], exigir: bool = True) -> list[Violacion]:
    out = []
    por_espacio: dict[str, dict[Hashable, set[Ranura]]] = defaultdict(dict)
    for i, c in enumerate(colocadas):
        curso_legible = nombre(inst, "curso", c.curso)
        curso = _e("curso", c.curso)
        e_id = c.sesion.espacio
        if e_id is None:
            if exigir:
                cuando = hora(inst, c.plantilla, c.dia, c.sesion.orden_inicio)
                out.append(_v("H4", [curso], f"{curso_legible} no tiene espacio el {cuando}"))
            continue
        espacio = inst.espacios.get(e_id)
        if espacio is None:
            out.append(_v("H4", [curso], f"El espacio {e_id} no existe ({curso_legible})"))
            continue
        ents = [_e("espacio", e_id), curso]
        tipo = inst.materias[inst.cursos[c.curso].materia].tipo_espacio
        if espacio.tipo != tipo:
            out.append(_v("H4", ents, f"{espacio.nombre} no es de tipo {tipo} ({curso_legible})"))
        if espacio.plantel != c.plantel:
            out.append(_v("H4", ents, f"{espacio.nombre} no está en el plantel de {curso_legible}"))
        alumnos = inst.curso_alumnos(c.curso)
        if espacio.capacidad < alumnos:
            out.append(
                _v(
                    "H4",
                    ents,
                    f"{espacio.nombre} tiene capacidad {espacio.capacidad} y "
                    f"{curso_legible} tiene {alumnos} alumnos",
                )
            )
        por_espacio[e_id][i] = _ranuras(c)
    for e_id, unidades in por_espacio.items():
        for r in _choques(unidades):
            out.append(
                _v(
                    "H4",
                    [_e("espacio", e_id)],
                    f"El espacio {nombre(inst, 'espacio', e_id)} tiene dos clases a la vez "
                    f"el {_cuando(inst, r)}",
                )
            )
    return out


def _h5(inst: Instance, colocadas: list[Colocada], exigir: bool = True) -> list[Violacion]:
    out = []
    por_curso: dict[str, list[Colocada]] = defaultdict(list)
    for c in colocadas:
        por_curso[c.curso].append(c)
    for curso_id, suyas in por_curso.items():
        curso, legible = _e("curso", curso_id), nombre(inst, "curso", curso_id)
        profesores = [c.sesion.profesor for c in suyas]
        if inst.cursos[curso_id].vacante:
            if any(p is not None for p in profesores):
                out.append(_v("H5", [curso], f"{legible} es vacante y no lleva profesor"))
            continue
        if exigir and None in profesores:
            out.append(_v("H5", [curso], f"{legible} no tiene profesor"))
        candidatos = inst.candidatos(curso_id)
        for p in sorted({p for p in profesores if p is not None}):
            if p not in inst.profesores:
                out.append(_v("H5", [curso], f"El profesor {p} no existe ({legible})"))
            elif p not in candidatos:
                out.append(
                    _v(
                        "H5",
                        [_e("profesor", p), curso],
                        f"{nombre(inst, 'profesor', p)} no está habilitado para {legible}",
                    )
                )
        if len({p for p in profesores if p is not None}) > 1:
            out.append(_v("H5", [curso], f"{legible} tiene bloques con profesores distintos"))
    return out


def _h6(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    return [
        _v(
            "H6",
            [_e("profesor", p)],
            f"{nombre(inst, 'profesor', p)} no está disponible el "
            f"{hora(inst, f.plantilla, f.dia, f.orden)}",
        )
        for p, suyas in _por_profesor(inst, colocadas).items()
        for c in suyas
        for f in c.franjas
        if inst.disponible(p, f.plantilla, f.dia, f.orden) == "N"
    ]


def _h7(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    out = []
    por_profesor = _por_profesor(inst, colocadas)
    for p, prof in inst.profesores.items():
        legible, ents = prof.nombre, [_e("profesor", p)]
        por_dia: dict[int, int] = defaultdict(int)
        for c in por_profesor.get(p, []):
            por_dia[c.dia] += c.bloque.duracion
        for dia, n in sorted(por_dia.items()):
            if n > prof.max_dia:
                out.append(
                    _v(
                        "H7",
                        ents,
                        f"{legible} tiene {n} h el {DIAS[dia]} y su máximo "
                        f"por día es {prof.max_dia}",
                    )
                )
        total = sum(por_dia.values())
        if total < prof.horas_min:
            out.append(
                _v(
                    "H7",
                    ents,
                    f"{legible} tiene {total} h a la semana y su mínimo es {prof.horas_min}",
                )
            )
        if total > prof.horas_max:
            out.append(
                _v(
                    "H7",
                    ents,
                    f"{legible} tiene {total} h a la semana y su máximo es {prof.horas_max}",
                )
            )
    return out


def _h8(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    out = []
    for sinc, cursos in inst.sincronias.items():
        suyas = [c for c in colocadas if c.curso in cursos]
        patrones = {c.bloque.patron for c in suyas}
        horas: dict[tuple[int, int], set[tuple[int, int]]] = defaultdict(set)
        for c in suyas:
            horas[(c.bloque.patron, c.bloque.indice)].add((c.dia, c.sesion.orden_inicio))
        if len(patrones) > 1 or any(len(h) > 1 for h in horas.values()):
            out.append(
                _v(
                    "H8",
                    [_e("sincronia", sinc)],
                    f"La sincronía {sinc} no tiene a todos sus cursos a la misma hora",
                )
            )
    return out


def _h9(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    por_dia: dict[tuple[str, int], int] = defaultdict(int)
    for c in colocadas:
        por_dia[(c.curso, c.dia)] += 1
    return [
        _v(
            "H9",
            [_e("curso", curso)],
            f"{nombre(inst, 'curso', curso)} tiene dos bloques el {DIAS[dia]}",
        )
        for (curso, dia), n in sorted(por_dia.items())
        if n > 1
    ]


def _h10(inst: Instance, sesiones: list[SesionIn]) -> list[Violacion]:
    out = []
    for f in inst.req.fijadas:
        en_su_lugar = any(
            not s.sin_acomodar
            and (s.curso, s.patron, s.bloque) == (f.curso, f.patron, f.bloque)
            and (s.dia, s.orden_inicio) == (f.dia, f.orden_inicio)
            and (f.profesor is None or s.profesor == f.profesor)
            and (f.espacio is None or s.espacio == f.espacio)
            for s in sesiones
        )
        if not en_su_lugar:
            plantilla = inst.curso_plantilla(f.curso)
            if (plantilla, f.dia, f.orden_inicio) in inst.franjas:
                cuando = hora(inst, plantilla, f.dia, f.orden_inicio)
            else:
                cuando = f"{DIAS[f.dia]} franja {f.orden_inicio}"
            out.append(
                _v(
                    "H10",
                    [_e("sesion", f"{f.curso}#{f.patron}.{f.bloque}")],
                    f"La sesión fijada de {nombre(inst, 'curso', f.curso)} no está en su "
                    f"lugar ({cuando})",
                )
            )
    return out


def _h11(inst: Instance, colocadas: list[Colocada]) -> list[Violacion]:
    out = []
    traslado = inst.req.parametros.minutos_traslado
    for p, suyas in _por_profesor(inst, colocadas).items():
        vistos = set()
        for a in suyas:
            for b in suyas:
                if a.dia != b.dia or a.plantel == b.plantel or a.inicio_min > b.inicio_min:
                    continue
                if a.inicio_min == b.inicio_min and a.plantel > b.plantel:
                    continue
                clave = (a.dia, a.plantel, b.plantel)
                if b.inicio_min - a.fin_min < traslado and clave not in vistos:
                    vistos.add(clave)
                    out.append(
                        _v(
                            "H11",
                            [_e("profesor", p)],
                            f"{nombre(inst, 'profesor', p)} no tiene tiempo de traslado "
                            f"entre {nombre(inst, 'plantel', a.plantel)} y "
                            f"{nombre(inst, 'plantel', b.plantel)} el {DIAS[a.dia]}",
                        )
                    )
    return out


def _obligatorias(inst: Instance, sesiones: list[SesionIn]) -> list[Violacion]:
    reglas = inst.req.parametros.reglas
    crudos = crudos_por_entidad(inst, sesiones)
    out = []
    for regla in REGLAS_S:
        if getattr(reglas, regla).modo != "obligatoria":
            continue
        for clave, n in sorted(crudos[regla].items()):
            if n > 0:
                ents = [_e(t, i) for t, i in clave]
                quien = " en ".join(nombre(inst, t, i) for t, i in reversed(clave))
                out.append(
                    _v(
                        regla,
                        ents,
                        f"La regla obligatoria «{NOMBRES_REGLAS[regla]}» no se cumple para {quien}",
                    )
                )
    if reglas.S2.modo != "obligatoria":
        for clave, minutos in sorted(crudos["S2"].items()):
            ((_, p),) = clave
            if minutos > 0 and inst.profesores[p].sin_huecos:
                out.append(
                    _v(
                        "S2",
                        [_e("profesor", p)],
                        f"{nombre(inst, 'profesor', p)} debe tener sus clases sin huecos "
                        f"y tiene {minutos} min de hueco",
                    )
                )
    return out
