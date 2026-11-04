# School Time Plan — Generador de horarios para bachillerato

- **Fecha:** 2026-11-04
- **Estado:** diseño aprobado por secciones, pendiente de revisión del spec escrito
- **Repo:** https://github.com/Pachekoko/school-time-plan

## 1. Propósito y criterios de éxito

Proyecto de **portafolio** que muestra dos cosas al mismo nivel:

1. Un **motor de optimización serio** (Google OR-Tools CP-SAT) que resuelve las restricciones reales de un bachillerato privado mexicano y **explica en español por qué** un horario no tiene solución.
2. Una **aplicación web completa y multi-escuela**: captura de datos, importación/exportación con Excel, generación de un borrador, edición interactiva arrastrando bloques y publicación para profesores y alumnos.

Caso de referencia: los bachilleratos incorporados a la UdeG y a la SEJ en Jalisco (tipo UTEG). La demo usa una **escuela ficticia** ("Prepa Horizonte"); no usa la marca, logos ni datos de UTEG.

**El proyecto está terminado cuando:**

- Un reclutador entra a la demo pública en Render, elige "Entrar como admin", genera un horario de la escuela demo en menos de 60 s, arrastra un bloque, lo fija, re-optimiza y publica; luego entra como profesor y como alumno y ve su horario.
- Si fuerza un caso imposible (ej. quita disponibilidad a un profesor), el sistema le dice qué reglas chocan, con nombres y números.
- Un coordinador puede bajar la plantilla Excel, llenarla, importarla, y exportar de vuelta lo mismo.
- `docker compose up` levanta todo en local y CI pasa en cada PR.

## 2. Contexto de la investigación

Resumen de lo que dio forma al diseño (investigación web del 2026-11-04).

**UTEG bachillerato** ([oferta](https://www.uteg.edu.mx/oferta-educativa/bachillerato), [RVOE](https://bedu-staging-assets.s3.us-west-2.amazonaws.com/RVOES_UTEG_82164d9ec2.pdf)):

- 8 planteles en la ZMG. Bachillerato General de 6 semestres incorporado a la UdeG (variantes Proulex y Gastronomía) y Bachillerato Intensivo Semiescolarizado / BIS Sabatino incorporado a la SEJ, organizado por módulos.
- Hay turno matutino y vespertino. No se publican horas exactas ni materias por semestre.
- El plan UdeG 2025 ([Dictamen I/2024/253](https://www.gaceta.udg.mx/wp-content/uploads/2024/09/253-Bachillerato-General-SEMS.pdf)) define materias de 38, 57 o 76 h por semestre (≈2, 3 o 4 h/semana), grupos de 20 a 45 alumnos (20 a 30 en trayectorias especializantes) e **Inglés agrupado por nivel**, mezclando alumnos de varios semestres.

**Normativa DGB / SEP** ([mapa curricular MCCEMS](https://dgb.sep.gob.mx/storage/recursos/2026/01/SAAh1woSSx-Mapa-Curricular-Comun-de-la-EMS.pdf), [Acuerdo 21/08/25](https://sidof.segob.gob.mx/notas/5765757)):

- 30 h/semana en 1.º a 4.º semestre, 33 h en 5.º y 34 h en 6.º; de 8 a 10 asignaturas por semestre; 16 semanas por semestre.
- Cada profesor debe tener perfil afín a la asignatura (Profesiograma DGB).
- Jornadas típicas de 7:00–7:30 a 14:00, con módulos de 50–60 min y 1 o 2 recesos.

**Caso real UTEG, ciclo 27-A (archivo de carga horaria con datos de prueba; no se versiona):**

- La hoja `HORARIOS` es la **carga académica**: una fila por (grupo, materia) con `GRUPO`, `MATERIA`, `HORAS`, `N° docente` y `Docente`. Son 13 grupos y 124 filas; las materias tienen de 1 a 4 h. La hoja `DOCENTES` lista las cargas por profesor, pero en esta muestra no coincide con los nombres de `HORARIOS`.
- Las vacantes se capturan con el docente `GENERICO` (25 h en 8 grupos): son materias con profesor por contratar.
- Problemas de calidad que el importador debe detectar:
  - el mismo `N° docente` usado por dos personas;
  - filas sin número de docente;
  - dos columnas `GRUPO` que no coinciden (un código con una letra de más);
  - espacios dobles o finales en los nombres;
  - una fórmula con `#REF!`.
- Reglas que pidió la coordinación:
  - clases de 60 min;
  - 1.º y 2.º semestre de 8:00 a 14:00; 3.º a 6.º de 8:00 a 15:00, pudiendo entrar más tarde o salir antes, pero sin huecos;
  - respetar la carga de cada docente y no empalmar maestros;
  - **un docente no da dos materias distintas al mismo grupo el mismo día**;
  - clases seguidas para los docentes;
  - **cada materia, como máximo 2 h por día** (3 h = 2 + 1).
- Disponibilidades particulares: una docente solo de 8:00 a 14:00; otra solo de miércoles a viernes y **sin huecos**.
- Holgura: los grupos de 1.º y 2.º tienen 30 h en 30 franjas, y la docente de 8:00 a 14:00 tiene 30 h en 30 franjas. Ninguno tiene margen. El motor debe explicar con precisión si no cabe.

**Software existente:** FET (open source, potente pero difícil de usar), aSc Horarios (interfaz anticuada), Peñalara GHC (el más usado en México, barato), Untis, Prime Timetable (solo en inglés), UniTime (orientado a universidades). Huecos detectados: ninguno es web en español y fácil de usar, explica bien las infactibilidades e integra los planes SEP/DGB/UdeG; en la práctica el puente con los procesos de las escuelas es Excel. Los mejores motores son híbridos: restricciones duras garantizadas más optimización de preferencias.

## 3. Arquitectura

```
school-time-plan/
├── apps/web/          Laravel 12 + Inertia + Vue 3 + TypeScript + Tailwind
├── apps/solver/       Python 3.12 + FastAPI + OR-Tools CP-SAT
├── contracts/
│   ├── solve-request.schema.json
│   ├── solve-response.schema.json
│   └── fixtures/      casos compartidos (entrada + veredicto esperado)
├── docker-compose.yml web + solver + postgres (local)
├── render.yaml        despliegue de los 2 servicios
└── docs/
```

**Servicios:**

| Servicio | Responsabilidad | Toca la BD |
|---|---|---|
| `web` | UI, auth, multi-escuela, CRUD, import/export Excel, editor, cola de jobs (driver `database`) con un worker bajo supervisord en el mismo contenedor | Sí |
| `solver` | Sin estado. `POST /solve` (problema completo → horario o diagnóstico), `POST /validate` (problema + horario → violaciones por regla), `GET /health` | No |
| Postgres | Neon en producción, contenedor en local | — |

- **Autenticación entre servicios:** token compartido en el header `Authorization: Bearer <SOLVER_TOKEN>`.
- **Contrato:** JSON Schema versionado en `contracts/`. Las pruebas de ambos lados validan contra el mismo archivo. El campo `schema_version` va en cada request y el solver rechaza versiones que no conoce.

**Flujo de generar:**

1. El admin pulsa "Generar". Se crea una `Corrida` en estado `pendiente`.
2. El job `ResolverCorrida`:
   1. Llama a `/health` y espera hasta 90 s a que el servicio despierte (cold start de Render).
   2. Arma el JSON desde la BD.
   3. Llama a `POST /solve` con el límite de tiempo de la corrida (default 60 s, máx. 300 s).
3. El job guarda el resultado (`Sesion`es o diagnóstico) y el estado final.
4. La interfaz consulta el estado cada 3 s mientras la corrida está en `pendiente` o `resolviendo`.

Solo puede haber **una corrida activa por ciclo**.

## 4. Modelo de datos

Multi-escuela con **una sola BD**: cada tabla de dominio lleva `escuela_id`, y un *global scope* (trait `PerteneceAEscuela`) filtra todas las consultas por la escuela del usuario autenticado. El registro público crea una `Escuela` nueva y su primer `admin`.

Todas las entidades importables tienen una `clave` única por escuela (ej. `MAT-101`, `PROF-023`), que sirve como llave natural para Excel.

### Estructura

- `Escuela` — nombre, configuración (franjas mínimas de traslado entre planteles, límite de tiempo por defecto).
- `Plantel` — escuela, clave, nombre, dirección.
- `Espacio` — plantel, clave, nombre, tipo (`aula`, `laboratorio`, `computo`, `cancha`, `cocina`, `taller`), capacidad.
- `Ciclo` — escuela, clave (ej. `2026B`), fechas, estado.
- `PlantillaHoraria` — escuela, nombre (ej. "Matutino L–V", "Sabatino"), días activos.
- `Franja` — plantilla, día, orden, inicio, fin, tipo (`clase` | `receso`).

### Lo académico

- `PlanEstudios` — escuela, clave, nombre (ej. "BG UdeG 2025", "BIS SEJ").
- `Materia` — plan, clave, nombre, semestre, `horas_semana`, `patrones` (uno o varios repartos permitidos, ej. `[[2],[1,1]]`; el motor elige uno), `tipo_espacio`. Si no se capturan, se derivan de las horas con un máximo de 2 h por día: 1 → `[[1]]`, 2 → `[[2],[1,1]]`, 3 → `[[2,1]]`, 4 → `[[2,2]]`, 5 → `[[2,2,1]]`, 6 → `[[2,2,2]]`.
- `Grupo` — plantel, ciclo, plantilla, plan, semestre, clave (ej. `3B-ZAP`), número de alumnos.
- `Curso` — ciclo, materia, `profesor_id` (nulo = el motor elige), `vacante` (bool: profesor por contratar, el `GENERICO` de UTEG; no se le aplican reglas de profesor), grupos (pivote `curso_grupo`: los grupos que quedan ocupados durante el curso), `sincronia_id` nulo.
- `Sincronia` — ciclo, nombre. Los cursos que comparten sincronía ocurren el mismo día a la misma hora. Caso típico: Inglés por nivel, donde 1A, 1B y 3A tienen Inglés a la vez y cada nivel tiene su profesor y su salón.

### Personas

- `Profesor` — escuela, clave, nombre, contrato (`asignatura` | `medio_tiempo` | `tiempo_completo`), horas mínimas y máximas por semana, máximo por día, `sin_huecos` (bool: para este profesor, S2 es obligatoria), `user_id` nulo.
  - `profesor_plantel` — planteles donde puede dar clase.
  - `profesor_materia` — materias habilitadas (perfil).
  - `Disponibilidad` — profesor, plantilla, franja, estado (`disponible` | `preferente` | `no_disponible`). La editan el admin y el propio profesor.
- `User` — escuela, rol (`admin` | `profesor` | `alumno`), `grupo_id` para alumnos.

### Resultados

- `Corrida` — ciclo, estado (`pendiente` | `resolviendo` | `ok` | `factible_parcial` | `sin_solucion` | `tiempo_agotado` | `error`), parámetros (reglas y pesos), límite de tiempo, puntaje total, desglose por regla, diagnóstico, `origen_corrida_id` (de qué versión se derivó), `publicada` (bool, una por ciclo), `version` (bloqueo optimista).
- `Sesion` — corrida, curso, día, franja de inicio, duración, espacio, profesor, `fijada` (bool), `sin_acomodar` (bool, para la bandeja del editor).

Alcance de una corrida: **toda la escuela en un ciclo**, porque hay profesores que trabajan en varios planteles.

## 5. Motor (solver)

### Formulación

- Cada `Curso` se parte en **bloques** según cada patrón permitido de su materia (4 h con `[2,2]` = dos bloques de 2 franjas). Un selector booleano por (curso, patrón) elige exactamente un patrón, y solo se acomodan los bloques de ese patrón.
- Variables booleanas `x[b, d, f]`: el bloque `b` empieza el día `d` en la franja `f`. Solo se crean para inicios válidos: dentro de franjas de clase seguidas, sin cruzar recesos, en la plantilla del grupo.
- Si el curso no trae profesor: variables `y[c, p]` para cada profesor habilitado, con exactamente uno elegido; todos los bloques del curso usan ese profesor.
- Espacio: variables `z[b, e]` para los espacios del tipo requerido, en el plantel del grupo y con capacidad ≥ alumnos.
- La ocupación por franja se deriva con restricciones lineales (suma ≤ 1 por grupo, profesor y espacio en cada franja).

### Reglas duras (siempre se cumplen)

| ID | Regla |
|---|---|
| H1 | Se elige un patrón por curso y cada bloque de ese patrón se acomoda exactamente una vez, en franjas de clase seguidas, sin cruzar recesos |
| H2 | Un grupo no tiene dos sesiones en la misma franja |
| H3 | Un profesor no tiene dos sesiones en la misma franja (no aplica a cursos `vacante`) |
| H4 | Un espacio no tiene dos sesiones en la misma franja; tipo correcto y capacidad suficiente |
| H5 | El profesor solo da materias habilitadas y solo en planteles asignados |
| H6 | El profesor solo da clase en franjas `disponible` o `preferente` |
| H7 | Horas del profesor: máximo por día, mínimo y máximo por semana |
| H8 | Los cursos de una misma sincronía empiezan el mismo día en la misma franja |
| H9 | Los bloques de una misma materia caen en días distintos |
| H10 | Las sesiones fijadas no se mueven |
| H11 | Si un profesor está en dos planteles el mismo día, hay al menos N franjas libres entre ellos (N lo configura la escuela) |

### Reglas configurables (los "parámetros")

Cada una se configura por corrida como `apagada`, `preferencia` con peso de 1 a 10, u `obligatoria` (pasa a ser dura).

| ID | Regla | Default |
|---|---|---|
| S1 | Sin huecos para los grupos | obligatoria |
| S2 | Minimizar huecos de profesores | preferencia 7 |
| S3 | Minimizar días que viene cada profesor | preferencia 5 |
| S4 | Usar franjas `preferente` del profesor | preferencia 4 |
| S5 | Repartir cada materia en la semana (no días seguidos) | preferencia 3 |
| S6 | Reglas de franja genéricas: "materia/tipo X no en franjas Y" (ej. Ed. Física no en la primera hora) | lista vacía |
| S7 | Un profesor no da dos materias distintas al mismo grupo el mismo día | obligatoria |

Además, un profesor con `sin_huecos` tiene S2 como obligatoria solo para él, sin importar el modo global.

La función objetivo es la suma ponderada de las penalizaciones. La respuesta incluye el desglose por regla (ej. `S2: 12 → 3`).

### Diagnóstico de infactibilidad

1. **Prechecks** (antes de construir el modelo, en milisegundos), cada uno con mensaje en español y las entidades involucradas:
   - Horas requeridas por profesor fijo > franjas disponibles.
   - Horas de un grupo > franjas de clase de su plantilla.
   - Demanda por tipo de espacio y plantel > capacidad en franjas.
   - Curso sin ningún profesor habilitado en ese plantel.
   - Sincronía con grupos de plantillas distintas.
2. **Núcleo de suposiciones:** cada familia de reglas duras, por entidad (ej. "H6 para Prof. Ana"), se activa con un literal de suposición. Si CP-SAT devuelve `INFEASIBLE`, `SufficientAssumptionsForInfeasibility()` da un subconjunto de reglas que chocan, y cada literal se traduce a un mensaje en español.
3. Se distingue `sin_solucion` (demostrado infactible) de `tiempo_agotado` (no se encontró solución en el tiempo dado).

### Resultado

- Mejor solución encontrada dentro del límite de tiempo, con estado `OPTIMAL` o `FEASIBLE`.
- Semilla fija y `num_workers` configurable, para que el resultado sea reproducible.
- Si se marca "permitir parcial", los bloques que no se pudieron acomodar se devuelven como `sin_acomodar`. Así funciona `factible_parcial`, implementado con un literal "acomodado" por bloque y una penalización alta.

## 6. Interfaz y flujos

### Roles

| Rol | Puede |
|---|---|
| `admin` | Todo dentro de su escuela: catálogos, profesores, disponibilidad, carga, importar/exportar, generar, editar, publicar |
| `profesor` | Editar su disponibilidad (mientras el ciclo esté abierto a captura), ver su horario publicado, exportar su `.ics` |
| `alumno` | Ver el horario publicado de su grupo |

### Flujo borrador → toques finales

1. **Generar:** el motor entrega un horario completo en estado borrador, visible solo para los admins.
2. **Editor de cuadrícula:**
   - Columnas = días y filas = franjas, con los recesos visibles. Los bloques son tarjetas con el color de la materia, el profesor y el espacio.
   - **Arrastrar y soltar** con validación en vivo: al arrastrar, cada celda se pinta verde si cabe o roja si choca, y un panel dice el motivo ("A. López no está disponible el viernes").
   - Soltar sobre otro bloque ofrece **intercambiarlos**.
   - Vistas por grupo, por profesor y por espacio sobre los mismos datos.
   - **Bandeja** de bloques sin acomodar.
   - **Alfiler** para fijar; Ctrl+Z / Ctrl+Y; mover con el teclado (flechas + Enter) para accesibilidad (WCAG 2.2 AA).
   - Panel de avisos de preferencias no cumplidas (huecos, etc.).
3. **Re-optimizar lo no fijado:** se crea una corrida nueva con las sesiones fijadas como H10 y `origen_corrida_id` apuntando a la anterior. Cada versión guarda su puntaje para compararlas.
4. **Publicar:** se marca la corrida como publicada (desmarca la anterior del ciclo). Profesores y alumnos la ven en solo lectura.

### Validación en el navegador

El validador de reglas duras se implementa en TypeScript (`apps/web/resources/js/validator/`) sobre los datos cargados en el editor: ocupación, disponibilidad, recesos, sincronía, capacidad y tipo de espacio, H7, H9 y H11. Así el arrastre no espera al servidor. Para evitar que diverja del motor, los casos de `contracts/fixtures/` (horario + veredicto esperado por regla) corren tanto en pytest como en Vitest.

Al guardar, Laravel revalida el horario completo con `POST /validate` del solver, que reutiliza el validador independiente de Python. Así la verdad final siempre es la de Python, y Laravel no reimplementa reglas. Si el solver no responde, el cambio se guarda marcado como "sin validar en servidor" y se revalida al publicar; no se puede publicar sin esa validación.

### Prototipo de referencia

Mockup aprobado durante el diseño: cuadrícula de 5 días × 6 franjas + receso, tarjetas arrastrables, celdas verde/rojo al arrastrar, panel "Al arrastrar" con el motivo, panel de avisos, alfiler para fijar y botón "Re-optimizar lo no fijado".

## 7. Importar y exportar con Excel

### Plantilla `.xlsx`

Se descarga desde la app, con encabezados, validaciones de datos (listas desplegables) y una hoja `Instrucciones`.

| Hoja | Columnas |
|---|---|
| `Planteles` | clave, nombre, dirección |
| `Espacios` | clave, plantel, nombre, tipo, capacidad |
| `Plantillas` | plantilla, día, orden, inicio, fin, tipo |
| `Materias` | clave, plan, nombre, semestre, horas_semana, patrones (opcional; ej. `2 | 1+1`, vacío = derivar de las horas), tipo_espacio |
| `Grupos` | clave, plantel, plantilla, plan, semestre, alumnos |
| `Profesores` | clave, nombre, contrato, horas_min, horas_max, max_dia, planteles (claves separadas por coma), materias (claves separadas por coma) |
| `Disponibilidad` | clave_profesor, plantilla, y una columna por franja (`Lun 07:00`…) con `D` / `P` / `N` (vacío = `D`) |
| `Carga` | grupo(s), materia, profesor (opcional), sincronía (opcional) |
| `Horario` (opcional) | grupo, materia, día, inicio, espacio, profesor, fijada (S/N) |

### Flujo de importación

1. Subir el archivo (máx. 5 MB). Además de la plantilla propia, se acepta el **formato de carga horaria de UTEG** (columnas `GRUPO`, `MATERIA`, `HORAS`, `N° docente`, `Docente`): `GENERICO` → curso `vacante`, y los patrones se derivan de las horas.
2. **Vista previa:** por hoja, cuántos registros se crean y cuántos se actualizan, más la lista de errores con hoja, fila, columna y mensaje (ej. "Profesores, fila 14: la materia MAT-3 no existe"). Los nombres se normalizan (espacios dobles y finales). Hay advertencias para: un número de docente con dos nombres distintos, filas sin número de docente y columnas de grupo que no coinciden.
3. Si no hay errores, se confirma y se aplica todo en **una sola transacción**. Con errores no se aplica nada.
4. Los registros se identifican por `clave`, así que reimportar el mismo archivo no duplica nada.
5. La hoja `Horario` crea una corrida borrador (fase 3).

### Exportar

- **Datos:** el mismo formato de plantilla, lleno con los datos actuales. Permite ida y vuelta: exportar → editar en Excel → reimportar.
- **Horario publicado:** Excel con formato de cuadrícula (una hoja por grupo, profesor o espacio) listo para imprimir, además de PDF y `.ics`.

Librería: `maatwebsite/excel` (PhpSpreadsheet).

## 8. Manejo de errores

| Situación | Comportamiento |
|---|---|
| Solver dormido o caído | El job espera hasta 90 s a `/health` y reintenta 2 veces con backoff. Si sigue fallando, la corrida queda en `error` con mensaje y botón "Reintentar" |
| Request inválido para el contrato | El solver responde 422 con los errores del schema. La corrida queda en `error` y se registra en el log |
| Sin solución demostrada | `sin_solucion` + diagnóstico (prechecks o núcleo) |
| Tiempo agotado sin solución | `tiempo_agotado`, sugiriendo dar más tiempo o aflojar reglas |
| Dos admins editan el mismo borrador | Bloqueo optimista por `version`. El segundo recibe 409 y el mensaje "otra persona cambió este horario, recarga" |
| Corrida activa en el ciclo | Se rechaza la nueva y se muestra la que está en curso |
| Acceso a datos de otra escuela | 404 (el global scope no encuentra el registro) |

## 9. Pruebas

| Capa | Herramienta | Qué cubre |
|---|---|---|
| Motor | pytest | Una prueba por regla H1–H11 y S1–S6 con instancias diminutas; prechecks; traducción del núcleo de infactibilidad |
| Motor | Hypothesis | Todo horario devuelto pasa un validador independiente (en Python) |
| Contrato | pytest + Vitest + Pest | Ejemplos válidos e inválidos contra el JSON Schema; los fixtures de `contracts/fixtures/` dan el mismo veredicto en Python y en TS |
| Web | Pest | Aislamiento entre escuelas, permisos por rol, flujo de corrida con el solver falso, import (vista previa, errores, transacción) y la prueba de ida y vuelta de Excel |
| Editor | Vitest | Validador TS, deshacer/rehacer, intercambio |
| E2E | Playwright | Entrar como admin → generar → arrastrar → fijar → re-optimizar → publicar → entrar como alumno |
| Benchmark | script en `apps/solver/bench/` | Escuela demo (≈36 grupos, ≈60 profesores, 3 planteles): tiempo y puntaje; meta < 60 s. Se registra en CI sin bloquear el merge |

CI en GitHub Actions: lint (Pint, ESLint, Ruff), pruebas de las tres capas, y build de las imágenes Docker en cada PR.

## 10. Deploy

- **Render** (`render.yaml`), 2 servicios Docker:
  - `web`: nginx + php-fpm + `queue:work` con supervisord. Al arrancar corre `php artisan migrate --force`.
  - `solver`: uvicorn con FastAPI. Health check en `/health`.
- **Neon:** `DATABASE_URL` con conexión *pooled* para web y `sslmode=require`.
- **Secretos:** `APP_KEY`, `DATABASE_URL`, `SOLVER_URL`, `SOLVER_TOKEN` en las variables de entorno de Render. Nunca en el repo.
- **Escuela demo "Prepa Horizonte"** (seeder): 3 planteles, BG (UdeG 2025) y BIS sabatino, materias y horas basadas en la investigación DGB/UdeG, Inglés por nivel con sincronías y profesores por asignatura con disponibilidad realista. La pantalla de login tiene los botones "Entrar como admin / profesor / alumno" para la demo; el comando `php artisan demo:reset` restablece sus datos.
- **Local:** `docker compose up` levanta web, solver y Postgres con la escuela demo.

## 11. Fases

Cada fase tiene su propio plan de implementación.

| Fase | Entrega | Se puede demostrar |
|---|---|---|
| 1. Motor | `apps/solver`, contrato, fixtures, prechecks, diagnóstico, benchmark con la escuela demo y con el caso real 27-A anonimizado, ambos en JSON | `curl` a `/solve` con la escuela demo devuelve un horario o un diagnóstico |
| 2. Web base | Laravel multi-escuela, roles, CRUD de catálogos, profesores y disponibilidad, carga académica, import/export Excel de datos, generar y ver el resultado en cuadrícula de solo lectura | Importar plantilla → generar → ver horario |
| 3. Editor | Arrastrar y soltar, validador TS, intercambio, bandeja, fijar, re-optimizar, versiones, importar hoja `Horario` | Ajustar un borrador y re-optimizar |
| 4. Publicar y deploy | Publicación, vistas de profesor y alumno, PDF, Excel en cuadrícula, `.ics`, Docker de producción, Render + Neon, demo pública | URL pública funcionando |

## 12. Fuera de alcance

- Pagos y suscripciones del SaaS.
- Integración directa con SIAAF, Espacio UTEG u otros sistemas de control escolar (el puente es Excel).
- Horarios de exámenes y suplencias.
- Inscripción de alumnos a materias (los alumnos pertenecen a un grupo).
- App móvil nativa (la web es responsiva).
