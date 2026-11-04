# Fase 2 — avance y pendientes

Corte: 2026-11-04. Plan: [`2026-11-05-fase-2-web.md`](2026-11-05-fase-2-web.md). Spec: [`2026-11-04-generador-horarios-design.md`](../specs/2026-11-04-generador-horarios-design.md).

## Dónde va

- **Rama:** `feat/fase-2-web`. Sale de `docs/plan-fase-2` (el plan), que sale de `main`.
- **Hecho:** tareas 1 y 2 de 14. `composer ci:check` pasa con 57 pruebas.
  - Tarea 1 (`4f6b9e6`): Laravel 13 en español, Docker local (Postgres y motor) y CI.
  - Tarea 2 (`bb76a14`): multi-escuela con global scope, roles, registro que crea la escuela y modelo `Plantel`.
- **Sigue:** tarea 3, planteles y espacios. Todavía no tiene código.

## Retomar en otra máquina (unos 15 min)

Requisitos: PHP 8.3+ (`pdo_pgsql`, `pdo_sqlite`, `intl`, `zip`, `gd`), Composer, Node 22+ y Docker.

```bash
git fetch origin && git switch feat/fase-2-web
docker compose up -d
cd apps/web && composer install && npm ci
cp .env.example .env && php artisan key:generate && php artisan migrate
npm run build && composer ci:check
```

Postgres queda en el puerto 5433 del host y el motor en el 8000 (token `dev`). El último comando debe terminar sin errores.

## Retomar con Claude

1. Recrea el ledger desde la raíz del repo (la carpeta `.superpowers/` no se versiona):

   ```bash
   mkdir -p .superpowers/sdd/2026-11-05-fase-2-web && sed -n '/^~~~text$/,/^~~~$/p' docs/superpowers/plans/2026-11-05-fase-2-web-avance.md | sed '1d;$d' > .superpowers/sdd/2026-11-05-fase-2-web/progress.md
   ```

2. Pide: «Continúa la ejecución nativa del plan `docs/superpowers/plans/2026-11-05-fase-2-web.md` desde la tarea 3».

## Notas para la tarea 3

- Las rutas de catálogo no llevan prefijo de nombre: `planteles.index`, `espacios.store`… (las pruebas del plan usan `route('planteles.destroy')`). Las URL son `/planteles` y `/espacios`, y las páginas viven en `pages/escuela/…`. Solo el inicio es `escuela.inicio` (`/escuela`).
- Cuando un FormRequest use `ReglasEscuela`, borra el `ignoreErrors` de `phpstan.neon`: PHPStan falla hasta quitarlo.
- Con un solo uso, la plantilla del mensaje de borrado diría «lo usan 1 espacios». Propuesta: «lo usa 1 espacio», registrada como ruling.
- `components/DeleteUser.vue` todavía tiene el diálogo en inglés; tradúcelo de paso.
- Después de agregar páginas, corre `npm run build` antes de `php artisan test`: el manifiesto de Vite y las rutas de Wayfinder salen de ahí.

## Pendiente

### Fase 2

1. Catálogos, tareas 3 a 7: planteles y espacios; ciclos y plantillas; planes, materias y grupos; profesores y disponibilidad; cursos y sincronías.
2. Motor, tareas 8 a 10: armar el problema, corridas con job y cuadrícula de solo lectura.
3. Excel, tareas 11 a 13: exportar, importar la plantilla e importar la carga de UTEG.
4. Tarea 14: escuela demo y flujo completo.
5. Revisión final de toda la rama con un revisor nuevo, una pasada de arreglos y el PR.

### Decisión tuya

- **`httpx` o `httpx2` en `apps/solver`:** Starlette sugiere `httpx2` para el `TestClient`. Se bloqueó instalarlo porque el nombre salió de un aviso y no de una fuente verificada; hoy queda `httpx` con ese aviso filtrado en pytest. Si confías en el paquete, el cambio es de una línea.

### Menores diferidos de la fase 1

Motor:

- M11: el modo parcial es por curso, no por bloque como dice el spec.
- M12: `PRE_FIJADA` no revisa la disponibilidad del profesor fijo cuando la fijada no trae profesor.
- M15: `PRE_SINCRONIA` compara las listas de patrones en orden (`[[2],[1,1]]` ≠ `[[1,1],[2]]`).
- M16: un profesor fijo sin la materia habilitada apaga H5 en silencio; falta un aviso no bloqueante.

Diagnóstico:

- M5: el modelo de diagnóstico incluye las preferencias; conviene construirlo en modo factibilidad.
- M7: `explain_core` deduplica sin mirar `Supuesto.datos` (dos H4 de un plantel con tipos distintos se juntan).

Pruebas y CI:

- M10: el CI no construye la imagen Docker (spec §9).
- M13: falta un fixture de traslape parcial entre plantillas (12:30–13:20 contra 13:00–13:50).
- M14: `test_parcial_solo_falla_por_bloques_sin_acomodar` permite H7; hay que endurecerla a `{"H1"}`.

M2, M3, M4 y M9 se arreglaron en el PR Pachekoko/school-time-plan#2; M6 quedó resuelto junto con C1.

## Ledger

Copia del ledger local hasta la tarea 2, con todos los rulings:

~~~text
# SDD ledger — plan: docs/superpowers/plans/2026-11-05-fase-2-web.md
Spec: docs/superpowers/specs/2026-11-04-generador-horarios-design.md
Setup: Ruling: rama feat/fase-2-web (desde docs/plan-fase-2) en este checkout, sin worktree — preferencia del usuario ya declarada en la sesión — costo si está mal: mover commits a un worktree.
Setup: base verde — apps/solver: 196 passed (not slow).
Pre-flight (interfaces compartidas):
- T2→T3..T14: PerteneceAEscuela, EscuelaActual, ReglasEscuela, Rol, middleware escuela/rol. Sin conflicto.
- T3 Borrado → T5 (grupos), T6 (franjas con disponibilidad), T7 (materias con cursos). Sin conflicto (firma impedirSiSeUsa(string $nombre, array $usos)).
- T4 Ciclo/cicloActual/GeneradorFranjas → T5, T7, T11, T12. Sin conflicto.
- T5 Patrones → T8 (patronesEfectivos), T9 (duración), T11 (formatear), T12 (parsear), T13 (derivar). Sin conflicto.
- T6 Disponibilidad → T8, T11, T12. Sin conflicto.
- T7 ClaveCurso → T12, T13. Sin conflicto.
- T8 ConstructorProblema/ParametrosCorrida → T9, T14. Sin conflicto.
- T9 Corrida/Sesion → T10. Sin conflicto.
- T11 Hojas/ExportadorPlantilla → T12 (ida y vuelta). Encabezados de Disponibilidad «Día HH:MM»: T12 los normaliza con Normalizador::encabezado («mie_07:00») y los separa por «_». Sin conflicto.
- T12 Normalizador/LectorLibro/PlanImportacion/AplicadorImportacion → T13. Sin conflicto.
- T2→T6: Ruling: la ruta mi-disponibilidad que usa la redirección de /dashboard para profesores (T2) se crea en T2 con una página provisional («Pronto podrás capturar tu disponibilidad») y T6 la reemplaza — la redirección de T2 necesita que la ruta exista — costo si está mal: ninguno.
Task 1: Ruling: laravel-lang/common exige ext-bcmath (no está en el PHP local); se instaló una sola vez con --ignore-platform-req=ext-bcmath para publicar lang/es y lang/es.json, y se restauraron composer.json/lock — el español queda versionado sin dependencia nueva ni cambio al PHP del sistema — costo si está mal: para actualizar traducciones hay que repetir el truco.
Task 1: Ruling: el Postgres de docker compose se expone en el puerto 5433 del host (DB_PORT=5433 en .env.example) — ya hay un PostgreSQL local en el 5432 — costo si está mal: ninguno.
Task 1: Ruling: script npm test = «vp test run --passWithNoTests» (subcomando de Vite+; «vp test --run» no existe y sin la bandera falla sin archivos de prueba) — costo si está mal: ninguno.
Task 1: Ruling: el workflow web.yml no usa «composer setup» del starter kit (migra con el .env de pgsql y fallaría sin base); hace install, .env + key, npm ci, build, ci:check y npm run test — costo si está mal: ninguno.
Task 1: Ruling: se tradujeron también los textos accesibles de components/ui (Close, Toggle sidebar…) y los errores de useTwoFactorAuth.ts; APP_NAME="Horarios" — costo si está mal: ninguno.
Task 1: complete (commits ac0e43b..4f6b9e6, tests: bash -c 'cd apps/web && composer ci:check && npm run test' → exclude:  **/node_modules/**, **/.git/**)
Task 2: Ruling: los modelos declaran su tabla con #[Table('planteles')] y EscuelaActual se registra con #[Singleton], en vez de $table y un binding en el provider — es el estilo de atributos del starter kit (#[Fillable], #[Hidden]) — costo si está mal: ninguno.
Task 2: Ruling: IdentificarEscuela y RequiereRol van en la lista de prioridad antes de SubstituteBindings, y el middleware envuelve $next con EscuelaActual::usar — sin esto el enlace de modelos corre antes de fijar la escuela (todo daría 404) y la escuela quedaría fija tras la petición; prueba test_un_registro_de_otra_escuela_en_la_ruta_da_404 — costo si está mal: ninguno.
Task 2: Ruling: el hook creating de PerteneceAEscuela respeta un escuela_id ya puesto (solo llena si viene vacío) — permite ->for($escuela) en factories y la demo; escuela_id no es fillable en ningún modelo, así que un formulario no puede ponerlo — costo si está mal: código que fije a mano otra escuela la guardaría ahí.
Task 2: Ruling: users.escuela_id es nullable en BD (SQLite no agrega NOT NULL sin default; todo alta lo llena) y un usuario sin escuela recibe 403 en el middleware escuela; users.rol tiene default 'alumno' (el de menos permisos); /dashboard da 403 al alumno (sus vistas son de la fase 4); ReglasEscuela lanza LogicException sin escuela actual — falla cerrado — costo si está mal: ninguno.
Task 2: Ruling: se borraron Dashboard.vue y PlaceholderPattern.vue (sin uso tras la redirección); composer types:check corre phpstan con --memory-limit=1G (truena a 128M); phpstan.neon ignora trait.unused en ReglasEscuela hasta que T3 lo use (el ignore sin coincidencia hará fallar PHPStan y obliga a quitarlo); pruebas extra: registro sin escuela, 404 entre escuelas en rutas, existe/unica, alumno 403 y profesor en mi-disponibilidad — costo si está mal: ninguno.
Task 2: complete (commits 4f6b9e6..bb76a14, tests: bash -c 'cd apps/web && composer ci:check && npm run test' → exclude:  **/node_modules/**, **/.git/**)
~~~

## Detalles de esta máquina

- En zsh, `cp` pregunta antes de sobrescribir: usa `command cp -f`.
- Aquí `git push` necesita `git -c credential.helper= -c credential.helper='!gh auth git-credential' push`.
- PHPStan corre con `--memory-limit=1G`; con 128 MB se cae.
