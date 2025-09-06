#!/bin/bash

# Deploy Script para Casa Bonita Backend API
# Uso: ./deploy.sh [production|staging]

set -e  # Salir si cualquier comando falla

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para logging
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar argumentos
ENVIRONMENT=${1:-production}

if [[ "$ENVIRONMENT" != "production" && "$ENVIRONMENT" != "staging" ]]; then
    error "Ambiente inválido. Usa 'production' o 'staging'"
    exit 1
fi

log "Iniciando deploy para ambiente: $ENVIRONMENT"

# Verificar que estamos en el directorio correcto
if [[ ! -f "composer.json" ]]; then
    error "No se encontró composer.json. Asegúrate de estar en el directorio del backend."
    exit 1
fi

# Verificar que Vercel CLI está instalado
if ! command -v vercel &> /dev/null; then
    error "Vercel CLI no está instalado. Instálalo con: npm i -g vercel"
    exit 1
fi

# Verificar que Composer está instalado
if ! command -v composer &> /dev/null; then
    error "Composer no está instalado. Instálalo desde: https://getcomposer.org/"
    exit 1
fi

# Función para verificar variables de entorno requeridas
check_env_vars() {
    log "Verificando variables de entorno..."
    
    required_vars=(
        "APP_NAME"
        "APP_ENV"
        "APP_KEY"
        "DB_CONNECTION"
        "DB_HOST"
        "DB_DATABASE"
        "DB_USERNAME"
        "DB_PASSWORD"
    )
    
    missing_vars=()
    
    for var in "${required_vars[@]}"; do
        if ! vercel env ls | grep -q "$var"; then
            missing_vars+=("$var")
        fi
    done
    
    if [[ ${#missing_vars[@]} -gt 0 ]]; then
        error "Variables de entorno faltantes:"
        for var in "${missing_vars[@]}"; do
            echo "  - $var"
        done
        echo ""
        echo "Configúralas con: vercel env add <VARIABLE_NAME>"
        exit 1
    fi
    
    success "Variables de entorno verificadas"
}

# Función para preparar el código
prepare_code() {
    log "Preparando código para deploy..."
    
    # Verificar que no hay cambios sin commitear (solo si es un repo git)
    if [[ -d ".git" ]]; then
        if [[ -n $(git status --porcelain) ]]; then
            warning "Hay cambios sin commitear. ¿Continuar? (y/N)"
            read -r response
            if [[ ! "$response" =~ ^[Yy]$ ]]; then
                error "Deploy cancelado"
                exit 1
            fi
        fi
    fi
    
    # Instalar dependencias de producción
    log "Instalando dependencias de producción..."
    composer install --no-dev --optimize-autoloader --no-scripts
    
    # Generar autoload optimizado
    log "Optimizando autoload..."
    composer dump-autoload --optimize --classmap-authoritative
    
    success "Código preparado"
}

# Función para ejecutar tests (opcional)
run_tests() {
    if [[ -f "phpunit.xml" ]]; then
        log "¿Ejecutar tests antes del deploy? (y/N)"
        read -r response
        if [[ "$response" =~ ^[Yy]$ ]]; then
            log "Ejecutando tests..."
            if composer test; then
                success "Tests pasaron"
            else
                error "Tests fallaron. Deploy cancelado."
                exit 1
            fi
        fi
    fi
}

# Función para hacer backup de la base de datos (si es posible)
backup_database() {
    if [[ "$ENVIRONMENT" == "production" ]]; then
        warning "¿Crear backup de la base de datos antes del deploy? (Y/n)"
        read -r response
        if [[ ! "$response" =~ ^[Nn]$ ]]; then
            log "Creando backup de la base de datos..."
            # Aquí puedes agregar tu lógica de backup específica
            # Por ejemplo, usando mysqldump o pg_dump
            warning "Implementa la lógica de backup específica para tu base de datos"
        fi
    fi
}

# Función para deploy
deploy() {
    log "Iniciando deploy en Vercel..."
    
    if [[ "$ENVIRONMENT" == "production" ]]; then
        vercel --prod --yes
    else
        vercel --yes
    fi
    
    success "Deploy completado"
}

# Función para verificar el deploy
verify_deploy() {
    log "Verificando deploy..."
    
    # Obtener la URL del deploy
    if [[ "$ENVIRONMENT" == "production" ]]; then
        URL=$(vercel ls --scope=$(vercel whoami) | grep "$(basename $(pwd))" | grep "READY" | head -1 | awk '{print $2}')
    else
        URL=$(vercel ls --scope=$(vercel whoami) | grep "$(basename $(pwd))" | grep "READY" | head -1 | awk '{print $2}')
    fi
    
    if [[ -z "$URL" ]]; then
        warning "No se pudo obtener la URL del deploy automáticamente"
        return
    fi
    
    # Verificar que la API responde
    log "Verificando que la API responde en: https://$URL"
    
    if curl -f -s "https://$URL/api/health" > /dev/null 2>&1; then
        success "API responde correctamente"
    else
        warning "La API no responde en /api/health. Verifica manualmente."
    fi
    
    echo ""
    echo "🚀 Deploy completado!"
    echo "📍 URL: https://$URL"
    echo "📊 Dashboard: https://vercel.com/dashboard"
    echo "📝 Logs: vercel logs $(basename $(pwd))"
}

# Función para rollback (en caso de problemas)
rollback() {
    error "¿Algo salió mal? Puedes hacer rollback con:"
    echo "  vercel rollback"
    echo ""
    echo "O desde el dashboard de Vercel:"
    echo "  https://vercel.com/dashboard"
}

# Función principal
main() {
    echo "🏠 Casa Bonita Backend Deploy Script"
    echo "===================================="
    echo ""
    
    # Verificar login en Vercel
    if ! vercel whoami > /dev/null 2>&1; then
        log "No estás logueado en Vercel. Ejecutando login..."
        vercel login
    fi
    
    # Ejecutar pasos del deploy
    check_env_vars
    prepare_code
    run_tests
    backup_database
    
    # Confirmación final
    if [[ "$ENVIRONMENT" == "production" ]]; then
        warning "¿Estás seguro de que quieres hacer deploy a PRODUCCIÓN? (y/N)"
        read -r response
        if [[ ! "$response" =~ ^[Yy]$ ]]; then
            error "Deploy cancelado"
            exit 1
        fi
    fi
    
    deploy
    verify_deploy
    
    success "¡Deploy completado exitosamente!"
}

# Manejar interrupciones
trap 'error "Deploy interrumpido"; rollback; exit 1' INT TERM

# Ejecutar función principal
main "$@"