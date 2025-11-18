#!/bin/bash

# ============================================
# Script de instalación de Supervisor Workers
# Casa Bonita - Droplet Production
# ============================================

echo "🚀 Instalando y configurando Supervisor para Workers 24/7"
echo "=========================================================="
echo ""

# 1. Instalar Supervisor (si no está instalado)
echo "1. Instalando Supervisor..."
sudo apt-get update
sudo apt-get install -y supervisor

# 2. Crear directorio de logs si no existe
echo ""
echo "2. Creando directorios de logs..."
sudo mkdir -p /var/www/html/casaBonita_api/storage/logs
sudo chown -R www-data:www-data /var/www/html/casaBonita_api/storage

# 3. Copiar configuración de Supervisor
echo ""
echo "3. Copiando configuración de Supervisor..."
sudo cp /var/www/html/casaBonita_api/supervisor_workers.conf /etc/supervisor/conf.d/casabonita-workers.conf

# 4. Recargar configuración de Supervisor
echo ""
echo "4. Recargando Supervisor..."
sudo supervisorctl reread
sudo supervisorctl update

# 5. Iniciar workers
echo ""
echo "5. Iniciando workers..."
sudo supervisorctl start casabonita-worker:*
sudo supervisorctl start casabonita-bonus-worker:*
sudo supervisorctl start casabonita-scheduler:*

# 6. Verificar estado
echo ""
echo "6. Verificando estado de workers..."
sudo supervisorctl status

echo ""
echo "✅ ¡Instalación completada!"
echo ""
echo "📋 Comandos útiles:"
echo "   - Ver status:    sudo supervisorctl status"
echo "   - Reiniciar:     sudo supervisorctl restart casabonita-worker:*"
echo "   - Detener:       sudo supervisorctl stop casabonita-worker:*"
echo "   - Ver logs:      tail -f /var/www/html/casaBonita_api/storage/logs/worker.log"
echo "   - Recargar:      sudo supervisorctl reread && sudo supervisorctl update"
echo ""
