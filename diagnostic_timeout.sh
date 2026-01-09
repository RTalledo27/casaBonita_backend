#!/bin/bash
# Script de Diagnóstico para Error 504 Gateway Timeout
# Ejecutar en producción: bash diagnostic_timeout.sh

echo "==================================="
echo "🔍 DIAGNÓSTICO DE TIMEOUT 504"
echo "==================================="
echo ""

# 1. Verificar configuración de Nginx
echo "📋 1. CONFIGURACIÓN NGINX:"
echo "-----------------------------------"
if [ -f /etc/nginx/sites-available/api.casabonita.pe ]; then
    echo "✓ Archivo encontrado"
    echo ""
    echo "Buscando timeouts configurados:"
    grep -i "timeout" /etc/nginx/sites-available/api.casabonita.pe | grep -v "#"
    echo ""
    echo "Buscando proxy/fastcgi settings:"
    grep -E "(fastcgi_read_timeout|proxy_read_timeout|fastcgi_pass)" /etc/nginx/sites-available/api.casabonita.pe | grep -v "#"
else
    echo "⚠️ Archivo no encontrado en esa ubicación"
    echo "Buscando archivos de configuración..."
    find /etc/nginx -name "*casabonita*" -o -name "*api*"
fi
echo ""

# 2. Verificar PHP-FPM
echo "📋 2. CONFIGURACIÓN PHP-FPM:"
echo "-----------------------------------"
PHP_VERSION=$(php -v | head -n 1 | cut -d' ' -f2 | cut -d'.' -f1,2)
echo "Versión PHP detectada: $PHP_VERSION"
echo ""

FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
if [ -f "$FPM_POOL" ]; then
    echo "✓ Pool encontrado: $FPM_POOL"
    echo ""
    echo "Buscando request_terminate_timeout:"
    grep "request_terminate_timeout" "$FPM_POOL" | grep -v ";"
    echo ""
    echo "Buscando memory_limit:"
    grep "memory_limit" "$FPM_POOL" | grep -v ";"
else
    echo "⚠️ Pool no encontrado. Buscando..."
    find /etc/php -name "www.conf"
fi
echo ""

# 3. Verificar PHP.ini
echo "📋 3. CONFIGURACIÓN PHP.INI:"
echo "-----------------------------------"
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    echo "✓ php.ini encontrado"
    echo ""
    echo "max_execution_time:"
    grep "^max_execution_time" "$PHP_INI"
    echo ""
    echo "max_input_time:"
    grep "^max_input_time" "$PHP_INI"
    echo ""
    echo "memory_limit:"
    grep "^memory_limit" "$PHP_INI"
else
    echo "⚠️ php.ini no encontrado"
fi
echo ""

# 4. Verificar estado de servicios
echo "📋 4. ESTADO DE SERVICIOS:"
echo "-----------------------------------"
echo "Nginx:"
systemctl is-active nginx && echo "✓ Activo" || echo "✗ Inactivo"
echo ""
echo "PHP-FPM:"
systemctl is-active php${PHP_VERSION}-fpm && echo "✓ Activo" || echo "✗ Inactivo"
echo ""

# 5. Verificar logs recientes
echo "📋 5. LOGS RECIENTES (últimas 20 líneas):"
echo "-----------------------------------"
echo ""
echo "Laravel Log:"
if [ -f /var/www/casabonita_api/storage/logs/laravel.log ]; then
    tail -20 /var/www/casabonita_api/storage/logs/laravel.log
else
    echo "⚠️ Log no encontrado"
fi
echo ""

echo "Nginx Error Log:"
if [ -f /var/log/nginx/error.log ]; then
    tail -20 /var/log/nginx/error.log | grep -i "timeout\|504\|upstream"
else
    echo "⚠️ Log no encontrado"
fi
echo ""

# 6. Test de timeout con PHP
echo "📋 6. TEST DE LÍMITES PHP:"
echo "-----------------------------------"
cd /var/www/casabonita_api
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . 's\n';"
php -r "echo 'memory_limit: ' . ini_get('memory_limit') . '\n';"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . '\n';"
echo ""

# 7. Verificar configuración nginx activa
echo "📋 7. CONFIGURACIÓN NGINX ACTIVA:"
echo "-----------------------------------"
nginx -T 2>&1 | grep -A 5 "server_name.*casabonita" | head -20
echo ""

# 8. Recomendaciones
echo "==================================="
echo "📝 RECOMENDACIONES:"
echo "==================================="
echo ""
echo "Si NO ves timeouts de 600s arriba:"
echo "1. Edita nginx: sudo nano /etc/nginx/sites-available/api.casabonita.pe"
echo "2. Agrega en location /:"
echo "   fastcgi_read_timeout 600s;"
echo "   fastcgi_send_timeout 600s;"
echo "3. Guarda y ejecuta: sudo nginx -t && sudo systemctl reload nginx"
echo ""
echo "Si NO ves request_terminate_timeout = 600:"
echo "1. Edita pool: sudo nano /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
echo "2. Agrega: request_terminate_timeout = 600"
echo "3. Ejecuta: sudo systemctl restart php${PHP_VERSION}-fpm"
echo ""
echo "==================================="
