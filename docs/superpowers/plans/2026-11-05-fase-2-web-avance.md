# Fase 2 — estado y pendientes

Corte: 2026-11-05. Plan: [`2026-11-05-fase-2-web.md`](2026-11-05-fase-2-web.md). Spec: [`2026-11-04-generador-horarios-design.md`](../specs/2026-11-04-generador-horarios-design.md).

## Dónde va

- **Rama `feat/fase-2-web`:** las 14 tareas del plan están hechas.
- **Revisión final:** un revisor nuevo encontró 1 hallazgo crítico y 7 importantes. Los 8 se arreglaron, cada uno con una prueba que primero falló. Pasan 212 pruebas en SQLite y en PostgreSQL.
- **Despliegue:** listo con `apps/web/Dockerfile` y `render.yaml`, para la web en el plan free de Render con la base en Neon. Los pasos están en [`docs/despliegue.md`](../../despliegue.md).
- **Demo pública:** usa la demo chica (`contracts/fixtures/demo/prepa-horizonte-chica.json`). La completa no cabe en el motor del plan free.

## Pendiente

1. Push, PR y merge a `main`. Hay que ver el job nuevo de PostgreSQL en CI.
2. Crear la base en Neon y el servicio en Render (`docs/despliegue.md`).
3. Decidir el correo: un proveedor SMTP, o solo la cuenta demo. Hoy los correos salen en los logs.

### Decisión tuya

- **`httpx` o `httpx2` en `apps/solver`:** Starlette sugiere `httpx2` para el `TestClient`.
  - No se instaló porque el nombre salió de un aviso y no de una fuente verificada.
  - Hoy queda `httpx`, con ese aviso filtrado en pytest.
  - Si confías en el paquete, el cambio es de una línea.

### Menores diferidos de la fase 2 (revisión final)

- M1: la web y el motor usan el puerto 8000 en local; los enlaces de verificación apuntan al motor.
- M2: un solo mensaje de falla para 401, 5xx y errores del constructor; un 5xx vuelve a resolver hasta 3 veces.
- M3: la espera de `/health` cuenta vueltas, no tiempo.
- M4: al rechazar una corrida nueva no se enlaza la que está en curso (spec §8).
- M5: `|` → `I` solo en el import de UTEG, no en el de la plantilla.
- M6: las subidas sin confirmar nunca se limpian.
- M7: el único admin puede borrar su propia cuenta.
- M8: `Borrado` revisa y luego borra; un insert concurrente sale como 500.
- M10: las respuestas falsas del motor no se validan contra `solve-response.schema.json`.

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
