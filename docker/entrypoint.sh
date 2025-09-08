#!/usr/bin/env bash
set -e

# Generar APP_KEY si falta
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" ]; then
  php artisan key:generate --force || true
fi

# Limpiar caches (si falla, no detengas)
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true
php artisan cache:clear  || true

# ¿Resetear BD completamente?
if [ "${DB_RESET:-false}" = "true" ]; then
  echo "[entrypoint] >>> Ejecutando migrate:fresh (drop ALL + migrate)"
  php artisan migrate:fresh --force --no-interaction
else
  echo "[entrypoint] >>> Ejecutando migrate (incremental)"
  php artisan migrate --force --no-interaction || true
fi

# Seeders opcionales
if [ "${RUN_SEED:-false}" = "true" ]; then
  # Por defecto, asumimos que SEED_CLASS va dentro de Database\Seeders
  if [ -z "${SEED_CLASS}" ]; then
    SEED_CLASS="AdminUserSeeder"
  fi

  if [ "${SEED_IS_FQCN:-false}" = "true" ]; then
    # ya viene con namespace completo
    FQCN="${SEED_CLASS}"
  else
    # anteponemos el namespace estándar
    FQCN="Database\\Seeders\\${SEED_CLASS}"
  fi

  echo "[entrypoint] >>> Ejecutando seeder: ${FQCN}"
  php artisan db:seed --class="${FQCN}" --force
fi

# Opcional: volver a cachear config/rutas para prod
php artisan config:cache || true
php artisan route:cache  || true

# Lanzar Apache
exec apache2-foreground
