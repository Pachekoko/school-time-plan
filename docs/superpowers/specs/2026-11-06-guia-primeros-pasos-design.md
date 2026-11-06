# Guía de primeros pasos — diseño

Fecha: 2026-11-06. Extiende la web de la fase 2 (`apps/web`); el spec general es [`2026-11-04-generador-horarios-design.md`](2026-11-04-generador-horarios-design.md).

## Objetivo

El admin de una escuela nueva aprende a usar la app mientras captura sus propios datos. La guía lo lleva en el orden en que la app los necesita y avanza sola cuando un paso ya tiene datos. Se puede abrir siempre que se quiera.

**Éxito:** una escuela sin datos llega a su primer horario siguiendo solo la guía, sin otra documentación.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Cómo enseña | Acompaña la captura: un paso está hecho cuando hay datos, no cuando se da «Siguiente». |
| Forma | Panel flotante que sigue al usuario por las pantallas y resalta el botón que toca. |
| Apertura | Se abre sola mientras falten pasos y no se haya cerrado. Al cerrarla, solo la abre el botón «Guía». |
| Construcción | Propia, sin librerías: el avance se calcula en el servidor y el panel es de Vue. |
| Quién la ve | Solo el rol admin. |

## Lo que ve el admin

- **Botón «Guía»:** en el pie de la barra lateral, arriba de «Repositorio», con el avance («2/9»). Abre y cierra el panel.
- **Panel flotante:** abajo a la derecha; en pantallas angostas, a lo ancho abajo.
  - Encabezado «Primeros pasos · N de 9» con barra de avance.
  - El paso actual (el primero pendiente) destacado: título, qué hacer, para qué sirve y un botón «Ir a …» a su pantalla.
  - La lista de los 9 pasos: hechos con ✓, pendientes en gris. Cada uno es un enlace a su pantalla.
  - Desde el paso 2 (ya hay ciclo actual), el atajo «¿Ya tienes tus datos en Excel? Importar todo», que lleva a `importar.index`.
  - Botones para minimizar (queda una pastilla con el avance) y para cerrar.
  - Con los 9 pasos hechos, muestra «Listo: ya generaste tu primer horario».
- **Resaltado:** en la pantalla del paso actual, los elementos marcados con `data-guia="<paso>"` llevan un borde que late. Con `prefers-reduced-motion: reduce`, el borde no se mueve.

## Los 9 pasos

«Ciclo» es el ciclo actual de la escuela (`escuelas.ciclo_actual_id`).

| # | Clave | Título | Hecho cuando hay… | «Ir a» | Elementos resaltados |
|---|---|---|---|---|---|
| 1 | `ciclo` | Crea tu ciclo escolar | ciclo actual | `ciclos.index` | «Nuevo ciclo» |
| 2 | `planteles` | Agrega tus planteles | al menos 1 plantel | `planteles.index` | «Nuevo plantel» |
| 3 | `espacios` | Registra tus espacios | al menos 1 espacio | `espacios.index` | «Nuevo espacio» |
| 4 | `plantilla` | Arma tu plantilla horaria | al menos 1 franja de tipo clase | `plantillas.index` | «Nueva plantilla» y «Generar franjas» |
| 5 | `materias` | Captura tu plan y sus materias | al menos 1 materia | `planes.index` | «Nuevo plan» mientras no hay planes, después «Materias» en Planes; y «Nueva materia» |
| 6 | `grupos` | Crea tus grupos | al menos 1 grupo en el ciclo | `grupos.index` | «Nuevo grupo» |
| 7 | `profesores` | Agrega a tus profesores | al menos 1 profesor | `profesores.index` | «Nuevo profesor» |
| 8 | `carga` | Asigna la carga académica | al menos 1 curso en el ciclo | `cursos.index` | «Nuevo curso» |
| 9 | `generar` | Genera tu horario | una corrida del ciclo en `ok` o `factible_parcial` | `corridas.create` | «Generar horario» y «Generar» |

El panel muestra, para el paso actual, qué hacer y para qué sirve:

| Clave | Qué hacer | Para qué sirve |
|---|---|---|
| `ciclo` | Crea el periodo que vas a programar, por ejemplo 2027-A. El primero queda como actual. | Los grupos, la carga y los horarios son de un ciclo. |
| `planteles` | Agrega cada sede de tu escuela; si solo tienes una, crea esa. | Los espacios y los grupos pertenecen a un plantel. |
| `espacios` | Aulas, laboratorios y canchas de cada plantel, con su capacidad. | El horario asigna un espacio a cada clase. |
| `plantilla` | Crea una plantilla y genera sus franjas: días, horas de clase y recesos. | Las clases solo se acomodan en esas franjas. |
| `materias` | Crea tu plan de estudios y después sus materias, con sus horas por semana. | La carga académica se arma con estas materias. |
| `grupos` | Crea los grupos del ciclo con su plantel, plantilla, semestre y alumnos. | Cada grupo recibe su propio horario. |
| `profesores` | Agrega a tus profesores con las materias que pueden dar. Su disponibilidad es opcional. | El motor solo les asigna lo que pueden dar y cuando pueden. |
| `carga` | Indica qué materia lleva cada grupo y quién la da. | Es lo que el motor acomoda en el horario. |
| `generar` | Revisa las reglas y genera; tarda unos minutos. | El motor acomoda todo sin choques y te muestra el resultado. |

## Cómo funciona

### Servidor

- **`App\Guia\PrimerosPasos::hechos(Escuela $escuela): array<string, bool>`:** devuelve las 9 claves en orden con su estado, calculado con **una sola consulta** (subconsultas `EXISTS` filtradas por `escuela_id` y por el ciclo actual). Sin ciclo actual, los pasos que dependen de él (`grupos`, `carga`, `generar`) quedan en falso.
- **Columna `users.guia_cerrada_en`:** timestamp nullable, con su migración y el cast `datetime` en `User`.
- **Ruta `PUT /guia`** (`guia.update`), dentro del grupo `auth` + `escuela` + `rol:admin`:
  - recibe `abierta` (booleano obligatorio);
  - si es `false`, guarda `guia_cerrada_en = now()`; si es `true`, lo pone en `null`;
  - responde 204 sin contenido: guardarlo no cambia la página.
- **Prop compartida `guia`** en `HandleInertiaRequests`:
  - para un admin con escuela: `{ hechos: Record<clave, boolean>, cerrada: boolean }`;
  - para cualquier otro rol: `null`.

  Cada visita o envío de formulario la recalcula, así que el panel se actualiza sin lógica extra.

### Navegador

- **`resources/js/lib/guia.ts`:**
  - `PASOS`: por cada clave, título, qué hacer, para qué y ruta de «Ir a» (Wayfinder). Dónde se resalta no se configura: lo dicen las marcas `data-guia` de cada página.
  - Funciones puras: `pasoActual(hechos)` (el primero pendiente o `null`), `avance(hechos)` (`{ hechos, total }`) y `abreSola(guia)` (`!cerrada && hay pendientes`).
- **`resources/js/composables/useGuia.ts`:**
  - estado compartido del panel (`abierto`, `minimizado`), iniciado con `abreSola`;
  - `abrir()` y `cerrar()`, que mandan `PUT /guia` con `useHttp`, sin visita de Inertia: no cambian la página ni cortan otra petición;
  - el resaltado: pone `data-guia-paso="<paso actual>"` en `<html>`, y el CSS le da el borde a cada `[data-guia]` de ese paso, también a los que se montan después.
- **`GuiaPanel.vue`:** el panel, montado en `AppSidebarLayout.vue` solo si hay `guia`.
- **`NavGuia.vue`:** el botón de la barra lateral, en `AppSidebar.vue`.
- **Marcas `data-guia` en los 13 elementos de la tabla:** en las páginas `Index` de cada catálogo, en `plantillas/Franjas.vue` y en las dos de corridas. En Planes, «Nuevo plan» lleva la marca solo mientras no hay planes, y el botón «Materias» aparece cuando ya hay.
- **Estilos en `app.css`:** una regla por paso, `:root[data-guia-paso="<paso>"] [data-guia="<paso>"]`, con el anillo; el pulso solo va sin movimiento reducido.

## Casos raros

| Caso | Qué pasa |
|---|---|
| Importa el Excel | Varios pasos se marcan de golpe y la guía salta al primero pendiente. |
| Borra datos | El paso vuelve a quedar pendiente. |
| Sin ciclo actual | Las pantallas de grupos y carga ya redirigen a ciclos; la guía marca primero el paso 1. |
| Profesor o alumno | `guia` es `null`: no hay panel ni botón. |
| Falla `PUT /guia` | El panel se cierra o abre igual en esta visita; en la siguiente manda el servidor. |
| El botón resaltado no está en la página | No se resalta nada; el panel sigue normal. |

## Pruebas

Cada prueba se escribe primero y se ve fallar.

- **`PrimerosPasosTest`** (PHPUnit):
  - una escuela vacía tiene los 9 pasos pendientes;
  - `EscuelaDePrueba` tiene 8 hechos y `generar` pendiente;
  - una corrida `ok` completa los 9; una corrida `error` no cuenta;
  - los datos de otra escuela no cuentan;
  - el cálculo hace una sola consulta (`DB::getQueryLog`).
- **`GuiaTest`** (PHPUnit):
  - el admin recibe `guia` y el profesor recibe `null`;
  - `PUT /guia` con `abierta: false` guarda la fecha y con `true` la borra;
  - un profesor recibe 403 en `PUT /guia`.
- **`lib/guia.test.ts`** (Vitest): `pasoActual`, `avance` y `abreSola`.
- **Revisión en local** con la imagen de producción y el navegador, en una escuela recién registrada:
  - se abre sola y resalta «Nuevo ciclo»;
  - al crear el ciclo y un plantel, avanza;
  - al cerrarla ya no vuelve sola, y el botón «Guía» la reabre;
  - minimizar deja la pastilla con el avance.

## Fuera de alcance

- Guía para el profesor.
- Pasos opcionales: sincronías, disponibilidad detallada, reglas de la corrida.
- Oscurecer la pantalla o globos anclados a botones.
- Guardar en qué paso se quedó el usuario: el paso actual siempre se deduce de los datos.

## Entrega

Un PR contra `main` con esta spec, su plan y el código, probado en local con la imagen de producción antes de subirlo.
