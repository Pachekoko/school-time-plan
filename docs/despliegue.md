# Publicar la web en Render con Neon (plan free)

La web corre en un servicio Docker de Render y su base en Neon. El motor ya es un servicio
aparte (`https://school-time-planner.onrender.com`).

Necesitas:
- El valor de `SOLVER_TOKEN` del motor (Render → el servicio del motor → Environment).
- El repo con `render.yaml` en `main`.

## 1. Neon (~5 min)

1. En <https://console.neon.tech>, crea un proyecto en la región **AWS US West 2 (Oregon)**, la misma del servicio web.
2. En **Connect**, apaga **Connection pooling** y copia la cadena de conexión. Empieza con `postgresql://` y termina con `?sslmode=require…`.

La conexión directa basta: el contenedor abre pocas conexiones y las migraciones corren mejor así.

## 2. Render (~15 min, más ~10 min del primer build)

1. Abre <https://dashboard.render.com/blueprint/new?repo=https://github.com/Pachekoko/school-time-plan>.
2. Autoriza GitHub si lo pide y deja el Blueprint en la rama `main`.
3. Llena los tres secretos:

   | Variable | Valor |
   |---|---|
   | `DB_URL` | La cadena de Neon |
   | `SOLVER_TOKEN` | El mismo del motor |
   | `DEMO_PASSWORD` | La contraseña que quieras para `admin@prepa-horizonte.test` |

4. Da clic en **Apply**. `APP_KEY` se genera solo.

## 3. Comprobar (~5 min)

1. `https://<tu-servicio>.onrender.com/up` responde 200.
2. Entra con `admin@prepa-horizonte.test` y tu `DEMO_PASSWORD`.
3. Ve a **Horarios → Generar**. Con el plan free tarda unos 2 min, y más o menos 1 min más si el motor estaba dormido.

## Qué hace el contenedor

`apps/web/docker/arrancar.sh` corre en cada arranque:

1. Convierte `APP_KEY` al formato de Laravel y toma `APP_URL` de `RENDER_EXTERNAL_URL`.
2. Corre `php artisan optimize` y `php artisan migrate --force`.
3. La primera vez, carga la escuela demo chica (`contracts/fixtures/demo/prepa-horizonte-chica.json`).
4. Deja a supervisord con nginx, php-fpm y un worker de la cola.

## Límites del plan free

- **Se duermen.** La web y el motor se duermen tras 15 min sin visitas, y despertar tarda alrededor de 1 min. La web espera al motor hasta `SOLVER_ESPERA_S` (150 s).
- **0.1 CPU y 512 MB.** La demo completa no cabe en el motor; por eso se carga la chica: 1 plantel, 4 grupos y 32 cursos. Las mediciones están en `apps/solver/README.md`.
- **Disco efímero.** Un Excel subido y no confirmado se pierde si el servicio se reinicia; vuelve a subirlo.
- **Correo.** Con `MAIL_MAILER=log`, los correos (verificar el registro, la contraseña de un profesor) solo salen en los logs del servicio. Para enviarlos de verdad, configura un SMTP con estas variables:
  - `MAIL_MAILER=smtp`
  - `MAIL_HOST`
  - `MAIL_PORT`
  - `MAIL_USERNAME`
  - `MAIL_PASSWORD`
  - `MAIL_FROM_ADDRESS`

## Probar la imagen en local

Corre esto desde la raíz del repo, con el Postgres de `docker compose`:

```bash
docker build -f apps/web/Dockerfile -t horarios-web .
docker run --rm -p 80:10000 --network timeplan_default \
  -e APP_KEY=cualquier-texto -e APP_URL=http://localhost \
  -e DB_CONNECTION=pgsql -e DB_URL=postgresql://horarios:horarios@postgres:5432/horarios \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=database \
  -e SOLVER_URL=http://solver:8000 -e SOLVER_TOKEN=dev -e SOLVER_WORKERS=1 \
  -e DEMO_PASSWORD=demo-local horarios-web
```

- **Usa el puerto 80 del host.** El nginx de Debian le pasa a PHP el host sin puerto (`HTTP_HOST $host`, por seguridad). En otro puerto, los assets apuntarían al 80. En Render no pasa, porque el proxy usa el 443.
- **Para imitar el plan free,** agrega `--cpus=0.1 --memory=512m`.
