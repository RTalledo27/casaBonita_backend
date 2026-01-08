# Implementación de Webhooks Logicware

## 📋 Descripción

Sistema completo de webhooks para recibir notificaciones en tiempo real desde Logicware CRM cuando ocurren cambios en contratos, pagos, cronogramas y lotes.

## 🎯 Características Implementadas

### ✅ Endpoint Webhook
- **URL**: `POST /api/webhooks/logicware`
- Sin autenticación JWT (validación por firma HMAC-SHA256)
- Respuesta rápida (<200ms) con procesamiento asíncrono
- Idempotencia mediante `messageId`

### ✅ Seguridad
- Validación de firma HMAC-SHA256 (`X-Webhook-Signature`)
- Comparación en tiempo constante para evitar timing attacks
- Secret configurable via `LOGICWARE_WEBHOOK_SECRET`
- Registro completo de headers y payload

### ✅ Procesamiento Asíncrono
- Job queue con Laravel (`ProcessLogicwareWebhook`)
- 3 reintentos automáticos (1min, 5min, 15min)
- Manejo de fallos permanentes
- Estados: pending → processing → processed/failed

### ✅ Eventos Soportados

#### 💰 Ventas
- `sales.process.completed` - Venta completada → Sincroniza contrato completo
- `sales.process.started` - Proceso de venta iniciado

#### 🔒 Separaciones
- `separation.process.completed` - Separación completada → Actualiza estado
- `separation.process.started` - Proceso de separación iniciado

#### 💳 Pagos
- `payment.created` - Pago registrado → Sincroniza cronograma
- `schedule.created` - Cronograma creado/actualizado → Sincroniza cuotas

#### 🏠 Lotes/Unidades
- `unit.updated` - Lote actualizado → Sincroniza estado (Disponible/Reservado/Vendido)
- `unit.created` - Nuevo lote creado

#### 📋 Otros
- `proforma.created` - Proforma creada → Registro de actividad
- `refund.process.started` - Devolución iniciada
- `refund.process.completed` - Devolución completada

### ✅ Auditoría Completa
- Tabla `webhook_logs` con:
  - `message_id` (único, indexado)
  - `event_type` (indexado)
  - `correlation_id` (indexado)
  - `source_id` (indexado)
  - `payload` (JSON completo)
  - `status` (pending/processing/processed/failed/failed_permanently)
  - `received_at`, `processed_at`
  - `error_message`, `retry_count`
  - `headers` (X-Webhook-Signature, X-LW-Event, etc.)

### ✅ Notificaciones en Tiempo Real
- Broadcast via WebSockets (Laravel Echo)
- Canal: `webhooks`
- Evento: `webhook.processed`
- Notificaciones visuales en el frontend con:
  - Mensaje descriptivo
  - Tipo (success/info/warning/error)
  - Timestamp
  - Datos del evento

## 📁 Archivos Creados

```
casaBonita_api/
├── app/
│   ├── Http/Controllers/
│   │   └── WebhookController.php           # Endpoint y validación
│   ├── Jobs/
│   │   └── ProcessLogicwareWebhook.php    # Procesamiento asíncrono
│   ├── Services/
│   │   ├── LogicwareWebhookHandler.php    # Lógica de eventos
│   │   └── NotificationService.php        # Notificaciones (ya existía)
│   ├── Events/
│   │   └── WebhookProcessed.php           # Evento broadcast
│   └── Models/
│       └── WebhookLog.php                 # Modelo de auditoría
├── database/migrations/
│   └── 2026_01_08_161830_create_webhook_logs_table.php
├── routes/
│   └── api.php                            # Rutas agregadas
└── config/
    └── services.php                       # Configuración actualizada
```

## 🚀 Configuración

### 1. Variables de Entorno

Agregar a `.env`:

```env
# Webhook Secret (proporcionado por Logicware)
LOGICWARE_WEBHOOK_SECRET=tu_secret_key_aqui
```

### 2. Ejecutar Migración

```bash
php artisan migrate
```

### 3. Configurar Queue Worker

#### Opción A: Queue Worker en desarrollo
```bash
php artisan queue:work --tries=3 --backoff=60,300,900
```

#### Opción B: Supervisor en producción

Crear archivo `/etc/supervisor/conf.d/casabonita-worker.conf`:

```ini
[program:casabonita-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/casaBonita_api/artisan queue:work --sleep=3 --tries=3 --backoff=60,300,900 --max-time=3600
autostart=true
autorestart=true
stopasflimit=3600
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/casaBonita_api/storage/logs/worker.log
stopwaitsecs=3600
```

Iniciar:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start casabonita-worker:*
```

### 4. Configurar WebSockets (Laravel Echo Server)

Ya configurado en el proyecto. Asegurarse que esté corriendo:

```bash
# Verificar que Laravel Echo Server está activo
pm2 list
```

### 5. Registrar Webhook en Logicware

Contactar con Logicware para configurar el webhook:

- **URL**: `https://tu-dominio.com/api/webhooks/logicware`
- **Método**: POST
- **Secret**: El valor de `LOGICWARE_WEBHOOK_SECRET`
- **Eventos suscritos**:
  - `sales.process.completed`
  - `separation.process.completed`
  - `payment.created`
  - `schedule.created`
  - `unit.updated`

## 📊 Estructura del Payload

Según documentación de Logicware:

```json
{
  "messageId": "a2e4bf25-c970-4d69-9336-9b6d09b89459",
  "eventType": "sales.process.completed",
  "eventTimestamp": "2025-09-02T23:46:11.634-05:00",
  "data": {
    "ord_correlative": "202509-000000274",
    "ord_total": 493.08,
    "client": {
      "type_document": "DNI",
      "document": "12345678",
      "full_name": "JUAN PEREZ PEREZ"
    },
    "units": [
      {
        "unit_number": "M-01",
        "sub_total": 493.08
      }
    ]
  },
  "sourceId": "5376",
  "correlationId": "sales.process.completed-5376-..."
}
```

## 🔍 Monitoreo y Debugging

### Ver Logs de Webhooks

```bash
# Logs de Laravel
tail -f storage/logs/laravel.log | grep -i webhook

# Logs del worker
tail -f storage/logs/worker.log
```

### API de Consulta de Webhooks

#### Listar webhooks recientes
```bash
GET /api/logicware/webhooks/logs?limit=50
Authorization: Bearer {token}
```

#### Ver detalle de webhook específico
```bash
GET /api/logicware/webhooks/logs/{messageId}
Authorization: Bearer {token}
```

Respuesta:
```json
{
  "log": {
    "id": 123,
    "message_id": "a2e4bf25-c970-4d69-9336-9b6d09b89459",
    "event_type": "sales.process.completed",
    "status": "processed",
    "received_at": "2025-01-08T16:30:00Z",
    "processed_at": "2025-01-08T16:30:02Z",
    "retry_count": 0,
    "error_message": null
  },
  "payload": { ... },
  "headers": { ... }
}
```

### Verificar Estado del Queue

```bash
# Ver jobs pendientes
php artisan queue:work --once

# Ver jobs fallidos
php artisan queue:failed

# Reintentar job fallido
php artisan queue:retry {id}

# Reintentar todos los fallidos
php artisan queue:retry all
```

## 🎨 Notificaciones en el Frontend

### Escuchar Eventos WebSocket

En tu componente Angular:

```typescript
import Echo from 'laravel-echo';

// Ya configurado en el proyecto
this.echo.channel('webhooks')
  .listen('.webhook.processed', (event) => {
    console.log('Webhook procesado:', event);
    
    // Mostrar notificación
    this.showNotification({
      message: event.message,
      type: event.type, // success/info/warning/error
      data: event.data
    });
    
    // Recargar datos si es necesario
    if (event.eventType === 'sales.process.completed') {
      this.reloadContracts();
    }
  });
```

### Tipos de Notificaciones

- **success** (verde): Ventas completadas, separaciones finalizadas
- **info** (azul): Actualizaciones de lotes, cronogramas
- **warning** (amarillo): Devoluciones, cancelaciones
- **error** (rojo): Errores críticos de procesamiento

## 🧪 Testing

### Probar Endpoint Manualmente

```bash
# Crear payload de prueba
cat > test_webhook.json << 'EOF'
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

# Calcular firma HMAC (reemplazar SECRET con tu secret real)
SECRET="tu_secret_key_aqui"
SIGNATURE=$(echo -n "$(cat test_webhook.json)" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)

# Enviar webhook
curl -X POST http://localhost:8000/api/webhooks/logicware \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: sha256=$SIGNATURE" \
  -H "X-LW-Event: sales.process.completed" \
  -H "X-LW-Delivery: test-delivery-123" \
  -d @test_webhook.json
```

### Verificar Respuesta

Respuesta exitosa:
```json
{
  "message": "Webhook received successfully",
  "messageId": "test-12345"
}
```

Respuesta duplicada (idempotencia):
```json
{
  "message": "Already processed"
}
```

## 📈 Métricas y Estadísticas

### Query de Análisis

```sql
-- Webhooks por tipo de evento (últimas 24 horas)
SELECT 
    event_type,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    AVG(TIMESTAMPDIFF(SECOND, received_at, processed_at)) as avg_processing_time
FROM webhook_logs
WHERE received_at >= NOW() - INTERVAL 24 HOUR
GROUP BY event_type
ORDER BY total DESC;

-- Webhooks con errores
SELECT 
    event_type,
    status,
    error_message,
    retry_count,
    received_at
FROM webhook_logs
WHERE status IN ('failed', 'failed_permanently')
ORDER BY received_at DESC
LIMIT 20;

-- Tasa de éxito
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as success,
    ROUND(100.0 * SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) / COUNT(*), 2) as success_rate
FROM webhook_logs
WHERE received_at >= NOW() - INTERVAL 7 DAY;
```

## 🔐 Seguridad

### Validación de Firma

El sistema valida automáticamente la firma HMAC-SHA256:

1. Logicware calcula: `HMAC-SHA256(payload, secret)`
2. Envía en header: `X-Webhook-Signature: sha256=<hash>`
3. Nuestro sistema recalcula y compara en tiempo constante
4. Rechaza si no coincide (401 Unauthorized)

### Mejores Prácticas

- ✅ Secret guardado en `.env`, nunca en código
- ✅ HTTPS obligatorio en producción
- ✅ Logs no contienen información sensible
- ✅ Rate limiting en Nginx (opcional)
- ✅ Validación de estructura de payload
- ✅ Idempotencia mediante messageId único

## 🐛 Troubleshooting

### Problema: Webhooks no se procesan

**Verificar:**
```bash
# 1. Queue worker está corriendo
ps aux | grep "queue:work"

# 2. Ver jobs pendientes
php artisan queue:work --once

# 3. Ver logs
tail -f storage/logs/laravel.log
```

### Problema: Firma inválida

**Verificar:**
```bash
# 1. Secret configurado correctamente
php artisan tinker
>>> config('services.logicware.webhook_secret')

# 2. Logicware usa el mismo secret
# Contactar con Logicware para verificar
```

### Problema: Duplicados procesándose

El sistema previene esto automáticamente mediante `messageId` único. Si ocurre:

```sql
-- Verificar duplicados
SELECT message_id, COUNT(*) 
FROM webhook_logs 
GROUP BY message_id 
HAVING COUNT(*) > 1;
```

## 📞 Soporte

- **Documentación Logicware**: https://docs.logicwareperu.com/
- **Soporte Logicware**: soporte@logicwareperu.com | WhatsApp: +51 953 448 476

## 📝 Changelog

### v1.0.0 - 2025-01-08
- ✅ Endpoint webhook implementado
- ✅ Validación HMAC-SHA256
- ✅ Procesamiento asíncrono con reintentos
- ✅ Auditoría completa en base de datos
- ✅ Notificaciones en tiempo real
- ✅ Soporte para 8+ tipos de eventos
- ✅ Documentación completa

---

## 🎯 Próximos Pasos (Opcional)

- [ ] Dashboard de métricas de webhooks en el frontend
- [ ] Alertas automáticas por webhook fallidos
- [ ] Replay manual de webhooks desde UI
- [ ] Filtros avanzados en logs de webhooks
- [ ] Exportar logs de webhooks a CSV/Excel
