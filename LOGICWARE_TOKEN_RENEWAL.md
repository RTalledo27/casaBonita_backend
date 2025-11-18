# Sistema de Renovación Automática de Token Logicware

## 📋 Descripción

Sistema automatizado para mantener siempre vigente el Bearer Token de Logicware, evitando interrupciones en las integraciones de importación de lotes y contratos.

## 🔧 Componentes Implementados

### 1. Comando Artisan
**Ubicación:** `app/Console/Commands/RenewLogicwareToken.php`

**Comando:**
```bash
php artisan logicware:renew-token
```

**Función:** Renueva el Bearer Token de forma manual o automática, guardándolo en caché por 23 horas.

### 2. Scheduler Automático
**Ubicación:** `routes/console.php`

**Configuración:**
- **Frecuencia:** Cada 5 minutos (recomendación oficial de Logicware)
- **Cron:** `*/5 * * * *`
- **Zona horaria:** America/Lima
- **Logs:** Automáticos en success/failure
- **Comportamiento:** Verifica el token cada 5 minutos, solo renueva si expiró (caché de 23 horas)

### 3. API Service Mejorado
**Ubicación:** `app/Services/LogicwareApiService.php`

**Mejoras:**
- `generateToken(bool $forceRefresh = false)` - Genera y cachea token automáticamente
- Caché automático por 23 horas (tokens duran 24h)
- Manejo inteligente de tokens en caché

### 4. Endpoints API

#### POST `/api/logicware/renew-token`
Renovar token manualmente desde el frontend.

**Respuesta:**
```json
{
  "success": true,
  "message": "Token renovado exitosamente",
  "data": {
    "token_preview": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "valid_until": "2025-11-18 13:37:37",
    "renewed_at": "2025-11-17 14:37:37"
  }
}
```

#### GET `/api/logicware/token-info`
Obtener información del token actual en caché.

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "has_token": true,
    "token_preview": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "cache_key": "logicware_bearer_token_casabonita",
    "message": "Token activo en caché"
  }
}
```

## 🚀 Activación del Scheduler

Para que el scheduler funcione, es necesario configurar un **cron job** en el servidor:

### Windows (Development)
1. Abrir **Programador de tareas** (Task Scheduler)
2. Crear una nueva tarea básica
3. Configurar trigger: **Cada 1 minuto** o **Al iniciar sesión**
4. Acción: Ejecutar programa
   - Programa: `php`
   - Argumentos: `C:\ruta\a\casaBonita_api\artisan schedule:run`
5. Guardar y activar

### Linux/Ubuntu (Production)
Agregar al crontab:
```bash
* * * * * cd /path/to/casaBonita_api && php artisan schedule:run >> /dev/null 2>&1
```

## 🧪 Pruebas

### Prueba Manual del Comando
```bash
php artisan logicware:renew-token
```

**Salida esperada (con token válido en caché):**
```
✅ Token existente en caché aún válido
📝 Token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Salida esperada (generando token nuevo):**
```
🔄 Renovando Bearer Token de Logicware...
✅ Token renovado exitosamente
📝 Token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
⏰ Válido hasta: 2025-11-18 13:37:37
💾 Guardado en caché automáticamente
```

### Prueba del Scheduler
```bash
php artisan schedule:test
```

### Verificar Cache
```bash
php artisan tinker
>>> Cache::get('logicware_bearer_token_casabonita')
```

## 📊 Monitoreo

### Logs
Los eventos se registran automáticamente en `storage/logs/laravel.log`:

```
[LogicwareScheduler] Token de Logicware renovado automáticamente
[LogicwareAPI] Bearer Token generado y guardado en caché
```

### Verificación desde API
```bash
curl -X GET "http://localhost:8000/api/logicware/token-info" \
  -H "Authorization: Bearer YOUR_AUTH_TOKEN"
```

## 🔄 Flujo de Renovación

```
┌─────────────────────────┐
│  Scheduler ejecuta      │
│  cada 5 minutos         │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│  ¿Token en caché?       │
└───────────┬─────────────┘
            │
       ┌────┴────┐
       │         │
      SÍ        NO
       │         │
       │         ▼
       │    ┌─────────────────────┐
       │    │  POST /auth/        │
       │    │  external/token     │
       │    └─────────┬───────────┘
       │              │
       │              ▼
       │    ┌─────────────────────┐
       │    │  Guardar en caché   │
       │    │  por 23 horas       │
       │    └─────────┬───────────┘
       │              │
       └──────┬───────┘
              │
              ▼
    ┌─────────────────────┐
    │  Token listo        │
    │  para usar          │
    └─────────────────────┘
```

## ⚙️ Configuración .env

Asegúrate de tener configuradas estas variables:

```env
LOGICWARE_BASE_URL=https://gw.logicwareperu.com
LOGICWARE_API_KEY=lw_prod_dc9e65ac36764d219471777944fa764746dc25c5
LOGICWARE_SUBDOMAIN=casabonita
LOGICWARE_TIMEOUT=30
```

## 🛡️ Seguridad

- El token se guarda en **caché de Laravel** (no en archivos)
- Duración: **23 horas** (renovación antes de expirar)
- API Key protegido en `.env`
- Logs automáticos de cada renovación

## ❓ Troubleshooting

### El token no se renueva automáticamente
1. Verificar que el scheduler esté corriendo:
   ```bash
   php artisan schedule:list
   ```
2. Verificar logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Error al generar token
1. Verificar conectividad:
   ```bash
   curl https://gw.logicwareperu.com
   ```
2. Verificar API Key en `.env`
3. Verificar que `LOGICWARE_SUBDOMAIN` sea correcto

### Token en caché expiró
El sistema genera automáticamente uno nuevo en la próxima petición. Para forzar renovación:
```bash
php artisan logicware:renew-token
```

## 📝 Notas

- Los tokens de Logicware duran **24 horas**
- Se **verifica** automáticamente cada **5 minutos** (recomendación oficial)
- Solo se **renueva** cuando el token en caché expira (cada 23 horas)
- El caché se guarda por **23 horas** para tener margen de seguridad
- El sistema **nunca queda sin token** válido si el scheduler está activo
- **Eficiente**: No hace peticiones innecesarias si el token aún es válido
