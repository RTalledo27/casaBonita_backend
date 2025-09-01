# Endpoint de Detalle de Ventas Individuales

## Descripción
Este endpoint permite obtener el detalle individual de cada venta realizada por un asesor en un período específico, mostrando la información desglosada de cada contrato con sus comisiones correspondientes.

## Endpoint
```
GET /api/v1/hr/commissions/sales-detail
```

## Parámetros Requeridos
- `employee_id` (integer): ID del empleado/asesor
- `month` (integer): Mes (1-12)
- `year` (integer): Año (2020-2030)

## Ejemplo de Petición
```bash
curl -X GET 'http://localhost:8000/api/v1/hr/commissions/sales-detail?employee_id=1&month=7&year=2025' \
     -H 'Authorization: Bearer YOUR_TOKEN' \
     -H 'Accept: application/json'
```

## Estructura de Respuesta

### Respuesta Exitosa (200)
```json
{
    "success": true,
    "data": {
        "employee_id": 1,
        "period": {
            "month": 7,
            "year": 2025
        },
        "summary": {
            "total_sales": 5,
            "total_commission_amount": 73110.00,
            "average_commission_rate": 3.65
        },
        "sales": [
            {
                "sale_number": 1,
                "contract_id": 123,
                "contract_number": "CNT-2025-001",
                "customer_name": "Juan Pérez",
                "project_name": "Residencial Las Flores",
                "lot_number": "A-15",
                "sale_amount": 450000.00,
                "term_months": 48,
                "commission_rate": 3.00,
                "total_commission_amount": 13500.00,
                "sign_date": "2025-07-15",
                "commissions": [
                    {
                        "commission_id": 456,
                        "payment_type": "first_payment",
                        "commission_amount": 9450.00,
                        "payment_status": "pendiente",
                        "payment_date": null,
                        "period_month": 7,
                        "period_year": 2025
                    },
                    {
                        "commission_id": 457,
                        "payment_type": "second_payment",
                        "commission_amount": 4050.00,
                        "payment_status": "pendiente",
                        "payment_date": null,
                        "period_month": 8,
                        "period_year": 2025
                    }
                ]
            }
        ]
    },
    "message": "Detalle de ventas obtenido exitosamente"
}
```

### Respuesta de Error (400/404/500)
```json
{
    "success": false,
    "message": "Error al obtener detalle de ventas: [descripción del error]"
}
```

## Campos de Respuesta

### Resumen (summary)
- `total_sales`: Número total de ventas en el período
- `total_commission_amount`: Monto total de comisiones generadas
- `average_commission_rate`: Tasa promedio de comisión aplicada

### Detalle de Ventas (sales)
Cada venta incluye:
- `sale_number`: Número secuencial de la venta (1, 2, 3...)
- `contract_id`: ID único del contrato
- `contract_number`: Número del contrato
- `customer_name`: Nombre completo del cliente
- `project_name`: Nombre del proyecto inmobiliario
- `lot_number`: Número del lote
- `sale_amount`: Monto de la venta (financing_amount)
- `term_months`: Plazo en meses del financiamiento
- `commission_rate`: Tasa de comisión aplicada (%)
- `total_commission_amount`: Monto total de comisión para esta venta
- `sign_date`: Fecha de firma del contrato
- `commissions`: Array de comisiones divididas

### Comisiones Divididas (commissions)
Cada comisión incluye:
- `commission_id`: ID único de la comisión
- `payment_type`: Tipo de pago (`first_payment`, `second_payment`, `full_payment`)
- `commission_amount`: Monto de esta parte de la comisión
- `payment_status`: Estado del pago (`pendiente`, `pagado`, `procesando`)
- `payment_date`: Fecha de pago (null si no se ha pagado)
- `period_month`: Mes del período de la comisión
- `period_year`: Año del período de la comisión

## Casos de Uso

### 1. Mostrar Tabla Detallada de Ventas
Puedes usar este endpoint para crear una tabla que muestre:
- Número de venta
- Información del contrato y cliente
- Monto de venta y plazo
- Tasa de comisión aplicada
- Comisión total generada
- Estado de pagos divididos

### 2. Dashboard de Asesor
Perfecto para mostrar:
- Resumen de ventas del mes
- Progreso de comisiones
- Detalle de cada venta individual

### 3. Reportes Detallados
Permite generar reportes con:
- Análisis venta por venta
- Seguimiento de comisiones pendientes
- Histórico de rendimiento

## Validaciones
- `employee_id`: Debe existir en la tabla employees
- `month`: Debe estar entre 1 y 12
- `year`: Debe estar entre 2020 y 2030

## Notas Importantes

1. **Cálculo Dinámico**: Las tasas de comisión se calculan dinámicamente basándose en el número de ventas acumuladas del asesor.

2. **Orden Cronológico**: Las ventas se ordenan por fecha de firma para mantener la secuencia correcta de cálculo de comisiones.

3. **Comisiones Divididas**: Cada venta puede tener múltiples registros de comisión (70/30 o 50/50) dependiendo del número total de ventas.

4. **Autenticación**: Requiere token de autenticación válido.

5. **Performance**: El endpoint está optimizado para cargar relaciones necesarias (customer, project, lot) en una sola consulta.

## Ejemplo de Implementación en Frontend (Angular)

```typescript
// En el servicio
getSalesDetail(employeeId: number, month: number, year: number): Observable<SalesDetailResponse> {
  const params = new HttpParams()
    .set('employee_id', employeeId.toString())
    .set('month', month.toString())
    .set('year', year.toString());
    
  return this.http.get<SalesDetailResponse>(
    `${API_ROUTES.HR.COMMISSIONS}/sales-detail`,
    { params }
  );
}

// En el componente
loadSalesDetail() {
  this.commissionService.getSalesDetail(this.employeeId, this.selectedMonth, this.selectedYear)
    .subscribe({
      next: (response) => {
        if (response.success) {
          this.salesDetail = response.data;
          this.updateSummary();
        }
      },
      error: (error) => {
        console.error('Error loading sales detail:', error);
      }
    });
}
```

Este endpoint complementa perfectamente el sistema de comisiones existente, proporcionando la granularidad necesaria para mostrar información detallada venta por venta.