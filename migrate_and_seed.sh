#!/bin/bash

# ============================================
# Script de Migración y Seeding - Casa Bonita
# Droplet Production
# ============================================

echo "🗄️  Migrando Base de Datos en Producción"
echo "=========================================="
echo ""

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 1. Ir al directorio del proyecto
cd /var/www/html/casaBonita_api

# 2. Verificar conexión a la base de datos
echo "1. Verificando conexión a la base de datos..."
php artisan db:show 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Conexión a la base de datos OK${NC}"
else
    echo -e "${RED}❌ Error de conexión a la base de datos${NC}"
    echo "Verifica tu archivo .env"
    exit 1
fi

echo ""

# 3. Hacer backup de la base de datos (por seguridad)
echo "2. Creando backup de la base de datos..."
BACKUP_FILE="storage/backups/db_backup_$(date +%Y%m%d_%H%M%S).sql"
mkdir -p storage/backups

# Obtener credenciales del .env
DB_HOST=$(grep DB_HOST .env | cut -d '=' -f2)
DB_DATABASE=$(grep DB_DATABASE .env | cut -d '=' -f2)
DB_USERNAME=$(grep DB_USERNAME .env | cut -d '=' -f2)
DB_PASSWORD=$(grep DB_PASSWORD .env | cut -d '=' -f2)

mysqldump -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_FILE" 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Backup creado: $BACKUP_FILE${NC}"
else
    echo -e "${YELLOW}⚠️  No se pudo crear backup (continúa de todos modos)${NC}"
fi

echo ""

# 4. Ejecutar migraciones
echo "3. Ejecutando migraciones..."
echo -e "${YELLOW}⚠️  Esto puede tomar unos minutos...${NC}"
php artisan migrate --force

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Migraciones ejecutadas exitosamente${NC}"
else
    echo -e "${RED}❌ Error al ejecutar migraciones${NC}"
    exit 1
fi

echo ""

# 5. Verificar si el usuario admin ya existe
echo "4. Verificando usuario administrador..."
ADMIN_EXISTS=$(php artisan tinker --execute="echo \Modules\Security\app\Models\User::where('email', 'admin@casabonita.com')->exists() ? 'true' : 'false';" 2>/dev/null | tail -1)

if [ "$ADMIN_EXISTS" == "true" ]; then
    echo -e "${YELLOW}⚠️  Usuario admin ya existe, omitiendo seeder${NC}"
    echo ""
    echo "📋 Credenciales existentes:"
    echo "   Email: admin@casabonita.com"
    echo "   (Si olvidaste la contraseña, usa: php artisan tinker para resetearla)"
else
    echo "5. Creando usuario administrador..."
    php artisan db:seed --class=AdminUserSeeder --force
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Usuario administrador creado${NC}"
        echo ""
        echo "📋 Credenciales del Administrador:"
        echo "   Email: admin@casabonita.com"
        echo "   Contraseña: (revisar el seeder o resetear con tinker)"
    else
        echo -e "${RED}❌ Error al crear usuario administrador${NC}"
    fi
fi

echo ""

# 6. Limpiar cachés
echo "6. Limpiando cachés..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo -e "${GREEN}✅ ¡Proceso completado!${NC}"
echo ""
echo "🔐 Acceso al sistema:"
echo "   URL: https://tu-dominio.com"
echo "   Email: admin@casabonita.com"
echo ""
echo "📝 Próximos pasos:"
echo "   1. Verifica que puedas iniciar sesión"
echo "   2. Cambia la contraseña del admin desde el panel"
echo "   3. Crea los demás usuarios necesarios"
echo ""
