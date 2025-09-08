#!/usr/bin/env bash
set -e

# Espera opcional por DB si lo necesitas (10s)
# sleep 10

# Si no hay APP_KEY, genéralo
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" ]; then
  php artisan key:generate --force || true
fi

# Limpia cachés de Laravel
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Ejecuta migraciones (no-interactivo, no revienta si no hay cambios)
php artisan migrate --force --no-interaction || true

# Seeder de Admin
if [ "${RUN_SEED:-false}" = "true" ]; then
  echo ">> Ejecutando AdminUserSeeder"
  php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force
fi


# Arranca Apache en primer plano
exec apache2-foreground
