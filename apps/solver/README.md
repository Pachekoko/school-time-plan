# Motor de horarios (`apps/solver`)

Servicio sin estado que recibe en JSON el problema de horarios de una escuela y devuelve un horario óptimo o, si no existe, **la explicación en español de qué reglas chocan**. También valida horarios hechos a mano.

Usa Google OR-Tools CP-SAT, FastAPI y Pydantic. El contrato (JSON Schema) se genera desde los modelos de `src/solver/contract.py` y vive en [`contracts/`](../../contracts).

## Correrlo

Requiere [uv](https://docs.astral.sh/uv/). En Manjaro: `sudo pacman -S uv`.

```bash
cd apps/solver
uv sync
uv run pytest -q -m "not slow"          # pruebas rápidas (~1 min)
SOLVER_TOKEN=dev uv run uvicorn solver.api:create_app --factory --port 8000
```

Con Docker:

```bash
docker build -t horarios-solver apps/solver
docker run --rm -e SOLVER_TOKEN=dev -p 8000:8000 horarios-solver
```

Sin `SOLVER_TOKEN` el servicio no arranca: no existe un modo sin token.

## Endpoints

| Método | Ruta | Cuerpo | Respuesta |
|---|---|---|---|
| `GET` | `/health` | — | `{"status": "ok", "schema_version": "1"}` (no pide token) |
| `POST` | `/solve` | `SolveRequest` | `SolveResponse`: `estado`, `sesiones`, `puntaje`, `desglose`, `diagnostico` |
| `POST` | `/validate` | `ValidateRequest` (problema + sesiones) | `ValidateResponse`: violaciones por regla y puntaje |

`/solve` y `/validate` exigen `Authorization: Bearer <SOLVER_TOKEN>`. Si el cuerpo no cumple el esquema o tiene referencias rotas, la respuesta es 422 con la lista de errores.

Ejemplo con la escuela demo:

```bash
curl -s -X POST localhost:8000/solve \
  -H "Authorization: Bearer dev" -H "Content-Type: application/json" \
  -d @contracts/fixtures/demo/prepa-horizonte.json
```

Estados posibles de `/solve`:

- `ok`: hay horario.
- `factible_parcial`: con `permitir_parcial`, quedaron bloques sin acomodar.
- `sin_solucion`: está demostrado que no hay horario; el `diagnostico` dice por qué.
- `tiempo_agotado`: no se encontró horario en el tiempo dado.

## Reglas

**Duras (siempre se cumplen):**

| | |
|---|---|
| H1 | Cada bloque se acomoda una vez, en franjas de clase seguidas, sin cruzar recesos (se elige uno de los repartos permitidos de la materia) |
| H2–H4 | Un grupo, un profesor o un espacio no están en dos lugares a la vez; el espacio es del tipo correcto y le caben los alumnos |
| H5–H7 | Profesor habilitado, disponible y dentro de sus horas por día y por semana |
| H8 | Los cursos de una sincronía (Inglés por nivel) van a la misma hora |
| H9 | Los bloques de una materia caen en días distintos |
| H10 | Las sesiones fijadas no se mueven |
| H11 | Tiempo de traslado entre planteles el mismo día |

**Configurables** (apagada, preferencia de peso 1 a 10, u obligatoria):

| | Regla | Default |
|---|---|---|
| S1 | Sin huecos para los grupos | obligatoria |
| S2 | Pocos huecos para profesores (`sin_huecos` la vuelve obligatoria para un profesor) | preferencia 7 |
| S3 | Menos días por profesor (no admite obligatoria) | preferencia 5 |
| S4 | Usar franjas preferentes | preferencia 4 |
| S5 | Repartir cada materia en la semana | preferencia 3 |
| S6 | Reglas de franja ("Actividad Física no en la primera hora") | preferencia 5 |
| S7 | Un docente, una materia por día en cada grupo | obligatoria |

## Cómo resuelve

1. **Prechecks** de milisegundos: horas contra disponibilidad, carga del grupo, espacios, bloques que no caben y sincronías sin profesores distintos. Si fallan, la respuesta es `sin_solucion` con un mensaje concreto y el modelo ni se construye.
2. **Fase A:** CP-SAT busca un horario que cumpla las reglas duras y las obligatorias, sin preferencias.
3. **Fase B:** optimiza las preferencias partiendo del horario de la fase A. Si no mejora a tiempo, se queda el de la fase A.
4. **Espacios:** con el horario fijo, un modelo pequeño por plantel y tipo elige el salón.
5. **Si no hay solución:** se repite el modelo con cada familia de reglas como *supuesto* de CP-SAT. Su núcleo (`SufficientAssumptionsForInfeasibility`) dice qué reglas chocan, en español.

El puntaje y el desglose siempre se calculan con `solver.scoring`, independiente del modelo. Las pruebas de propiedades comprueban que todo horario `ok` pase el validador independiente.

## Benchmark

`uv run python bench/run.py` corre la escuela demo ficticia "Prepa Horizonte" (`bench/generate_demo.py`):

- 3 planteles, 39 grupos (BG matutino y vespertino + BIS sabatino), 81 profesores por turno, 333 cursos y 735 bloques.
- Inglés por nivel con 9 sincronías.
- 15 profesores en dos planteles.

Resultado con 60 s y 8 workers en un Intel Core 5 120U (12 hilos):

| Estado | Tiempo | Modelo | Puntaje | Validador |
|---|---|---|---|---|
| `ok` (CP-SAT FEASIBLE) | 50.3 s | 74 962 variables, 111 057 restricciones | 223 230 | 0 violaciones |

| Regla | Crudo | Peso | Penalización |
|---|---|---|---|
| S1 | 0 | 5 | 0 |
| S2 | 7 300 min | 7 | 51 100 |
| S3 | 294 días-profesor | 5 | 147 000 |
| S4 | 227 franjas | 4 | 9 080 |
| S5 | 102 | 3 | 15 300 |
| S6 | 3 | 5 | 750 |
| S7 | 0 | 5 | 0 |

Notas:

- **Varianza:** con más de 1 worker, CP-SAT no es determinista; el puntaje cambia entre corridas.
- **Hardware:** con menos núcleos (por ejemplo, un servicio pequeño de Render), la misma demo necesita más tiempo límite.
- **Profesores por turno:** se probó con profesores que dan clase en matutino y vespertino (69 en total). La fase A tardó de 19 s a más de 60 s según la semilla, contra 10 a 18 s con profesores por turno. Por eso la demo usa profesores por turno.
- **Ajuste de la demo, para que sea factible:**
  - Cada materia del BIS sabatino tiene su propio profesor, porque S7 no deja que uno dé dos materias al mismo grupo el mismo sábado.
  - Inglés tiene al menos 2 profesores por plantel, porque los dos niveles van a la misma hora.
  - Ambos casos los detectan los prechecks.

## Caso real: carga horaria de UTEG, ciclo 27-A (anonimizado)

`bench/from_carga_xlsx.py` convierte la hoja `HORARIOS` de la carga horaria (columnas `GRUPO`, `MATERIA`, `HORAS`, `Docente`) en un problema del motor.

**Lo que hace la conversión:**

- `GENERICO` se vuelve curso vacante.
- Los repartos se derivan de las horas, con un máximo de 2 h por día: 2 h = 2 seguidas o 1 + 1; 3 h = 2 + 1; 4 h = 2 + 2.
- `|` final se lee como número romano (`Tutoria||` → `Tutoria II`).
- 1.º y 2.º semestre van de 8:00 a 14:00; 3.º a 6.º, de 8:00 a 15:00. Clases de 60 min.
- Las disponibilidades particulares vienen de `docentes.local.json`, que no se versiona.

En el repo solo está la versión anonimizada: [`contracts/fixtures/real/uteg-27a-anon.json`](../../contracts/fixtures/real/uteg-27a-anon.json).

```bash
uv run python bench/run.py --archivo ../../contracts/fixtures/real/uteg-27a-anon.json
```

**Datos:** 13 grupos, 12 docentes (11 cursos vacantes) y 124 cursos. Es un caso sin margen:

- Los 6 grupos de 1.º y 2.º tienen 30 h en 30 franjas.
- La docente F tiene 30 h y solo puede de 8:00 a 14:00.
- La docente N solo va de miércoles a viernes y sin huecos.

Resultado con 60 s y 8 workers:

| Estado | Tiempo | Validador | Huecos de grupos (S1) | Mismo docente, mismo día (S7) | Huecos de docentes (S2) |
|---|---|---|---|---|---|
| `ok` | 48.5 s | 0 violaciones | 0 | 0 | 60 min en total |
