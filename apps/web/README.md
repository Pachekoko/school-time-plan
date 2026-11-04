# Web (`apps/web`)

Aplicación Laravel 13 + Inertia 3 + Vue 3 (starter kit oficial de Vue) para capturar o importar los datos de cada escuela, generar horarios con el motor (`apps/solver`) y verlos.

## Desarrollo local

Requisitos: PHP 8.3+ con `pdo_pgsql`, Composer, Node 22+ y Docker.

```bash
# Postgres (puerto 5433 del host) y el motor (puerto 8000), desde la raíz del repo
docker compose up -d postgres solver

cd apps/web
cp .env.example .env
php artisan key:generate
composer install && npm install
php artisan migrate
composer dev                                 # servidor, Vite y logs
php artisan queue:work --timeout=1000        # en otra terminal: el job que llama al motor
```

`.env.example` apunta a ese Postgres (`DB_PORT=5433`) y al motor (`SOLVER_URL=http://localhost:8000`, `SOLVER_TOKEN=dev`). Para trabajar sin Docker, usa `DB_CONNECTION=sqlite`.

## Pruebas y calidad

```bash
composer ci:check   # oxlint/oxfmt (vp check), vue-tsc, Pint, PHPStan y PHPUnit
npm run test        # Vitest (vp test)
```

Las pruebas de PHP usan SQLite en memoria (`phpunit.xml`).
