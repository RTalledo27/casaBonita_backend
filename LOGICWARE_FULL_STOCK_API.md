# API de Stock Completo - Logicware Integration

## 📋 Descripción

Endpoint para obtener **TODOS** los datos completos de las unidades desde Logicware, incluyendo:
- ✅ Información completa de la unidad (área, precio, características)
- ✅ Estado actual (disponible, reservado, vendido)
- ✅ **Datos del vendedor/asesor asignado**
- ✅ **Historial de reservas y ventas**
- ✅ **Cliente asociado** (si aplica)
- ✅ Información financiera completa

Este endpoint es ideal para sincronizar TODA la información de unidades y sus relaciones con clientes, vendedores y reservas.

## 🔗 Endpoint

```
GET /api/logicware/full-stock
```

### Headers Requeridos
```
Authorization: Bearer {your_auth_token}
Accept: application/json
```

### Query Parameters (Opcionales)

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `force_refresh` | boolean | `false` | Forzar consulta al API (consume 1 de 4 llamadas diarias) |

## 📤 Ejemplos de Uso

### 1. Obtener datos desde caché (recomendado)
```bash
GET /api/logicware/full-stock
```

### 2. Forzar actualización desde Logicware
```bash
GET /api/logicware/full-stock?force_refresh=true
```

⚠️ **Nota**: Usar `force_refresh=true` consume una de las 4 llamadas diarias permitidas.

## 📥 Respuesta del API

### Estructura de Respuesta Exitosa

```json
{
  "success": true,
  "message": "Stock completo obtenido exitosamente",
  "data": {
    "succeeded": true,
    "data": [
      {
        "id": "unit-12345",
        "unitNumber": "G2-16",
        "status": "vendido",
        "area": 120.5,
        "price": 28560.0,
        "currency": "PEN",
        
        "seller": {
          "id": "seller-001",
          "name": "FERNANDO DAVID FEIJOO GARCIA",
          "code": "ASE001",
          "email": "feijoo@casabonita.pe"
        },
        
        "client": {
          "documentNumber": "12345678",
          "firstName": "JUAN",
          "paternalSurname": "PEREZ",
          "maternalSurname": "GARCIA",
          "email": "juan.perez@email.com",
          "phone": "987654321"
        },
        
        "reservation": {
          "date": "2025-11-01T10:30:00",
          "amount": 500.0,
          "status": "confirmed"
        },
        
        "sale": {
          "date": "2025-11-15T18:16:59",
          "correlative": "202511-000000577",
          "downPayment": 376.0,
          "amountToFinance": 22184.0,
          "totalInstallments": 60
        },
        
        "stage": {
          "id": "stage-01",
          "name": "Etapa 1 - Fase A"
        },
        
        "block": "G2",
        "features": [
          "Esquina",
          "Vista a parque",
          "Servicios básicos"
        ]
      }
      // ... más unidades
    ],
    "cached_at": "2025-11-17 15:30:00",
    "cache_expires_at": "2025-11-17 21:30:00",
    "daily_requests_used": 2
  },
  "stats": {
    "total_units": 150,
    "by_status": {
      "disponible": 80,
      "reservado": 25,
      "vendido": 45
    },
    "with_seller": 70,
    "with_client": 70,
    "with_reservation": 25,
    "data_source": "cache"
  },
  "cache_info": {
    "cached_at": "2025-11-17 15:30:00",
    "cache_expires_at": "2025-11-17 21:30:00",
    "is_cached": true
  },
  "api_usage": {
    "daily_requests_used": 2,
    "daily_requests_limit": 4,
    "requests_remaining": 2
  }
}
```

### Estructura de Respuesta con Error

```json
{
  "success": false,
  "message": "Error al obtener stock completo de Logicware",
  "error": "Rate limit alcanzado y no hay datos en caché disponibles"
}
```

## 🔄 Flujo de Datos

```
┌─────────────────────────────────┐
│ Frontend solicita full-stock    │
│ GET /api/logicware/full-stock   │
└───────────┬─────────────────────┘
            │
            ▼
┌─────────────────────────────────┐
│ ¿Datos en caché?                │
│ (válido por 6 horas)            │
└───────────┬─────────────────────┘
            │
       ┌────┴────┐
       │         │
      SÍ        NO
       │         │
       │         ▼
       │    ┌─────────────────────┐
       │    │ Verificar límite    │
       │    │ diario (4 requests) │
       │    └─────────┬───────────┘
       │              │
       │         ┌────┴────┐
       │         │         │
       │     OK         LÍMITE
       │         │         │
       │         │         ▼
       │         │    ┌──────────────┐
       │         │    │ Usar caché   │
       │         │    │ expirado     │
       │         │    └──────────────┘
       │         │
       │         ▼
       │    ┌─────────────────────┐
       │    │ GET /external/units/│
       │    │ stock/full          │
       │    │ (con Bearer Token)  │
       │    └─────────┬───────────┘
       │              │
       │              ▼
       │    ┌─────────────────────┐
       │    │ Cachear 6 horas     │
       │    └─────────┬───────────┘
       │              │
       └──────┬───────┘
              │
              ▼
    ┌─────────────────────┐
    │ Retornar datos con  │
    │ estadísticas        │
    └─────────────────────┘
```

## 📊 Estadísticas Incluidas

El endpoint retorna automáticamente estadísticas útiles:

- **total_units**: Total de unidades en el sistema
- **by_status**: Desglose por estado (disponible, reservado, vendido)
- **with_seller**: Unidades con vendedor asignado
- **with_client**: Unidades con cliente asociado
- **with_reservation**: Unidades que tuvieron reserva
- **data_source**: Origen de los datos (cache/api)

## 🎯 Casos de Uso

### 1. Dashboard de Inventario Completo
Mostrar todas las unidades con su estado actual, vendedores y clientes.

```javascript
// Frontend (Angular/React)
fetch('/api/logicware/full-stock')
  .then(res => res.json())
  .then(data => {
    console.log('Total unidades:', data.stats.total_units);
    console.log('Por estado:', data.stats.by_status);
    console.log('Unidades:', data.data.data);
  });
```

### 2. Sincronización Periódica
Actualizar datos cada 6 horas automáticamente.

```javascript
// Ejecutar cada 6 horas
setInterval(() => {
  fetch('/api/logicware/full-stock?force_refresh=true')
    .then(res => res.json())
    .then(data => {
      console.log('Datos actualizados:', data.stats);
    });
}, 6 * 60 * 60 * 1000); // 6 horas
```

### 3. Verificar Relaciones Completas
Analizar qué unidades tienen vendedor, cliente y reserva.

```javascript
fetch('/api/logicware/full-stock')
  .then(res => res.json())
  .then(data => {
    const units = data.data.data;
    
    // Filtrar unidades vendidas con toda la info
    const completeUnits = units.filter(unit => 
      unit.status === 'vendido' && 
      unit.seller && 
      unit.client
    );
    
    console.log(`${completeUnits.length} unidades vendidas con datos completos`);
  });
```

## ⚙️ Configuración del Caché

- **Duración**: 6 horas
- **Clave**: `logicware_full_stock_casabonita`
- **Tamaño máximo**: 2 MB
- **Comportamiento**: Si los datos exceden 2 MB, no se cachean pero se retornan igual

## 🛡️ Límites y Recomendaciones

### Límites del API
- **4 llamadas diarias** al API de Logicware
- Caché de **6 horas** para minimizar consumo
- Si se alcanza el límite, se usa caché expirado si está disponible

### Recomendaciones
✅ **Usar caché siempre que sea posible** (sin `force_refresh`)
✅ **Forzar refresh solo cuando sea necesario** (datos críticos)
✅ **Implementar lógica de fallback** si no hay datos disponibles
✅ **Monitorear `api_usage`** para evitar quedarse sin llamadas

❌ **NO** forzar refresh en cada petición del usuario
❌ **NO** usar `force_refresh=true` en intervalos automáticos menores a 6 horas
❌ **NO** ignorar el contador `daily_requests_used`

## 🔍 Monitoreo

### Verificar Uso del API
```bash
GET /api/logicware/status
```

Retorna:
```json
{
  "success": true,
  "data": {
    "daily_requests_used": 2,
    "daily_requests_limit": 4,
    "requests_available": true
  }
}
```

### Logs
Todas las operaciones se registran en `storage/logs/laravel.log`:

```
[LogicwareAPI] 📦 Stock COMPLETO obtenido del CACHÉ
[LogicwareAPI] ⚠️ CONSULTANDO STOCK COMPLETO (consume 1 de 4 consultas diarias)
[LogicwareAPI] ✅ Stock COMPLETO obtenido y guardado en caché
```

## 🚨 Manejo de Errores

### Error 429 - Rate Limit
Si se alcanza el límite de 4 llamadas:
1. El sistema intenta usar caché expirado
2. Si no hay caché, retorna error 500 con mensaje claro

### Error 401 - Token Inválido
El token se renueva automáticamente cada 5 minutos.
Si falla, ejecutar:
```bash
php artisan logicware:renew-token
```

### Error 500 - Error del Servidor Logicware
Revisar logs y verificar conectividad:
```bash
curl https://gw.logicwareperu.com
```

## 📝 Notas Técnicas

- El endpoint NO tiene middleware de permisos específico (solo autenticación)
- Los datos incluyen relaciones completas (seller, client, reservation, sale)
- Compatible con caché de MySQL (CACHE_STORE=database)
- Maneja respuestas grandes (hasta 2 MB en caché)
- Incrementa automáticamente el contador diario de peticiones

## 🔗 Endpoints Relacionados

- `GET /api/logicware/status` - Ver estado de la integración
- `POST /api/logicware/renew-token` - Renovar token manualmente
- `GET /api/logicware/token-info` - Info del token actual
- `POST /api/logicware/import-contracts` - Importar contratos (usa `/external/clients/sales`)
