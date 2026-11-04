"""Conteos rápidos que detectan, antes de construir el modelo, problemas sin solución.

Ninguno da falsos positivos: cada conteo es una cota que cualquier horario válido cumple.
Las capacidades se cuentan en horas reales distintas (día, hora de inicio), para no sumar dos
veces plantillas que se traslapan.
"""

from collections import defaultdict

from solver.contract import Diagnostico, Entidad, SesionIn
from solver.instance import Franja, Instance
from solver.messages import DIAS, hora, nombre
from solver.validator import validate_partial


def run_prechecks(inst: Instance) -> list[Diagnostico]:
    out = [
        *_horas_profesores(inst),
        *_horas_grupos(inst),
        *_espacios(inst),
        *_sin_profesor(inst),
        *_sincronias(inst),
        *_bloques(inst),
        *_mismo_docente(inst),
        *_fijadas(inst),
    ]
    return sorted(out, key=lambda d: (d.codigo, d.entidades[0].id if d.entidades else ""))


def _d(codigo: str, regla: str, entidades: list[Entidad], mensaje: str) -> Diagnostico:
    return Diagnostico(codigo=codigo, regla=regla, entidades=entidades, mensaje=mensaje)


def _e(tipo: str, id: str) -> Entidad:
    return Entidad(tipo=tipo, id=id)


def _horas(inst: Instance, curso_id: str) -> int:
    return inst.materias[inst.cursos[curso_id].materia].horas_semana


def _clase(inst: Instance, plantilla: str) -> list[Franja]:
    return [f for f in inst.franjas.values() if f.plantilla == plantilla and f.es_clase]


def _horas_reales(franjas: list[Franja]) -> int:
    return len({(f.dia, f.inicio_min) for f in franjas})


def _asignados(inst: Instance) -> dict[str, list[str]]:
    """Cursos que seguro da cada profesor: los que tiene fijos y aquellos donde es el único
    candidato."""
    out: dict[str, list[str]] = defaultdict(list)
    for c in inst.req.cursos:
        if c.vacante:
            continue
        candidatos = inst.candidatos(c.id)
        if len(candidatos) == 1:
            out[candidatos[0]].append(c.id)
    return out


def _horas_profesores(inst: Instance) -> list[Diagnostico]:
    out = []
    asignados = _asignados(inst)
    for p in inst.profesores.values():
        fijos = asignados.get(p.id, [])
        if not fijos:
            continue
        requeridas = sum(_horas(inst, c) for c in fijos)
        plantillas = {inst.curso_plantilla(c) for c in fijos}
        libres = [
            f
            for pl in plantillas
            for f in _clase(inst, pl)
            if inst.disponible(p.id, pl, f.dia, f.orden) != "N"
        ]
        disponibles = _horas_reales(libres)
        if requeridas > disponibles:
            out.append(
                _d(
                    "PRE_HORAS_PROFESOR",
                    "H6",
                    [_e("profesor", p.id)],
                    f"{p.nombre} necesita {requeridas} h y solo tiene {disponibles} "
                    "franjas disponibles",
                )
            )
        if requeridas > p.horas_max:
            out.append(
                _d(
                    "PRE_HORAS_MAX",
                    "H7",
                    [_e("profesor", p.id)],
                    f"{p.nombre} tiene {requeridas} h asignadas y su máximo es {p.horas_max} h",
                )
            )
    return out


def _horas_grupos(inst: Instance) -> list[Diagnostico]:
    out = []
    for g in inst.grupos.values():
        carga: dict[tuple[str, str], int] = {}
        for c in inst.req.cursos:
            if g.id not in inst.grupos_ocupados(c.id):
                continue
            if c.sincronia is not None:
                carga[("S", c.sincronia)] = _horas(inst, inst.sincronias[c.sincronia][0])
            else:
                carga[("C", c.id)] = _horas(inst, c.id)
        requeridas, disponibles = sum(carga.values()), len(_clase(inst, g.plantilla))
        if requeridas > disponibles:
            out.append(
                _d(
                    "PRE_HORAS_GRUPO",
                    "H2",
                    [_e("grupo", g.id)],
                    f"El grupo {g.nombre} tiene {requeridas} h de carga y su plantilla solo "
                    f"tiene {disponibles} franjas de clase",
                )
            )
    return out


def _espacios(inst: Instance) -> list[Diagnostico]:
    out = []
    demanda: dict[tuple[str, str], list[str]] = defaultdict(list)
    for c in inst.req.cursos:
        tipo = inst.materias[c.materia].tipo_espacio
        plantel = inst.curso_plantel(c.id)
        demanda[(plantel, tipo)].append(c.id)
        alumnos = inst.curso_alumnos(c.id)
        if not any(
            e.plantel == plantel and e.tipo == tipo and e.capacidad >= alumnos
            for e in inst.espacios.values()
        ):
            out.append(
                _d(
                    "PRE_ESPACIO",
                    "H4",
                    [_e("plantel", plantel), _e("curso", c.id)],
                    f"{nombre(inst, 'plantel', plantel)}: ningún espacio de tipo {tipo} "
                    f"tiene capacidad para {alumnos} alumnos "
                    f"({nombre(inst, 'curso', c.id)})",
                )
            )
    for (plantel, tipo), cursos in demanda.items():
        n_espacios = sum(
            1 for e in inst.espacios.values() if e.plantel == plantel and e.tipo == tipo
        )
        if n_espacios == 0:
            continue  # ya lo reporta el chequeo de capacidad de cada curso
        plantillas = {inst.curso_plantilla(c) for c in cursos}
        disponibles = n_espacios * _horas_reales([f for pl in plantillas for f in _clase(inst, pl)])
        requeridas = sum(_horas(inst, c) for c in cursos)
        if requeridas > disponibles:
            out.append(
                _d(
                    "PRE_ESPACIO",
                    "H4",
                    [_e("plantel", plantel)],
                    f"{nombre(inst, 'plantel', plantel)}: se necesitan {requeridas} "
                    f"franjas de {tipo} y solo hay {disponibles}",
                )
            )
    return out


def _sin_profesor(inst: Instance) -> list[Diagnostico]:
    out = []
    for c in inst.req.cursos:
        if c.vacante or inst.candidatos(c.id):
            continue
        grupos = ", ".join(nombre(inst, "grupo", g) for g in c.grupos)
        out.append(
            _d(
                "PRE_SIN_PROFESOR",
                "H5",
                [_e("curso", c.id)],
                f"El curso {nombre(inst, 'materia', c.materia)} de {grupos} no tiene ningún "
                f"profesor habilitado en {nombre(inst, 'plantel', inst.curso_plantel(c.id))}",
            )
        )
    return out


def _sincronias(inst: Instance) -> list[Diagnostico]:
    out = []
    for sinc, cursos in inst.sincronias.items():
        plantillas = {inst.curso_plantilla(c) for c in cursos}
        patrones = {
            tuple(tuple(p) for p in inst.materias[inst.cursos[c].materia].patrones) for c in cursos
        }
        if len(plantillas) > 1 or len(patrones) > 1:
            out.append(
                _d(
                    "PRE_SINCRONIA",
                    "H8",
                    [_e("sincronia", sinc)],
                    f"La sincronía {sinc} mezcla cursos con plantillas o bloques distintos",
                )
            )
        opciones = [inst.candidatos(c) for c in cursos if not inst.cursos[c].vacante]
        alcanzan = _emparejamiento(opciones)
        if alcanzan < len(opciones):
            out.append(
                _d(
                    "PRE_SINCRONIA_PROFESORES",
                    "H3",
                    [_e("sincronia", sinc)],
                    f"La sincronía {sinc} necesita {len(opciones)} profesores distintos a la "
                    f"misma hora y sus candidatos solo alcanzan para {alcanzan}",
                )
            )
    return out


def _emparejamiento(opciones: list[list[str]]) -> int:
    """Tamaño del emparejamiento máximo curso → profesor distinto (caminos de aumento)."""
    pareja: dict[str, int] = {}

    def intenta(i: int, vistos: set[str]) -> bool:
        for p in opciones[i]:
            if p in vistos:
                continue
            vistos.add(p)
            if p not in pareja or intenta(pareja[p], vistos):
                pareja[p] = i
                return True
        return False

    return sum(intenta(i, set()) for i in range(len(opciones)))


def _bloques(inst: Instance) -> list[Diagnostico]:
    out = []
    largos: dict[tuple[str, str, int], list[str]] = defaultdict(list)
    for c in inst.req.cursos:
        por_patron = inst.bloques_por_curso[c.id]
        posibles = [
            bloques for bloques in por_patron if all(inst.inicios_validos(b) for b in bloques)
        ]
        if not posibles:
            largo = next(b for b in por_patron[0] if not inst.inicios_validos(b))
            largos[(c.materia, inst.curso_plantilla(c.id), largo.duracion)].append(c.id)
            continue
        dias = [{i.dia for b in bloques for i in inst.inicios_validos(b)} for bloques in posibles]
        if all(len(bloques) > len(d) for bloques, d in zip(posibles, dias, strict=True)):
            n, d = min(len(b) for b in posibles), max(len(d) for d in dias)
            out.append(
                _d(
                    "PRE_DIAS",
                    "H9",
                    [_e("curso", c.id)],
                    f"{nombre(inst, 'curso', c.id)} tiene {n} bloques y solo hay {d} días "
                    "con espacio",
                )
            )
    for (materia, plantilla, n), cursos in largos.items():
        out.append(
            _d(
                "PRE_BLOQUE_LARGO",
                "H1",
                [_e("curso", c) for c in cursos],
                f"{nombre(inst, 'materia', materia)} tiene un bloque de {n} h y la plantilla "
                f"{nombre(inst, 'plantilla', plantilla)} no tiene {n} franjas de clase "
                "seguidas",
            )
        )
    return out


def _mismo_docente(inst: Instance) -> list[Diagnostico]:
    if inst.req.parametros.reglas.S7.modo != "obligatoria":
        return []
    out = []
    asignados = _asignados(inst)
    for g in inst.grupos.values():
        dias = sum(1 for d in inst.dias(g.plantilla) if inst.franjas_clase(g.plantilla, d))
        for p in inst.profesores.values():
            cursos = [
                inst.cursos[c] for c in asignados.get(p.id, []) if g.id in inst.cursos[c].grupos
            ]
            if len(cursos) < 2:
                continue
            necesarios = sum(
                min(len(pt) for pt in inst.materias[c.materia].patrones) for c in cursos
            )
            if necesarios > dias:
                out.append(
                    _d(
                        "PRE_MISMO_DOCENTE",
                        "S7",
                        [_e("profesor", p.id), _e("grupo", g.id)],
                        f"{p.nombre} da {len(cursos)} materias a {g.nombre} y necesita "
                        f"{necesarios} días distintos; la semana solo tiene {dias}",
                    )
                )
    return out


def _donde(inst: Instance, s: SesionIn) -> str:
    plantilla = inst.curso_plantilla(s.curso)
    if (plantilla, s.dia, s.orden_inicio) in inst.franjas:
        return hora(inst, plantilla, s.dia, s.orden_inicio)
    return f"{DIAS[s.dia]} franja {s.orden_inicio}"


def _fijadas(inst: Instance) -> list[Diagnostico]:
    out, propios = [], set()
    for f in inst.req.fijadas:
        curso = inst.cursos[f.curso]
        grupos = ", ".join(nombre(inst, "grupo", g) for g in curso.grupos)
        lugar = f"{nombre(inst, 'materia', curso.materia)} ({grupos}, {_donde(inst, f)})"
        for v in validate_partial(inst, [f]):
            propios.add(v.mensaje)
            out.append(
                _d(
                    "PRE_FIJADA",
                    "H10",
                    [_e("sesion", f"{f.curso}#{f.patron}.{f.bloque}"), *v.entidades],
                    f"La sesión fijada de {lugar} no es válida: {v.mensaje}",
                )
            )
    vistos = set()
    for v in validate_partial(inst, list(inst.req.fijadas)):
        if v.mensaje in propios or v.mensaje in vistos:
            continue
        vistos.add(v.mensaje)
        out.append(
            _d(
                "PRE_FIJADA",
                "H10",
                v.entidades,
                f"Las sesiones fijadas no son compatibles: {v.mensaje}",
            )
        )
    return out
