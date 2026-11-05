#!/bin/sh
# Arranque del contenedor: prepara Laravel, migra, carga la demo si se pide y deja todo a
# supervisord. Corre en cada arranque (en el plan free, cada vez que el servicio despierta).
set -eu
cd /app/apps/web

# Render genera APP_KEY como texto aleatorio (generateValue); Laravel pide «base64:» con 32
# bytes, así que se deriva una de ese texto.
case "${APP_KEY:-}" in
    base64:*) ;;
    "") echo "Falta APP_KEY" >&2; exit 1 ;;
    *) APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY"), true));')"; export APP_KEY ;;
esac
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT:-10000}}}"

sed -i "s/__PORT__/${PORT:-10000}/g" /etc/nginx/sites-available/default

php artisan optimize
php artisan migrate --force

# La escuela demo, solo la primera vez (si ya existe, demo:cargar lo dice y no toca nada).
if [ -n "${DEMO_PASSWORD:-}" ]; then
    php artisan demo:cargar --archivo="${DEMO_ARCHIVO}" --password="${DEMO_PASSWORD}" || true
fi

chown -R www-data:www-data storage bootstrap/cache
exec supervisord -c /etc/supervisor/horarios.conf
