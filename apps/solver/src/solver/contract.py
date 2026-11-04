"""Contrato JSON entre la web y el motor.

Estos modelos son la fuente de verdad: `solver.export_schema` genera con ellos los
JSON Schema de `contracts/`.
"""

from typing import Annotated, Literal

from pydantic import BaseModel, ConfigDict, Field, model_validator

SCHEMA_VERSION = "1"

TipoEspacio = Literal["aula", "laboratorio", "computo", "cancha", "cocina", "taller"]
ModoRegla = Literal["apagada", "preferencia", "obligatoria"]
Dia = Annotated[int, Field(ge=0, le=6)]
Hora = Annotated[str, Field(pattern=r"^([01]\d|2[0-3]):[0-5]\d$")]
Positivo = Annotated[int, Field(ge=1)]


def minutos(hora: str) -> int:
    h, m = hora.split(":")
    return int(h) * 60 + int(m)


class _Modelo(BaseModel):
    model_config = ConfigDict(extra="forbid")


# --- Entrada ---------------------------------------------------------------


class FranjaIn(_Modelo):
    dia: Dia
    orden: int = Field(ge=0)
    inicio: Hora
    fin: Hora
    tipo: Literal["clase", "receso"]

    @model_validator(mode="after")
    def _fin_despues_de_inicio(self) -> "FranjaIn":
        if minutos(self.fin) <= minutos(self.inicio):
            raise ValueError(
                f"la franja termina ({self.fin}) antes de empezar ({self.inicio}); "
                "fin debe ser posterior a inicio"
            )
        return self


class PlantillaIn(_Modelo):
    id: str
    nombre: str
    franjas: list[FranjaIn]


class PlantelIn(_Modelo):
    id: str
    nombre: str


class EspacioIn(_Modelo):
    id: str
    plantel: str
    nombre: str
    tipo: TipoEspacio
    capacidad: Positivo


class MateriaIn(_Modelo):
    id: str
    nombre: str
    horas_semana: Positivo
    patrones: list[Annotated[list[Positivo], Field(min_length=1)]] = Field(min_length=1)
    tipo_espacio: TipoEspacio = "aula"


class GrupoIn(_Modelo):
    id: str
    nombre: str
    plantel: str
    plantilla: str
    alumnos: Positivo


class RefFranja(_Modelo):
    plantilla: str
    dia: Dia
    orden: int = Field(ge=0)


class ProfesorIn(_Modelo):
    id: str
    nombre: str
    planteles: list[str]
    materias: list[str]
    horas_min: int = Field(default=0, ge=0)
    horas_max: int = Field(ge=0)
    max_dia: Positivo
    no_disponible: list[RefFranja] = []
    preferente: list[RefFranja] = []
    sin_huecos: bool = False

    @model_validator(mode="after")
    def _rango_de_horas(self) -> "ProfesorIn":
        if self.horas_min > self.horas_max:
            raise ValueError(
                f"horas_min ({self.horas_min}) es mayor que horas_max ({self.horas_max})"
            )
        return self


class CursoIn(_Modelo):
    id: str
    materia: str
    grupos: list[str] = Field(min_length=1)
    profesor: str | None = None
    sincronia: str | None = None
    alumnos: Positivo | None = None
    vacante: bool = False

    @model_validator(mode="after")
    def _vacante_sin_profesor(self) -> "CursoIn":
        if self.vacante and self.profesor is not None:
            raise ValueError("un curso vacante no puede tener profesor")
        return self


class SesionIn(_Modelo):
    curso: str
    patron: int = Field(default=0, ge=0)
    bloque: int = Field(ge=0)
    dia: Dia | None = None
    orden_inicio: int | None = Field(default=None, ge=0)
    espacio: str | None = None
    profesor: str | None = None
    fijada: bool = False
    sin_acomodar: bool = False

    @model_validator(mode="after")
    def _hora_si_esta_acomodada(self) -> "SesionIn":
        if not self.sin_acomodar and (self.dia is None or self.orden_inicio is None):
            raise ValueError("una sesión acomodada necesita dia y orden_inicio")
        return self


class ReglaConfig(_Modelo):
    modo: ModoRegla
    peso: int = Field(default=5, ge=1, le=10)


class RestriccionFranja(_Modelo):
    materias: list[str] = []
    tipos_espacio: list[TipoEspacio] = []
    franjas: list[RefFranja]


def _regla(modo: ModoRegla, peso: int = 5):
    return Field(default_factory=lambda: ReglaConfig(modo=modo, peso=peso))


class ReglasConfig(_Modelo):
    S1: ReglaConfig = _regla("obligatoria")
    S2: ReglaConfig = _regla("preferencia", 7)
    S3: ReglaConfig = _regla("preferencia", 5)
    S4: ReglaConfig = _regla("preferencia", 4)
    S5: ReglaConfig = _regla("preferencia", 3)
    S6: ReglaConfig = _regla("preferencia", 5)
    S6_restricciones: list[RestriccionFranja] = []
    S7: ReglaConfig = _regla("obligatoria")


class Parametros(_Modelo):
    tiempo_limite_s: float = Field(default=60, ge=1, le=300)
    semilla: int = Field(default=0, ge=0, le=2**31 - 1)
    num_workers: int = Field(default=8, ge=1, le=16)
    permitir_parcial: bool = False
    minutos_traslado: int = Field(default=50, ge=0)
    reglas: ReglasConfig = Field(default_factory=ReglasConfig)


class SolveRequest(_Modelo):
    schema_version: Literal["1"]
    parametros: Parametros = Field(default_factory=Parametros)
    plantillas: list[PlantillaIn]
    planteles: list[PlantelIn]
    espacios: list[EspacioIn]
    materias: list[MateriaIn]
    grupos: list[GrupoIn]
    profesores: list[ProfesorIn]
    cursos: list[CursoIn]
    fijadas: list[SesionIn] = []


# --- Salida ----------------------------------------------------------------

TipoEntidad = Literal[
    "grupo",
    "profesor",
    "curso",
    "plantel",
    "espacio",
    "sincronia",
    "sesion",
    "materia",
    "plantilla",
]


class Entidad(_Modelo):
    tipo: TipoEntidad
    id: str


class Diagnostico(_Modelo):
    codigo: str
    regla: str
    entidades: list[Entidad]
    mensaje: str


class PenalizacionRegla(_Modelo):
    crudo: int
    peso: int
    penalizacion: int


class Estadisticas(_Modelo):
    tiempo_s: float
    estado_cp_sat: str
    variables: int
    restricciones: int


class SolveResponse(_Modelo):
    schema_version: Literal["1"] = SCHEMA_VERSION
    estado: Literal["ok", "factible_parcial", "sin_solucion", "tiempo_agotado"]
    sesiones: list[SesionIn]
    puntaje: int
    desglose: dict[str, PenalizacionRegla]
    diagnostico: list[Diagnostico]
    estadisticas: Estadisticas


class ValidateRequest(_Modelo):
    schema_version: Literal["1"]
    problema: SolveRequest
    sesiones: list[SesionIn]


class Violacion(_Modelo):
    regla: str
    entidades: list[Entidad]
    mensaje: str


class ValidateResponse(_Modelo):
    schema_version: Literal["1"] = SCHEMA_VERSION
    violaciones: list[Violacion]
    desglose: dict[str, PenalizacionRegla]
    puntaje: int
