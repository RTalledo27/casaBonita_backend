# 📊 Sistema de Cortes de Ventas Diarios

Sistema profesional y automatizado para gestionar cierres de ventas diarios, pagos recibidos y comisiones generadas.

## 🎯 Características

✅ **Cierre Automático**: Ejecuta automáticamente cada día a las 11:59 PM
✅ **Métricas Completas**: Ventas, pagos, cronogramas pagados y comisiones
✅ **Balance por Método**: Efectivo vs Transferencia/Tarjeta
✅ **Auditoría**: Registro de quién cierra y revisa cada corte
✅ **Estadísticas Mensuales**: Análisis comparativo del mes
✅ **Historial**: Consulta cortes pasados con búsqueda y filtros

## 📋 Estructura de Base de Datos

### Tabla: `sales_cuts`
- `cut_id`: ID del corte
- `cut_date`: Fecha del corte
- `cut_type`: Tipo (daily, weekly, monthly)
- `status`: Estado (open, closed, reviewed, exported)
- **Métricas de Ventas:**
  - `total_sales_count`: Total de ventas
  - `total_revenue`: Ingresos por ventas
  - `total_down_payments`: Cuotas iniciales
- **Métricas de Pagos:**
  - `total_payments_count`: Total de pagos recibidos
  - `total_payments_received`: Total cobrado
  - `paid_installments_count`: Cuotas pagadas
- **Balance:**
  - `cash_balance`: Balance en efectivo
  - `bank_balance`: Balance bancario
- **Comisiones:**
  - `total_commissions`: Total de comisiones
- **Auditoría:**
  - `closed_by`, `closed_at`
  - `reviewed_by`, `reviewed_at`

### Tabla: `sales_cut_items`
- `item_id`: ID del item
- `cut_id`: Referencia al corte
- `item_type`: Tipo (sale, payment, commission)
- `contract_id`: Referencia al contrato (opcional)
- `payment_schedule_id`: Referencia al cronograma (opcional)
- `employee_id`: Referencia al empleado
- `amount`: Monto
- `commission`: Comisión (opcional)
- `payment_method`: Método de pago (para pagos)

## 🚀 Instalación y Configuración

### 1. Ejecutar Migración

```bash
php artisan migrate
```

### 2. Configurar Cron Job (Producción)

Agregar al crontab del servidor:

```bash
* * * * * cd /var/www/casabonita_api && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Ejecutar Manualmente (Desarrollo)

```bash
# Crear corte del día actual
php artisan sales:create-daily-cut

# Crear corte de una fecha específica
php artisan sales:create-daily-cut 2026-01-08
```

## 📡 API Endpoints

### **GET** `/api/v1/sales/cuts`
Obtener lista de cortes con paginación

**Query Params:**
- `per_page`: Items por página (default: 15)
- `status`: Filtrar por estado (open, closed, reviewed, exported)
- `type`: Filtrar por tipo (daily, weekly, monthly)
- `start_date`: Fecha inicio (YYYY-MM-DD)
- `end_date`: Fecha fin (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "cut_id": 1,
        "cut_date": "2026-01-09",
        "cut_type": "daily",
        "status": "closed",
        "total_sales_count": 5,
        "total_revenue": 450000.00,
        "total_payments_count": 12,
        "total_payments_received": 28500.00,
        "total_commissions": 13500.00,
        "cash_balance": 12000.00,
        "bank_balance": 16500.00,
        "closed_by": 1,
        "closed_at": "2026-01-09 23:59:59"
      }
    ],
    "per_page": 15,
    "total": 30
  }
}
```

### **GET** `/api/v1/sales/cuts/today`
Obtener corte del día actual (lo crea si no existe)

**Response:**
```json
{
  "success": true,
  "data": {
    "cut_id": 1,
    "cut_date": "2026-01-09",
    "status": "open",
    "total_sales_count": 3,
    "total_revenue": 275000.00,
    "items": [
      {
        "item_id": 1,
        "item_type": "sale",
        "amount": 90000.00,
        "commission": 2700.00,
        "description": "Venta: 202601-000001",
        "contract": {
          "contract_number": "202601-000001",
          "client": {
            "first_name": "Juan",
            "last_name": "Pérez"
          }
        }
      }
    ],
    "summary_data": {
      "sales_by_advisor": [
        {
          "advisor_name": "Carlos García",
          "sales_count": 2,
          "total_amount": 180000.00,
          "total_commission": 5400.00
        }
      ]
    }
  }
}
```

### **GET** `/api/v1/sales/cuts/{id}`
Obtener detalle completo de un corte

### **POST** `/api/v1/sales/cuts/create-daily`
Crear corte diario manualmente

**Body:**
```json
{
  "date": "2026-01-08"  // Opcional
}
```

### **POST** `/api/v1/sales/cuts/{id}/close`
Cerrar un corte

**Response:**
```json
{
  "success": true,
  "message": "Corte cerrado exitosamente",
  "data": {
    "cut_id": 1,
    "status": "closed",
    "closed_by": 1,
    "closed_at": "2026-01-09 23:59:59"
  }
}
```

### **POST** `/api/v1/sales/cuts/{id}/review`
Marcar corte como revisado (después de cerrado)

### **PATCH** `/api/v1/sales/cuts/{id}/notes`
Actualizar notas del corte

**Body:**
```json
{
  "notes": "Día con alto volumen de ventas. 2 ventas de lotes premium."
}
```

### **GET** `/api/v1/sales/cuts/monthly-stats`
Obtener estadísticas del mes actual

**Response:**
```json
{
  "success": true,
  "data": {
    "total_sales": 45,
    "total_revenue": 3850000.00,
    "total_payments": 450000.00,
    "total_commissions": 115500.00,
    "daily_average": {
      "sales": 5.0,
      "revenue": 428333.33,
      "payments": 50000.00
    },
    "cuts_count": 9,
    "closed_cuts": 7
  }
}
```

## 🎨 Frontend - Componentes Necesarios

Necesitarás crear:

1. **Dashboard de Cortes** (`/sales/cuts`)
   - Lista de cortes con filtros
   - Tarjetas con métricas principales
   - Botón para ver corte del día

2. **Detalle de Corte** (`/sales/cuts/{id}`)
   - Métricas generales
   - Tabla de ventas del día
   - Tabla de pagos recibidos
   - Gráficos de balance
   - Botones: Cerrar, Revisar, Agregar notas

3. **Corte del Día** (`/sales/cuts/today`)
   - Vista en tiempo real
   - Actualización automática
   - Resumen por asesor
   - Top ventas del día

## 📊 Qué Incluye Cada Corte

### 1. **Ventas Nuevas**
- Contratos firmados en el día (`sign_date`)
- Estado `vigente`
- Incluye: monto total, comisión calculada, cliente, lote, asesor

### 2. **Pagos Recibidos**
- Cuotas pagadas en el día (`paid_date`)
- Estado `pagada`
- Incluye: método de pago, monto, número de cuota, contrato

### 3. **Comisiones**
- Comisiones de ventas del día (3% por defecto)
- Asociadas al asesor
- Basadas en el monto total de la venta

### 4. **Balance**
- **Efectivo**: Pagos en cash
- **Banco**: Transferencias + Tarjetas

## 🔒 Flujo de Estados

```
open → closed → reviewed → exported
  ↓       ↓        ↓          ↓
Abierto  Cerrado  Revisado  Exportado
```

- **open**: Corte activo, se pueden agregar items
- **closed**: Cerrado por usuario, no se pueden agregar items
- **reviewed**: Revisado por supervisor/gerente
- **exported**: Exportado a contabilidad/sistema externo

## 🎯 Uso Típico

### Día a Día:
1. Sistema crea corte automáticamente a las 11:59 PM
2. Al día siguiente, gerente revisa corte en dashboard
3. Verifica ventas, pagos y comisiones
4. Cierra el corte manualmente
5. Supervisor revisa y marca como revisado

### Consultas:
- Ver corte del día en tiempo real
- Comparar cortes del mes
- Analizar tendencias de ventas
- Verificar comisiones generadas

## 🧪 Testing

```bash
# Crear corte de prueba para hoy
php artisan sales:create-daily-cut

# Ver resultado en consola
# Verificar en base de datos
SELECT * FROM sales_cuts WHERE cut_date = CURDATE();
SELECT * FROM sales_cut_items WHERE cut_id = 1;
```

## 📈 Próximas Mejoras

- [ ] Exportación a PDF
- [ ] Exportación a Excel
- [ ] Notificaciones por email/WhatsApp
- [ ] Integración con sistema contable
- [ ] Cortes semanales y mensuales
- [ ] Comparativas entre períodos
- [ ] Alertas de anomalías (ventas muy altas/bajas)

---

**¡Sistema listo para producción!** 🚀
