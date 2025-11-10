# 🔄 Sistema de Caché para API de LOGICWARE

## 📊 Límite Diario
- **Endpoint**: `/external/units/stock/full`
- **Límite**: 4 consultas por día
- **Reset**: Medianoche (00:00:00)

## ✅ Solución Implementada: Sistema de Caché

### Funcionamiento Automático

Todas las consultas al API **usan caché automáticamente**:

1. **Primera consulta del día** → Consulta API real (consume 1/4)
2. **Siguientes consultas** → Usa datos del caché (NO consume)
3. **Caché válido por**: 6 horas
4. **Después de 6 horas**: Se renueva automáticamente si consultas

### Endpoints Disponibles

#### 1. Test de Conexión (USA CACHÉ)
```bash
GET /api/v1/inventory/external-lot-import/test-connection
```
**No consume consultas diarias** - Usa caché si está disponible

#### 2. Preview de Lotes (USA CACHÉ)
```bash
GET /api/v1/inventory/external-lot-import/preview
GET /api/v1/inventory/external-lot-import/preview?force_refresh=1
```
- Sin parámetros: Usa caché
- Con `force_refresh=1`: Consulta API real (consume 1/4)

#### 3. Ver Estado del Límite Diario
```bash
GET /api/v1/inventory/external-lot-import/daily-limit-status
```
Respuesta:
```json
{
  "success": true,
  "data": {
    "daily_limit": 4,
    "requests_used": 1,
    "requests_remaining": 3,
    "has_available_requests": true,
    "percentage_used": 25,
    "reset_at": "2025-11-05 23:59:59"
  }
}
```

#### 4. Limpiar Caché
```bash
POST /api/v1/inventory/external-lot-import/clear-cache
```
⚠️ **Cuidado**: Después de limpiar, la próxima consulta consumirá una request del límite

### Desde la Terminal (Artisan)

#### Ver consultas usadas hoy:
```bash
php artisan tinker --execute="echo 'Usadas: ' . app(\App\Services\LogicwareApiService::class)->getDailyRequestCount() . '/4' . PHP_EOL;"
```

#### Limpiar caché:
```bash
php artisan tinker --execute="app(\App\Services\LogicwareApiService::class)->clearCache(); echo 'Caché limpiado' . PHP_EOL;"
```

## 🎯 Recomendaciones

### ✅ DO's (Hacer)
1. **Usar el caché** para pruebas y desarrollo
2. **Consultar el límite diario** antes de hacer refresh manual
3. **Sincronizar una vez al día** en producción (por la mañana)
4. **Dejar que el caché expire naturalmente** (6 horas)

### ❌ DON'Ts (No Hacer)
1. **No uses `force_refresh=1`** a menos que sea absolutamente necesario
2. **No limpies el caché** frecuentemente
3. **No hagas múltiples consultas** en el mismo día sin necesidad
4. **No uses el test de conexión** para verificar cada 5 minutos

## 📈 Estrategia Recomendada para Producción

### Opción 1: Sincronización Diaria Programada
```bash
# En cron (Linux) o Task Scheduler (Windows)
# Ejecutar cada día a las 6:00 AM
php artisan lots:sync-external --force
```

### Opción 2: Sincronización Manual
1. Usuario hace clic en "Sincronizar Todos"
2. Sistema usa datos del caché si están disponibles
3. Solo consulta API real si:
   - No hay caché disponible
   - El caché expiró (>6 horas)
   - Usuario fuerza refresh manualmente

### Opción 3: Sincronización On-Demand
- Trabajar con datos del caché durante el día
- Al final del día, sincronizar una sola vez
- Los datos del caché son suficientes para operaciones normales

## 🔍 Debugging

### Ver si hay datos en caché:
```bash
php artisan tinker --execute="
\$key = 'logicware_stock_casabonita';
\$hasCache = Cache::has(\$key);
echo 'Cache exists: ' . (\$hasCache ? 'YES' : 'NO') . PHP_EOL;
if (\$hasCache) {
    \$data = Cache::get(\$key);
    echo 'Total units: ' . count(\$data['data'] ?? []) . PHP_EOL;
    echo 'Cached at: ' . (\$data['cached_at'] ?? 'unknown') . PHP_EOL;
}
"
```

### Ver tiempo restante del caché:
Laravel maneja esto automáticamente. El caché expira después de 6 horas desde la última consulta al API real.

## 💡 Tips Adicionales

1. **El caché se comparte entre todos los endpoints** que usan `getProperties()`
2. **El contador de consultas diarias se resetea a medianoche** automáticamente
3. **Si obtienes error 429** (rate limit exceeded), significa que ya usaste las 4 consultas
4. **El Bearer Token se genera automáticamente** y no cuenta para el límite de 4 consultas
5. **El test de conexión NO consume consultas** porque usa el caché

## 🆘 Troubleshooting

### Problema: "Error 429 - Daily rate limit exceeded"
**Solución**: Esperar hasta el reset (medianoche) o usar datos del caché

### Problema: "No se recibió accessToken"
**Solución**: Verificar configuración en `.env`:
- `LOGICWARE_API_KEY`
- `LOGICWARE_SUBDOMAIN=casabonita`
- `LOGICWARE_BASE_URL=https://gw.logicwareperu.com`

### Problema: "Datos del caché están desactualizados"
**Solución**:
1. Verificar cuántas consultas quedan: `GET /daily-limit-status`
2. Si quedan consultas, limpiar caché: `POST /clear-cache`
3. La próxima consulta obtendrá datos frescos del API

### Problema: "Quiero datos frescos AHORA pero ya gasté las 4 consultas"
**Solución**: No hay forma de saltarse el límite de LOGICWARE. Debes:
- Esperar hasta mañana
- O trabajar con los datos del caché de hoy
- O contactar a LOGICWARE para aumentar el límite

## 📞 Soporte

Si necesitas aumentar el límite de 4 consultas diarias, contacta a:
- **LOGICWARE CRM Support**
- Solicita plan con mayor límite de requests
- O acceso a webhooks para recibir actualizaciones en tiempo real
