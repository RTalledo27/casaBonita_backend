504 Gateway Time-out
# 🔧 Fix para Errores de Importación en Producción

## Problemas Identificados:

1. **500 Internal Server Error** en `/api/v1/inventory/external-lot-import/sales`
2. **504 Gateway Timeout** en `/api/v1/inventory/external-lot-import/sales/import`
3. **CORS Error** en importación

## Soluciones Implementadas:

### 1. Backend - Mejoras en el Controlador ✅

**Archivo:** `Modules/Inventory/app/Http/Controllers/Api/ExternalLotImportController.php`

- ✅ Validación de fechas antes de llamar al API
- ✅ Timeout extendido de 300s → 600s (10 minutos)
- ✅ Memory limit aumentado a 512M
- ✅ Mejor manejo de errores con stack trace en logs
- ✅ Información de debugging cuando `APP_DEBUG=true`

### 2. Configuración del Servidor (Producción)

#### A. Nginx - Aumentar Timeouts

**Archivo:** `/etc/nginx/sites-available/api.casabonita.pe` (o tu configuración de nginx)

Agregar dentro del bloque `location /`:

```nginx
server {
    listen 443 ssl http2;
    server_name api.casabonita.pe;

    # ... otras configuraciones ...

    location / {
        try_files $uri $uri/ /index.php?$query_string;
        
        # TIMEOUTS EXTENDIDOS PARA IMPORTACIONES
        proxy_read_timeout 600s;
        proxy_connect_timeout 600s;
        proxy_send_timeout 600s;
        
        # Si usas fastcgi_pass (PHP-FPM)
        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
        
        # Buffers para respuestas grandes
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    # ... resto de la configuración ...
}
```

**Aplicar cambios:**
```bash
sudo nginx -t  # Verificar sintaxis
sudo systemctl reload nginx
```

#### B. PHP-FPM - Aumentar Timeouts

**Archivo:** `/etc/php/8.x/fpm/pool.d/www.conf` (o tu pool específico)

```ini
; Maximum execution time
request_terminate_timeout = 600

; Memory limit (ya lo maneja PHP pero por si acaso)
php_admin_value[memory_limit] = 512M
```

**Aplicar cambios:**
```bash
sudo systemctl restart php8.2-fpm  # Ajusta la versión según tu instalación
```

#### C. PHP.ini - Timeouts Globales

**Archivo:** `/etc/php/8.x/fpm/php.ini`

```ini
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
post_max_size = 50M
upload_max_filesize = 50M
```

**Aplicar cambios:**
```bash
sudo systemctl restart php8.2-fpm
```

### 3. Comandos para Ejecutar en Producción

```bash
# 1. Ir al directorio del backend
cd /var/www/casabonita_api

# 2. Hacer pull de los cambios
git pull origin main

# 3. Limpiar caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 4. Verificar logs si hay errores
tail -f storage/logs/laravel.log

# 5. Verificar configuración de nginx
sudo nginx -t

# 6. Recargar nginx
sudo systemctl reload nginx

# 7. Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 4. Verificación Post-Deployment

#### Probar endpoint de ventas:
```bash
curl -X GET "https://api.casabonita.pe/api/v1/inventory/external-lot-import/sales?startDate=2025-01-01&endDate=2026-01-07" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Respuesta esperada:**
```json
{
  "success": true,
  "data": {
    "total": X,
    "items": [...],
    "cached_at": "...",
    "cache_expires_at": "..."
  }
}
```

#### Probar importación:
```bash
curl -X POST "https://api.casabonita.pe/api/v1/inventory/external-lot-import/sales/import" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "startDate": "2025-01-01",
    "endDate": "2026-01-07",
    "force_refresh": false
  }'
```

### 5. Monitoreo de Logs

```bash
# Ver logs en tiempo real
tail -f /var/www/casabonita_api/storage/logs/laravel.log

# Buscar errores específicos
grep -i "ExternalLotImportController" /var/www/casabonita_api/storage/logs/laravel.log | tail -50

# Ver logs de Nginx
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log

# Ver logs de PHP-FPM
sudo tail -f /var/log/php8.2-fpm.log
```

### 6. Troubleshooting

#### Si sigue el error 500:
1. Verificar logs detallados: `tail -f storage/logs/laravel.log`
2. Verificar que las fechas sean válidas
3. Verificar que el API de Logicware esté respondiendo
4. Probar con `force_refresh=false` para usar caché

#### Si sigue el error 504:
1. Verificar que nginx tenga los timeouts configurados
2. Verificar que PHP-FPM tenga `request_terminate_timeout = 600`
3. Considerar procesar en background con queue jobs

#### Si sigue el error CORS:
1. Verificar que `config/cors.php` tenga `https://app.casabonita.pe`
2. Ejecutar `php artisan config:clear`
3. Verificar que nginx no esté bloqueando headers CORS

### 7. Alternativa: Procesamiento en Background (Futura Mejora)

Para importaciones muy grandes, considerar:

```php
// Crear un Job
php artisan make:job ImportSalesFromLogicware

// Despachar el job
ImportSalesFromLogicware::dispatch($startDate, $endDate);

// Responder inmediatamente al frontend
return response()->json([
    'success' => true,
    'message' => 'Importación iniciada en segundo plano',
    'job_id' => $jobId
]);
```

---

## Resumen de Cambios

| Componente | Cambio | Valor Anterior | Valor Nuevo |
|------------|--------|----------------|-------------|
| PHP Max Execution | Timeout | 300s | 600s |
| PHP Memory Limit | Memory | 256M | 512M |
| Nginx Timeout | Timeout | 60s (default) | 600s |
| PHP-FPM Timeout | Timeout | 30s (default) | 600s |
| Error Handling | Logs | Básico | Detallado + Stack |

---

## Checklist de Deployment

- [ ] Backend: `git pull origin main`
- [ ] Backend: `php artisan config:clear`
- [ ] Nginx: Configurar timeouts
- [ ] Nginx: `sudo nginx -t && sudo systemctl reload nginx`
- [ ] PHP-FPM: Configurar `request_terminate_timeout`
- [ ] PHP-FPM: `sudo systemctl restart php8.2-fpm`
- [ ] Probar endpoint de ventas (GET)
- [ ] Probar importación (POST)
- [ ] Monitorear logs durante prueba
- [ ] Verificar que no haya errores CORS

---

**Fecha:** 2026-01-08
**Versión:** 1.0
**Estado:** ✅ Listo para deployment
