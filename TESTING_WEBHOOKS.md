# 🧪 Guía Rápida para Probar Webhooks

## Método 1: Script PHP (Recomendado) ⭐

### Paso 1: Configurar Secret
Edita `test_webhook.php` línea 12:
```php
$secret = 'test_secret_123'; // Cambiar por tu LOGICWARE_WEBHOOK_SECRET
```

O agrega a `.env`:
```env
LOGICWARE_WEBHOOK_SECRET=test_secret_123
```

### Paso 2: Asegurar que el servidor esté corriendo
```bash
php artisan serve
```

### Paso 3: Ejecutar pruebas

**Terminal 1 - Ver logs en tiempo real:**
```bash
cd casaBonita_api
tail -f storage/logs/laravel.log | grep -i webhook
```

**Terminal 2 - Enviar webhook:**
```bash
# Probar venta completada
php test_webhook.php sales.process.completed

# Probar pago creado
php test_webhook.php payment.created

# Probar actualización de lote
php test_webhook.php unit.updated

# Ver todos los eventos disponibles
php test_webhook.php
```

**Terminal 3 - Procesar queue:**
```bash
php artisan queue:work --once
```

### Paso 4: Verificar en base de datos
```sql
-- Ver webhooks recibidos
SELECT * FROM webhook_logs ORDER BY received_at DESC LIMIT 5;

-- Ver por tipo de evento
SELECT event_type, status, received_at, processed_at 
FROM webhook_logs 
WHERE event_type = 'sales.process.completed';

-- Ver errores
SELECT * FROM webhook_logs WHERE status = 'failed';
```

## Método 2: cURL Manual

```bash
# 1. Crear payload
cat > payload.json << 'EOF'
{
  "messageId": "test-12345",
  "eventType": "sales.process.completed",
  "eventTimestamp": "2025-01-08T16:00:00-05:00",
  "data": {
    "ord_correlative": "202501-000000001",
    "ord_total": 50000.00
  },
  "sourceId": "1001",
  "correlationId": "test-correlation-123"
}
EOF

# 2. Calcular firma (Windows - PowerShell)
$secret = "test_secret_123"
$payload = Get-Content payload.json -Raw
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($secret)
$signature = [System.BitConverter]::ToString($hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($payload))).Replace('-','').ToLower()

# 3. Enviar webhook
curl -X POST http://127.0.0.1:8000/api/webhooks/logicware `
  -H "Content-Type: application/json" `
  -H "X-Webhook-Signature: sha256=$signature" `
  -H "X-LW-Event: sales.process.completed" `
  --data-binary "@payload.json"
```

## Método 3: Postman

1. **Importar colección**
   - Abrir Postman
   - File → Import
   - Seleccionar `Logicware_Webhooks.postman_collection.json`

2. **Configurar variables**
   - En la colección, ir a Variables
   - `base_url`: `http://127.0.0.1:8000`
   - `webhook_secret`: Tu secret de `.env`

3. **Calcular firma HMAC-SHA256**
   - En Postman, ir a Pre-request Script
   - Agregar:
   ```javascript
   const CryptoJS = require('crypto-js');
   const secret = pm.collectionVariables.get('webhook_secret');
   const body = pm.request.body.raw;
   const signature = CryptoJS.HmacSHA256(body, secret).toString();
   pm.request.headers.upsert({
       key: 'X-Webhook-Signature',
       value: 'sha256=' + signature
   });
   ```

4. **Enviar requests**
   - Seleccionar "1. Sales Process Completed"
   - Click Send
   - Verificar respuesta 200 OK

## 🔍 Verificación Paso a Paso

### 1. Verificar que el webhook fue recibido
```bash
# Ver último webhook
php artisan tinker
>>> \App\Models\WebhookLog::latest()->first()
```

### 2. Verificar que está en la cola
```bash
php artisan queue:work --once
```

Deberías ver:
```
✅ Webhook recibido y encolado
🔄 Procesando webhook
✅ Webhook procesado exitosamente
```

### 3. Verificar que se procesó correctamente
```sql
SELECT 
    message_id,
    event_type,
    status,
    received_at,
    processed_at,
    error_message
FROM webhook_logs
ORDER BY received_at DESC
LIMIT 1;
```

### 4. Verificar notificación WebSocket (si está configurado)
En la consola del navegador deberías ver:
```
📥 Webhook notification received: {eventType: "sales.process.completed", ...}
```

## 🚨 Troubleshooting

### ❌ Error 401: Invalid signature
**Problema:** El secret no coincide

**Solución:**
```bash
# Verificar secret en .env
cat .env | grep LOGICWARE_WEBHOOK_SECRET

# Actualizar en test_webhook.php
nano test_webhook.php  # Línea 12
```

### ❌ Error 500: Internal Server Error
**Problema:** Error en el código del handler

**Solución:**
```bash
# Ver error completo en logs
tail -n 50 storage/logs/laravel.log
```

### ❌ Webhook recibido pero no procesado
**Problema:** Queue worker no está corriendo

**Solución:**
```bash
# Verificar si hay jobs pendientes
php artisan queue:work --once

# O iniciar worker permanente
php artisan queue:work
```

### ❌ Webhook se procesa dos veces
**Problema:** messageId duplicado (esto NO debería pasar)

**Solución:**
```bash
# El sistema previene esto automáticamente
# Verificar en logs:
tail -f storage/logs/laravel.log | grep "duplicado"
```

## 📊 Scripts Útiles

### Ver webhooks del día
```bash
php artisan tinker
>>> \App\Models\WebhookLog::whereDate('received_at', today())->count()
```

### Reintentar webhook fallido
```bash
# Ver jobs fallidos
php artisan queue:failed

# Reintentar específico
php artisan queue:retry {id}

# Reintentar todos
php artisan queue:retry all
```

### Limpiar webhooks de prueba
```sql
DELETE FROM webhook_logs WHERE message_id LIKE 'test-%';
```

## ✅ Checklist de Pruebas

- [ ] Servidor Laravel corriendo (`php artisan serve`)
- [ ] Queue worker iniciado (`php artisan queue:work`)
- [ ] Secret configurado en `.env`
- [ ] Tabla `webhook_logs` existe
- [ ] WebSocket server corriendo (opcional)
- [ ] Test script ejecuta sin errores
- [ ] Webhook aparece en base de datos
- [ ] Webhook se procesa correctamente
- [ ] Notificación aparece en frontend (opcional)

## 🎯 Casos de Prueba Recomendados

1. ✅ **Venta completada** - Debe sincronizar contrato
2. ✅ **Pago creado** - Debe actualizar cronograma
3. ✅ **Lote actualizado** - Debe cambiar estado
4. ✅ **Webhook duplicado** - Debe responder "Already processed"
5. ✅ **Firma inválida** - Debe rechazar con 401
6. ✅ **Payload inválido** - Debe fallar gracefully

## 📞 Siguiente Paso

Una vez que las pruebas locales funcionen:

1. Contactar a Logicware: soporte@logicwareperu.com
2. Proporcionar URL de producción: `https://tu-dominio.com/api/webhooks/logicware`
3. Compartir secret (de forma segura)
4. Solicitar activación de webhooks
