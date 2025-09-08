#!/usr/bin/env bash
set -euo pipefail

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $*"; }

# ---- Espera opcional por la DB (si lo necesitas) ----
# sleep 10

# ---- APP KEY ----
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" ]; then
  log "Generando APP_KEY..."
  php artisan key:generate --force || true
fi

# ---- Limpieza de cachés ----
log "Limpiando cachés de Laravel..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear  || true
php artisan cache:clear || true

# ---- Reset total de BD si se pide ----
DB_RESET="${DB_RESET:-false}"                # true/false
DB_RESET_CONFIRM="${DB_RESET_CONFIRM:-}"     # I_UNDERSTAND (solo requerido en prod)
APP_ENVIRONMENT="${APP_ENV:-local}"

if [ "${DB_RESET}" = "true" ]; then
  if [ "${APP_ENVIRONMENT}" = "production" ] && [ "${DB_RESET_CONFIRM}" != "I_UNDERSTAND" ]; then
    log "ADVERTENCIA: DB_RESET=true en producción pero falta DB_RESET_CONFIRM=I_UNDERSTAND. Saltando reset."
  else
    log ">>> Ejecutando migrate:fresh (eliminar TODAS las tablas y migrar desde cero)..."
    # Puedes añadir --drop-views --drop-types si usas MySQL con vistas/tipos
    php artisan migrate:fresh --force --no-interaction
    RESET_DONE="true"
  fi
fi

# ---- Migraciones normales si no hubo reset ----
if [ "${DB_RESET:-false}" != "true" ] || [ "${RESET_DONE:-false}" != "true" ]; then
  log "Ejecutando migraciones pendientes..."
  php artisan migrate --force --no-interaction || true
fi

# ---- Seeders (opcional) ----
# Si quieres permitir una lista de seeders: SEED_CLASSES="Database\\Seeders\\AdminUserSeeder,Database\\Seeders\\Otra"
RUN_SEED="${RUN_SEED:-false}"
SEED_CLASSES_DEFAULT="Database\\Seeders\\AdminUserSeeder"
SEED_CLASSES="${SEED_CLASSES:-$SEED_CLASSES_DEFAULT}"

if [ "${RUN_SEED}" = "true" ]; then
  IFS=',' read -ra SEED_LIST <<< "${SEED_CLASSES}"
  for CLASS in "${SEED_LIST[@]}"; do
    CLASS_TRIMMED="$(echo "$CLASS" | xargs)"
    if [ -n "$CLASS_TRIMMED" ]; then
      log ">> Ejecutando seeder: ${CLASS_TRIMMED}"
      php artisan db:seed --class="${CLASS_TRIMMED}" --force
    fi
  done
fi

# ---- (Opcional) Optimización final ----
log "Optimizando framework..."
php artisan config:cache || true
php artisan route:cache  || true
php artisan view:cache   || true

# ---- Arranca Apache en primer plano ----
log "Iniciando Apache..."
exec apache2-foreground
