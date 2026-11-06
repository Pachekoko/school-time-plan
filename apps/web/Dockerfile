# La web (Laravel + Inertia) con nginx, php-fpm y el worker de la cola bajo supervisord.
# Se construye desde la raíz del repo, porque también copia contracts/ (la demo):
#   docker build -f apps/web/Dockerfile -t horarios-web .

FROM php:8.4-fpm-bookworm AS base

# Extensiones que pide composer.lock (más pdo_pgsql, pcntl para el timeout de la cola y
# opcache); las bibliotecas de compilación se quitan al final.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx supervisor; \
    guardados="$(apt-mark showmanual)"; \
    apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libpq-dev libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd opcache pcntl pdo_pgsql zip; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $guardados > /dev/null; \
    find /usr/local -type f -name '*.so*' -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
# El mismo npm que generó package-lock.json: desde 11.19, `npm ci` pide react (peer de ink, que
# trae @laravel/multiplex del starter kit como opcional) y el lock no lo tiene.
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && npm install -g npm@11.6.2 --no-audit --no-fund

WORKDIR /app/apps/web

# Primero las dependencias (capas que casi no cambian), luego el código.
COPY apps/web/composer.json apps/web/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY apps/web/package.json apps/web/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY apps/web ./
# El build de Vite corre `php artisan wayfinder:generate`, por eso esta etapa tiene PHP y Node.
ENV VITE_APP_NAME=Horarios
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && npm run build \
    && rm -rf node_modules

FROM base

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/^worker_processes auto;/worker_processes 1;/' /etc/nginx/nginx.conf \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY apps/web/docker/php.ini /usr/local/etc/php/conf.d/zz-horarios.ini
COPY apps/web/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-horarios.conf
COPY apps/web/docker/nginx.conf /etc/nginx/sites-available/default
COPY apps/web/docker/supervisord.conf /etc/supervisor/horarios.conf
COPY apps/web/docker/arrancar.sh /usr/local/bin/arrancar
RUN chmod 755 /usr/local/bin/arrancar

# La demo del plan free; demo:cargar la busca relativa a apps/web.
COPY contracts /app/contracts
COPY --from=build --chown=www-data:www-data /app/apps/web /app/apps/web
WORKDIR /app/apps/web

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DEMO_ARCHIVO=../../contracts/fixtures/demo/prepa-horizonte-chica.json

EXPOSE 10000
CMD ["arrancar"]
