# 🚀 Configuración de Webhooks en Producción

## Pre-requisitos completados ✅

- [x] Código de webhooks implementado
- [x] Migración ejecutada (`webhook_logs` table)
- [x] Validación HMAC-SHA256
- [x] Sistema de reintentos automáticos
- [x] Auditoría completa

---

## 📋 Checklist de Deployment

### 1. Configurar Variables de Entorno

**En el servidor de producción**, agregar al archivo `.env`:

```env
# Webhook Secret - Generar un string aleatorio fuerte
LOGICWARE_WEBHOOK_SECRET=cb_webhook_secret_2026_prod_xyz123abc456def789ghi
```

**⚠️ IMPORTANTE:** Este secret debe:
- Tener al menos 32 caracteres
- Ser completamente aleatorio
- Mantenerse secreto (NO compartir públicamente)
- Ser el mismo que se registre en Logicware

**Generar un nuevo secret seguro** (ejecutar en terminal):

```bash
# Opción 1: Usando OpenSSL
openssl rand -hex 32

# Opción 2: Usando PHP
php -r "echo bin2hex(random_bytes(32));"

# Opción 3: Online (usar con precaución)
# https://www.random.org/strings/
```

### 2. Configurar el Queue Worker

Los webhooks se procesan asíncronamente. Necesitas un worker corriendo permanentemente:

**Opción A: Supervisor (Recomendado para Linux)**

Crear archivo `/etc/supervisor/conf.d/casa-bonita-queue.conf`:

```ini
[program:casa-bonita-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/tu/proyecto/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/ruta/a/tu/proyecto/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Después ejecutar:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start casa-bonita-queue-worker:*
```

**Opción B: Systemd Service (Linux alternativo)**

Crear archivo `/etc/systemd/system/casa-bonita-queue.service`:

```ini
[Unit]
Description=Casa Bonita Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/ruta/a/tu/proyecto
ExecStart=/usr/bin/php /ruta/a/tu/proyecto/artisan queue:work database --sleep=3 --tries=3
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Habilitar:

```bash
sudo systemctl enable casa-bonita-queue
sudo systemctl start casa-bonita-queue
```

**Opción C: Windows Service (usando NSSM)**

1. Descargar NSSM: https://nssm.cc/download
2. Ejecutar:

```cmd
nssm install CasaBonitaQueue "C:\ruta\a\php.exe" "C:\ruta\a\artisan queue:work database --sleep=3 --tries=3"
nssm start CasaBonitaQueue
```

**Opción D: Cron Job (No recomendado, solo para desarrollo)**

```bash
* * * * * cd /ruta/a/tu/proyecto && php artisan queue:work --once
```

### 3. Registrar Webhook en Logicware

**Contactar al equipo de Logicware** y proporcionar:

**URL del webhook:**
```
https://tudominio.com/api/webhooks/logicware
```

**Secret compartido:**
```
cb_webhook_secret_2026_prod_xyz123abc456def789ghi
```
(El mismo configurado en `LOGICWARE_WEBHOOK_SECRET`)

**Eventos a suscribir:**
- `sales.process.completed` - Venta completada
- `separation.process.completed` - Separación completada
- `payment.created` - Pago creado
- `schedule.created` - Cronograma creado/actualizado
- `unit.updated` - Lote/unidad actualizado
- `unit.created` - Nueva unidad creada
- `proforma.created` - Proforma creada

### 4. Verificar Configuración del Servidor

**Firewall/Seguridad:**

El servidor debe permitir:
- ✅ Conexiones HTTPS entrantes (puerto 443)
- ✅ Tráfico desde IPs de Logicware (preguntar rango de IPs)

**Nginx/Apache:**

Asegurarse que el endpoint esté accesible:

```bash
# Test desde el servidor
curl -X POST https://tudominio.com/api/webhooks/logicware \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: sha256=test" \
  -d '{"messageId":"test","eventType":"test","data":{}}'
```

Respuesta esperada: `401 Unauthorized` (porque la firma es inválida, pero el endpoint responde)

### 5. Configurar Logs y Monitoreo

**Laravel Log Rotation:**

Editar `config/logging.php` para rotar logs:

```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14, // Mantener 14 días de logs
],
```

**Monitorear webhook_logs:**

```sql
-- Ver últimos webhooks recibidos
SELECT 
    message_id,
    event_type,
    status,
    received_at,
    processed_at,
    error_message
FROM webhook_logs 
ORDER BY received_at DESC 
LIMIT 20;

-- Contar webhooks por estado
SELECT 
    status,
    COUNT(*) as total
FROM webhook_logs 
WHERE DATE(received_at) = CURDATE()
GROUP BY status;

-- Ver webhooks fallidos
SELECT 
    message_id,
    event_type,
    error_message,
    retry_count,
    received_at
FROM webhook_logs 
WHERE status = 'failed'
ORDER BY received_at DESC;
```

### 6. Testing en Producción

**Verificar que todo funciona:**

1. **Revisar que el worker esté corriendo:**

```bash
# Linux
ps aux | grep "queue:work"

# Ver logs del worker
tail -f storage/logs/queue-worker.log
```

2. **Enviar webhook de prueba** (pedir a Logicware que envíen uno de prueba)

3. **Monitorear logs:**

```bash
tail -f storage/logs/laravel.log | grep -i webhook
```

4. **Verificar en base de datos:**

```sql
SELECT * FROM webhook_logs ORDER BY received_at DESC LIMIT 5;
SELECT * FROM jobs; -- Debe estar vacía si se procesan correctamente
SELECT * FROM failed_jobs; -- No debe haber registros
```

---

## 🔍 Troubleshooting

### El webhook no llega

1. ✅ Verificar que la URL sea accesible públicamente
2. ✅ Revisar firewall/seguridad del servidor
3. ✅ Verificar logs de Nginx/Apache
4. ✅ Confirmar con Logicware que lo hayan configurado

### El webhook llega pero falla la validación

1. ✅ Verificar que `LOGICWARE_WEBHOOK_SECRET` sea el mismo en ambos lados
2. ✅ Revisar logs: `tail -f storage/logs/laravel.log | grep signature`
3. ✅ Confirmar que Logicware esté enviando el header `X-Webhook-Signature`

### El webhook se recibe pero no se procesa

1. ✅ Verificar que el queue worker esté corriendo: `ps aux | grep queue:work`
2. ✅ Revisar tabla `jobs`: `SELECT * FROM jobs;`
3. ✅ Revisar tabla `failed_jobs`: `SELECT * FROM failed_jobs;`
4. ✅ Ejecutar manualmente: `php artisan queue:work --once`

### Webhooks duplicados

1. ✅ Verificar campo `message_id` en `webhook_logs` (debe ser único)
2. ✅ Sistema automáticamente previene duplicados por `messageId`

### Worker se detiene

1. ✅ Usar Supervisor/Systemd para auto-restart
2. ✅ Revisar logs del worker
3. ✅ Verificar memoria: worker se reinicia cada 3600s (1 hora)

---

## 📊 Monitoreo Continuo

### Dashboard SQL para monitoreo diario:

```sql
-- Resumen del día
SELECT 
    DATE(received_at) as fecha,
    event_type,
    status,
    COUNT(*) as total,
    AVG(TIMESTAMPDIFF(SECOND, received_at, processed_at)) as avg_processing_time_seconds
FROM webhook_logs 
WHERE DATE(received_at) = CURDATE()
GROUP BY DATE(received_at), event_type, status
ORDER BY event_type, status;

-- Tasa de éxito
SELECT 
    ROUND(
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
        2
    ) as success_rate_percent,
    COUNT(*) as total_webhooks
FROM webhook_logs 
WHERE DATE(received_at) = CURDATE();
```

### Alertas recomendadas:

1. ⚠️ Si tasa de éxito < 95% en últimas 24h
2. ⚠️ Si hay > 10 webhooks failed sin procesar
3. ⚠️ Si el queue worker no está corriendo
4. ⚠️ Si hay > 100 jobs en la tabla `jobs` (cola atascada)

---

## ✅ Checklist Final

Antes de considerar el deployment completo:

- [ ] Variable `LOGICWARE_WEBHOOK_SECRET` configurada en producción
- [ ] Queue worker corriendo como servicio permanente
- [ ] Webhook registrado en Logicware con URL y secret
- [ ] Endpoint accesible públicamente (test con curl)
- [ ] Logs configurados y rotando correctamente
- [ ] Sistema de monitoreo en su lugar
- [ ] Webhook de prueba enviado y procesado exitosamente
- [ ] Documentación compartida con el equipo

---

## 📞 Contacto

**Para soporte de Logicware:**
- Solicitar registro de webhook
- Solicitar rango de IPs (para whitelist)
- Solicitar webhooks de prueba

**Archivos relevantes en el código:**
- Endpoint: `app/Http/Controllers/WebhookController.php`
- Job procesamiento: `app/Jobs/ProcessLogicwareWebhook.php`
- Handler de eventos: `app/Services/LogicwareWebhookHandler.php`
- Modelo auditoría: `app/Models/WebhookLog.php`
- Configuración: `config/services.php`
- Rutas: `routes/api.php` (línea webhook)

---

**Fecha de creación:** 2026-01-08  
**Versión:** 1.0  
**Estado:** ✅ Listo para producción
