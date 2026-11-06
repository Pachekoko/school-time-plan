# Guía de primeros pasos — plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que el admin de una escuela nueva capture sus datos con una guía flotante que avanza sola según lo que ya existe en la base.

**Architecture:**
- El servidor calcula con una sola consulta cuáles de los 9 pasos tienen datos y los comparte en la prop `guia`; el estado «cerrada» vive en `users.guia_cerrada_en`.
- En el navegador, `lib/guia.ts` define los pasos y sus textos, `useGuia` lleva el estado del panel y el resaltado, y `GuiaPanel` y `NavGuia` son la interfaz.
- Los botones que se resaltan llevan `data-guia="<paso>"`.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3.5 + TypeScript, Tailwind 4, Wayfinder, reka-ui/shadcn-vue, @lucide/vue, PHPUnit 12, Vitest (Vite+ `vp`).

**Spec:** [`docs/superpowers/specs/2026-11-06-guia-primeros-pasos-design.md`](../specs/2026-11-06-guia-primeros-pasos-design.md)

## Global Constraints

- Sin dependencias nuevas, ni de Composer ni de npm.
- Solo el rol admin recibe la guía; para cualquier otro rol la prop `guia` es `null`.
- Las claves y su orden son exactos: `ciclo`, `planteles`, `espacios`, `plantilla`, `materias`, `grupos`, `profesores`, `carga`, `generar`.
- Los títulos y los textos «qué hacer» y «para qué sirve» se copian tal cual de las tablas de la spec.
- El avance sale de una sola consulta a la base.
- `composer ci:check` y `npm run test` pasan al final de cada tarea. `ci:check` incluye `vp check`, `vue-tsc`, Pint, PHPStan nivel 7 y PHPUnit.
- Después de agregar rutas o páginas, corre `npm run build` antes de las pruebas de PHP: el manifiesto de Vite y las rutas de Wayfinder salen de ahí.

## Review Focus

1. **Navegar entre pantallas con Inertia, sin recargar:** el borde se mueve al botón del paso actual y no queda ninguno viejo. Se revisa en el navegador en la Tarea 4, paso 6.
2. **Celular:** el panel no tapa los botones de guardar de los formularios. Se revisa en la Tarea 4, paso 6, con el tamaño `mobile`.
3. **Una escuela con los 9 pasos hechos que nunca cerró la guía:** no se abre sola. Lo cubre `abreSola` en la Tarea 3.
4. **Cerrar la guía en una computadora y entrar en otra:** sigue cerrada. Lo cubre `test_cerrar_y_abrir_la_guia_se_recuerda` en la Tarea 2.
5. **El paso actual no tiene botón en la página abierta:** no falla nada y no se resalta nada. Se revisa en el navegador en la Tarea 4, paso 6.

---

### Task 1: El avance de la guía en el servidor

**Files:**
- Create: `apps/web/app/Guia/PrimerosPasos.php`
- Test: `apps/web/tests/Feature/PrimerosPasosTest.php`

**Interfaces:**
- Produces: `App\Guia\PrimerosPasos`
  - `public const array PASOS` — las 9 claves en el orden de los Global Constraints.
  - `public function hechos(Escuela $escuela): array<string, bool>` — las 9 claves en ese orden.

- [ ] **Step 1: Escribir las pruebas que fallan**

Todas usan `RefreshDatabase`. `EscuelaDePrueba::crear()` es el helper de `tests/Support`.

```php
public function test_una_escuela_vacia_tiene_todo_pendiente(): void
{
    $hechos = app(PrimerosPasos::class)->hechos(Escuela::factory()->create());

    $this->assertSame(PrimerosPasos::PASOS, array_keys($hechos));
    $this->assertSame([], array_keys(array_filter($hechos)));
}

public function test_la_escuela_de_prueba_solo_le_falta_generar(): void
{
    $e = EscuelaDePrueba::crear();

    $hechos = app(PrimerosPasos::class)->hechos($e['escuela']->fresh());

    $this->assertSame(['generar'], array_keys(array_filter($hechos, fn (bool $h) => ! $h)));
}

public function test_una_corrida_con_horario_completa_la_guia_y_una_con_error_no(): void
{
    // Crear con $e['ciclo']->corridas()->create([...]) estados Error y luego Ok, con
    // 'parametros' => [] y 'tiempo_limite_s' => 60. Tras Error, generar es false;
    // tras Ok, es true.
}

public function test_los_datos_de_otra_escuela_no_cuentan(): void
{
    // EscuelaDePrueba::crear() y luego Escuela::factory()->create():
    // la segunda tiene los 9 en false.
}

public function test_el_avance_sale_de_una_sola_consulta(): void
{
    // Con EscuelaDePrueba: DB::flushQueryLog(), DB::enableQueryLog(), llamar a hechos()
    // y assertCount(1, DB::getQueryLog()).
}
```

- [ ] **Step 2: Correrlas y ver que fallan**

Run: `cd apps/web && php artisan test --compact --filter=PrimerosPasosTest`

Expected: FAIL, porque `App\Guia\PrimerosPasos` no existe.

- [ ] **Step 3: Implementar `PrimerosPasos::hechos(Escuela $escuela): array<string, bool>`**

- **`ciclo`:** sale de `$escuela->ciclo_actual_id !== null`, sin consulta.
- **Los otros 8:** un solo `DB::selectOne` con una subconsulta `EXISTS` por clave, filtrada a mano. Los global scopes no aplican en SQL crudo.

| Clave | Tabla | Filtro |
|---|---|---|
| `planteles` | `planteles` | `escuela_id = ?` |
| `espacios` | `espacios` | `escuela_id = ?` |
| `plantilla` | `franjas` | `escuela_id = ?` y `tipo = 'clase'` |
| `materias` | `materias` | `escuela_id = ?` |
| `profesores` | `profesores` | `escuela_id = ?` |
| `grupos` | `grupos` | `ciclo_id = ?` |
| `carga` | `cursos` | `ciclo_id = ?` |
| `generar` | `corridas` | `ciclo_id = ?` y estado en `EstadoCorrida::Ok` o `EstadoCorrida::FactibleParcial` |

- **Sin ciclo actual,** usa `0` como id de ciclo: sigue siendo una consulta y las tres claves del ciclo dan `false`.
- **Cada valor se convierte con `(bool)`:** SQLite devuelve `0` o `1`, PostgreSQL devuelve booleanos.

- [ ] **Step 4: Correrlas y ver que pasan, también en PostgreSQL**

Run:

```bash
cd apps/web && php artisan test --compact --filter=PrimerosPasosTest
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=horarios_test DB_USERNAME=horarios DB_PASSWORD=horarios php artisan test --compact --filter=PrimerosPasosTest
```

Expected: 5 pasan en cada base.

- [ ] **Step 5: Commit**

```bash
git add apps/web/app/Guia apps/web/tests/Feature/PrimerosPasosTest.php
git commit -m "feat(web): avance de la guía de primeros pasos"
```

---

### Task 2: La guía en la app: prop compartida, cerrar y abrir

**Files:**
- Create: `apps/web/database/migrations/2026_11_06_000100_add_guia_cerrada_en_to_users_table.php`
- Create: `apps/web/app/Http/Controllers/GuiaController.php`
- Create: `apps/web/app/Http/Requests/GuiaRequest.php`
- Modify: `apps/web/app/Models/User.php` (cast)
- Modify: `apps/web/app/Http/Middleware/HandleInertiaRequests.php` (`share()`)
- Modify: `apps/web/routes/web.php` (grupo `rol:admin`)
- Test: `apps/web/tests/Feature/GuiaTest.php`

**Interfaces:**
- Consumes: `PrimerosPasos::hechos()` (Tarea 1).
- Produces:
  - La columna `users.guia_cerrada_en`: timestamp nullable, con cast `'datetime'`.
  - La prop compartida `guia`:
    - para un admin con escuela: `array{hechos: array<string, bool>, cerrada: bool}`;
    - para cualquier otro: `null`.
  - La ruta `PUT /guia`, `guia.update`, dentro del grupo `rol:admin`. `GuiaRequest` pide `'abierta' => ['required', 'boolean']`. Responde `back()`.

- [ ] **Step 1: Escribir las pruebas que fallan**

```php
public function test_el_admin_recibe_su_avance(): void
{
    $e = EscuelaDePrueba::crear();

    $this->actingAs($e['admin'])->get(route('escuela.inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('guia.hechos.planteles', true)
            ->where('guia.hechos.generar', false)
            ->where('guia.cerrada', false));
}

public function test_el_profesor_no_recibe_la_guia(): void
{
    $this->actingAs(User::factory()->profesor()->create())->get(route('mi-disponibilidad'))
        ->assertInertia(fn (Assert $page) => $page->where('guia', null));
}

public function test_cerrar_y_abrir_la_guia_se_recuerda(): void
{
    // Como admin:
    // 1. PUT guia.update ['abierta' => false] → assertRedirect(); guia_cerrada_en no es null.
    // 2. GET escuela.inicio → guia.cerrada es true.
    // 3. PUT ['abierta' => true] → guia_cerrada_en vuelve a null.
}

public function test_el_profesor_no_puede_cambiar_la_guia(): void
{
    // PUT guia.update ['abierta' => false] como profesor → assertForbidden().
}

public function test_abierta_es_obligatoria(): void
{
    // PUT guia.update [] como admin → assertSessionHasErrors('abierta').
}
```

- [ ] **Step 2: Correrlas y ver que fallan**

Run: `cd apps/web && php artisan test --compact --filter=GuiaTest`

Expected: FAIL, porque no existen la prop `guia` ni la ruta `guia.update`.

- [ ] **Step 3: Implementar**

1. La migración: `$table->timestamp('guia_cerrada_en')->nullable()`, con `down` que la quita.
2. El cast en `User::casts()`.
3. `GuiaRequest`.
4. `GuiaController::update(GuiaRequest $request): RedirectResponse`: hace `forceFill(['guia_cerrada_en' => $request->boolean('abierta') ? null : now()])->save()` y devuelve `back()`.
5. La ruta, junto a `escuela.inicio`.
6. En `share()`: `'guia' => fn () => $this->guia($request)`, con `private function guia(Request $request): ?array`.
   - Devuelve `null` si el usuario no es `Rol::Admin` o no tiene escuela.
   - Si no, devuelve `hechos` de su escuela y `cerrada` (`guia_cerrada_en !== null`).

- [ ] **Step 4: Correr todo**

Run: `cd apps/web && composer ci:check`

Expected: pasa, con las pruebas nuevas en verde.

- [ ] **Step 5: Commit**

```bash
git add apps/web/database/migrations apps/web/app apps/web/routes/web.php apps/web/tests/Feature/GuiaTest.php
git commit -m "feat(web): la guía se comparte con el admin y recuerda si la cerró"
```

---

### Task 3: Los pasos en el navegador

**Files:**
- Create: `apps/web/resources/js/types/guia.ts`
- Create: `apps/web/resources/js/lib/guia.ts`
- Modify: `apps/web/resources/js/types/global.d.ts` (`sharedPageProps.guia`)
- Modify: `apps/web/resources/js/types/index.ts` (exporta los tipos nuevos)
- Test: `apps/web/resources/js/lib/guia.test.ts`

**Interfaces:**
- Consumes: la prop `guia` y la ruta `guia.update` (Tarea 2). Corre `npm run build` antes, para que Wayfinder genere `@/routes/guia`.
- Produces:
  - En `types/guia.ts`:
    ```ts
    export type ClavePaso = 'ciclo' | 'planteles' | 'espacios' | 'plantilla' | 'materias' | 'grupos' | 'profesores' | 'carga' | 'generar';
    export type Guia = { hechos: Record<ClavePaso, boolean>; cerrada: boolean };
    ```
  - En `lib/guia.ts`:
    - `PASOS: readonly Paso[]`, con `Paso = { clave: ClavePaso; titulo: string; queHacer: string; paraQue: string; destino: string; ir: () => RouteDefinition<'get'> }`;
    - `pasoActual(hechos: Record<ClavePaso, boolean>): Paso | null`;
    - `avance(hechos: Record<ClavePaso, boolean>): { hechos: number; total: number }`;
    - `abreSola(guia: Guia): boolean`.
  - En `global.d.ts`: `guia: Guia | null` dentro de `sharedPageProps`.

- [ ] **Step 1: Escribir las pruebas que fallan**

```ts
const nada = Object.fromEntries(PASOS.map((p) => [p.clave, false])) as Record<ClavePaso, boolean>;

test('los pasos van en el orden del servidor', () => {
    expect(PASOS.map((p) => p.clave)).toEqual(['ciclo', 'planteles', 'espacios', 'plantilla', 'materias', 'grupos', 'profesores', 'carga', 'generar']);
});
test('el paso actual es el primer pendiente, aunque haya hechos después', () => {
    expect(pasoActual(nada)?.clave).toBe('ciclo');
    expect(pasoActual({ ...nada, ciclo: true, espacios: true })?.clave).toBe('planteles');
    expect(pasoActual(Object.fromEntries(PASOS.map((p) => [p.clave, true])) as typeof nada)).toBeNull();
});
test('avance cuenta los pasos hechos', () => {
    expect(avance({ ...nada, ciclo: true, planteles: true })).toEqual({ hechos: 2, total: 9 });
});
test('se abre sola solo si faltan pasos y no se cerró', () => {
    expect(abreSola({ hechos: nada, cerrada: false })).toBe(true);
    expect(abreSola({ hechos: nada, cerrada: true })).toBe(false);
    expect(abreSola({ hechos: Object.fromEntries(PASOS.map((p) => [p.clave, true])) as typeof nada, cerrada: false })).toBe(false);
});
```

- [ ] **Step 2: Correrlas y ver que fallan**

Run: `cd apps/web && npm run build && npm run test`

Expected: FAIL, porque `@/lib/guia` no existe.

- [ ] **Step 3: Implementar `lib/guia.ts`**

- **Textos:** `titulo`, `queHacer` y `paraQue`, copiados de las dos tablas de la spec.
- **`destino`** es el texto del botón «Ir a …»: Ciclos, Planteles, Espacios, Plantillas, Planes, Grupos, Profesores, Carga académica y Generar horario.
- **`ir`** es la ruta de Wayfinder de la columna «Ir a» de la spec: `index` de `@/routes/ciclos`, `planteles`, `espacios`, `plantillas`, `planes`, `grupos`, `profesores` y `cursos`, y `create` de `@/routes/corridas`.

- [ ] **Step 4: Correr todo**

Run: `cd apps/web && npm run test && composer ci:check`

Expected: pasan.

- [ ] **Step 5: Commit**

```bash
git add apps/web/resources/js/lib/guia.ts apps/web/resources/js/lib/guia.test.ts apps/web/resources/js/types
git commit -m "feat(web): pasos y textos de la guía en el navegador"
```

---

### Task 4: El panel, el botón «Guía» y el resaltado

**Files:**
- Create: `apps/web/resources/js/composables/useGuia.ts`
- Create: `apps/web/resources/js/components/GuiaPanel.vue`
- Create: `apps/web/resources/js/components/NavGuia.vue`
- Modify: `apps/web/resources/js/layouts/app/AppSidebarLayout.vue` (monta `GuiaPanel`)
- Modify: `apps/web/resources/js/components/AppSidebar.vue` (`NavGuia` en `SidebarFooter`, antes de `NavFooter`)
- Modify: `apps/web/resources/css/app.css` (clase `guia-resaltado`)
- Modify: estos 12 elementos de 11 páginas de `apps/web/resources/js/pages/escuela/`, agregándoles `data-guia="<clave>"`:

  | Página | Elemento | `data-guia` |
  |---|---|---|
  | `ciclos/Index` | «Nuevo ciclo» | `ciclo` |
  | `planteles/Index` | «Nuevo plantel» | `planteles` |
  | `espacios/Index` | «Nuevo espacio» | `espacios` |
  | `plantillas/Index` | «Nueva plantilla» | `plantilla` |
  | `plantillas/Franjas` | «Generar franjas» | `plantilla` |
  | `planes/Index` | «Nuevo plan» | `materias` |
  | `materias/Index` | «Nueva materia» | `materias` |
  | `grupos/Index` | «Nuevo grupo» | `grupos` |
  | `profesores/Index` | «Nuevo profesor» | `profesores` |
  | `cursos/Index` | «Nuevo curso» | `carga` |
  | `corridas/Index` | «Generar horario» | `generar` |
  | `corridas/Create` | «Generar» | `generar` |

**Interfaces:**
- Consumes: `PASOS`, `pasoActual`, `avance` y `abreSola` (Tarea 3); `update` de `@/routes/guia` (Tarea 2).
- Produces: `useGuia(): { guia: ComputedRef<Guia | null>; abierto: Ref<boolean>; minimizado: Ref<boolean>; abrir(): void; cerrar(): void; alternar(): void }`. El estado vive a nivel de módulo, así que el panel y el botón comparten el mismo.

- [ ] **Step 1: Implementar `useGuia`**

- **`abierto`** se inicia una sola vez con `abreSola(guia)`.
- **`abrir()` y `cerrar()`** cambian el estado al momento y llaman a `router.put(update().url, { abierta }, { preserveState: true, preserveScroll: true, only: ['guia'] })`.
  - `abrir()` también pone `minimizado` en `false`.
  - `alternar()` llama a `abrir()` o a `cerrar()` según `abierto`.
- **Resaltado:**
  - quita `guia-resaltado` de todo `[data-guia]`;
  - si `abierto` es verdadero, la pone en `[data-guia="<paso actual>"]`;
  - corre en `router.on('navigate')`, registrado una sola vez, y al cambiar `abierto` o el paso actual. Usa `nextTick`, para que la página ya esté pintada.

- [ ] **Step 2: `GuiaPanel.vue`**

- **Solo se pinta** si hay `guia` y `abierto`.
- **Posición:** `fixed` abajo a la derecha (`w-80`, `z-50`); por debajo de `sm`, a lo ancho abajo.
- **`role="region"`** con `aria-label="Guía de primeros pasos"`.
- **Arriba:** «Primeros pasos · N de 9» y una barra de avance.
- **El paso actual:** título, `queHacer`, `paraQue` y un botón `Link` «Ir a {destino}».
- **La lista de los 9 pasos:** como `Link`; los hechos con `Check` de @lucide/vue.
- **Si `hechos.ciclo`:** el atajo «¿Ya tienes tus datos en Excel? Importar todo», con `index` de `@/routes/importar`.
- **Botones** con `aria-label`: minimizar (`Minus`) y cerrar (`X`). Minimizado, queda una pastilla «Guía N/9» que lo vuelve a expandir.
- **Sin paso actual:** muestra «Listo: ya generaste tu primer horario».

- [ ] **Step 3: `NavGuia.vue`**

- `SidebarMenu`, `SidebarMenuItem` y `SidebarMenuButton`, con un ícono de @lucide/vue (`ListChecks`), «Guía» y el avance «N/9».
- Al dar clic llama a `alternar()`.
- Se pinta solo si hay `guia`.

- [ ] **Step 4: Las marcas y el CSS**

- Las 12 marcas de la tabla.
- `.guia-resaltado`: `outline` de 2px con `var(--ring)`, `outline-offset: 3px` y una animación de pulso de 1.6 s.
- `@media (prefers-reduced-motion: reduce)` quita la animación y deja el borde.

- [ ] **Step 5: Correr todo**

Run: `cd apps/web && npm run build && composer ci:check && npm run test`

Expected: pasan.

- [ ] **Step 6: Revisión en local con la imagen de producción**

Base vacía, imagen y contenedor en el puerto 80, como en `docs/despliegue.md`. En el navegador, registra una escuela nueva y comprueba:

1. **Al entrar:**
   - el panel se abre solo, en «Primeros pasos · 0 de 9»;
   - «Ir a Ciclos» lleva a ciclos;
   - «Nuevo ciclo» tiene el borde.
2. **Al crear el ciclo:** 1 de 9, el paso actual es «Agrega tus planteles» y aparece el atajo de Excel.
3. **En Planteles:** se resalta «Nuevo plantel» y ya no queda ningún borde viejo (Review Focus 1).
4. **En una pantalla sin botón del paso actual,** por ejemplo Materias: no se resalta nada y no hay errores en la consola (Review Focus 5).
5. **Minimizar:** queda la pastilla «Guía 1/9»; al darle clic, se expande.
6. **Cerrar y recargar:** no se abre sola. El botón «Guía» de la barra lateral la reabre.
7. **Tamaño `mobile`:** el panel va a lo ancho abajo y no tapa «Guardar» en el formulario de plantel (Review Focus 2).

- [ ] **Step 7: Commit**

```bash
git add apps/web/resources
git commit -m "feat(web): panel de la guía de primeros pasos con resaltado"
```
