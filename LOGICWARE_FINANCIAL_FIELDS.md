# 💰 Mejoras en Importación de Datos Financieros desde Logicware

## 📋 Resumen de Cambios

Se han implementado mejoras para capturar **TODOS** los datos financieros que envía Logicware, incluyendo campos que faltaban como descuento, cuota balón y bono BPP.

---

## 🆕 Nuevo Campo en Base de Datos

### Campo `discount` agregado a tabla `contracts`

```sql
ALTER TABLE contracts 
ADD COLUMN discount DECIMAL(15,2) DEFAULT 0 
AFTER total_price 
COMMENT 'Descuento aplicado a la venta';
```

**Migración**: `2025_11_18_132751_add_discount_to_contracts_table.php`

---

## 📊 Estructura Financiera Completa

### Campos que ahora se capturan:

| Campo Base de Datos | Campo Logicware | Descripción |
|---------------------|-----------------|-------------|
| `total_price` | `unit['listPrice']` o `unit['basePrice']` | Precio lista ANTES del descuento |
| `discount` | `unit['discount']` o `financing['discount']` | Descuento aplicado |
| `down_payment` | `financing['downPayment']` | Cuota inicial |
| `financing_amount` | `financing['amountToFinance']` | Monto a financiar |
| `balloon_payment` | `financing['balloonPayment']` o `financing['balloon']` | Cuota balón final |
| `bpp` | `financing['bppBonus']` o `financing['bpp']` | Bono Buen Pagador |
| `bfh` | `financing['bfhBonus']` o `financing['bfh']` | Bono Fondo Habitacional |
| `funding` | `financing['funding']` | Financiamiento especial |
| `monthly_payment` | Calculado: `amountToFinance / financingInstallments` | Cuota mensual |

---

## 🔧 Servicios Actualizados

### 1. ExternalLotImportService.php

**Método actualizado**: `processSaleDocumentItem()`

```php
// Extraer datos financieros completos
$listPrice = $this->parseNumericValue($unit['listPrice'] ?? $unit['basePrice'] ?? $unit['total'] ?? 0);
$discount = $this->parseNumericValue($unit['discount'] ?? $financing['discount'] ?? 0);
$balloonPayment = $this->parseNumericValue($financing['balloonPayment'] ?? $financing['balloon'] ?? 0);
$bppBonus = $this->parseNumericValue($financing['bppBonus'] ?? $financing['bpp'] ?? 0);

// Crear contrato con TODOS los campos
$contractData = [
    // ... otros campos ...
    'total_price' => $listPrice, // Precio lista
    'discount' => $discount, // ← NUEVO
    'balloon_payment' => $balloonPayment, // ← CAPTURADO
    'bpp' => $bppBonus, // ← CAPTURADO
    // ...
];
```

### 2. LogicwareContractImporter.php

**Método actualizado**: `createContractFromRealData()`

Implementa la misma lógica de captura de datos financieros completos.

---

## 📅 Generación de Cuotas Mejorada

### Nuevo: Se generan 4 tipos de cuotas

#### 1. Cuotas Iniciales
- Cantidad: `financing['initialInstallments']`
- Monto: `downPayment / initialInstallments`
- Tipo: `'inicial'`

#### 2. Cuotas de Financiamiento
- Cantidad: `financing['financingInstallments']`
- Monto: `amountToFinance / financingInstallments`
- Tipo: `'financiamiento'`

#### 3. 🆕 Cuota Balón (si existe)
- Cantidad: 1
- Monto: `financing['balloonPayment']`
- Tipo: `'balon'`
- Fecha: Al final de las cuotas de financiamiento

#### 4. 🆕 Cuota Bono BPP (si existe)
- Cantidad: 1
- Monto: `financing['bppBonus']`
- Tipo: `'bono_bpp'`
- Fecha: Después de la cuota balón

---

## 📐 Ejemplo Práctico

### Datos de Logicware:
```json
{
  "units": [{
    "listPrice": 37683.33,
    "discount": 5652.50,
    "total": 32030.83
  }],
  "financing": {
    "downPayment": 353.00,
    "amountToFinance": 19099.50,
    "initialInstallments": 1,
    "financingInstallments": 54,
    "balloonPayment": 10000.00,
    "bppBonus": 3000.00
  }
}
```

### Se genera en la BD:

**Tabla `contracts`:**
```
total_price: 37,683.33  (precio lista)
discount: 5,652.50      (descuento aplicado)
down_payment: 353.00
financing_amount: 19,099.50
balloon_payment: 10,000.00
bpp: 3,000.00
monthly_payment: 353.69 (19099.50 / 54)
```

**Tabla `payment_schedules`:**
```
Cuota 1: Inicial - S/ 353.00 (mes 0)
Cuota 2-55: Financiamiento - S/ 353.69 cada una (meses 1-54)
Cuota 56: Balón - S/ 10,000.00 (mes 55)
Cuota 57: Bono BPP - S/ 3,000.00 (mes 56)
```

**Total de cuotas**: 57

---

## ✅ Validación

Para verificar que todo está funcionando:

```bash
# 1. Importar contratos desde Logicware
php artisan logicware:import-sales

# 2. Verificar datos guardados
SELECT 
    contract_number,
    total_price,
    discount,
    total_price - discount as precio_neto,
    balloon_payment,
    bpp
FROM contracts
WHERE source = 'logicware'
LIMIT 10;

# 3. Verificar cuotas generadas
SELECT 
    c.contract_number,
    ps.installment_number,
    ps.type,
    ps.amount,
    ps.due_date
FROM payment_schedules ps
JOIN contracts c ON c.contract_id = ps.contract_id
WHERE c.source = 'logicware'
ORDER BY c.contract_id, ps.installment_number;
```

---

## 🔍 Nombres Alternativos de Campos

El código busca en múltiples variantes por si Logicware usa nombres diferentes:

```php
// Descuento
$unit['discount'] ?? $financing['discount']

// Cuota Balón
$financing['balloonPayment'] ?? $financing['balloon']

// Bono BPP
$financing['bppBonus'] ?? $financing['bpp']

// Precio Lista
$unit['listPrice'] ?? $unit['basePrice'] ?? $unit['total']
```

Esto hace el código más robusto ante cambios en la API.

---

## 📝 Notas Importantes

1. **Precio Lista vs Precio de Venta**:
   - `total_price` guarda el precio LISTA (antes del descuento)
   - El precio de venta real es: `total_price - discount`

2. **Cuotas Opcionales**:
   - Balón y BPP solo se generan SI vienen en los datos de Logicware
   - Si son 0 o no existen, se omiten

3. **Logs Detallados**:
   - Todos los imports ahora logean los montos capturados
   - Buscar en logs: `💰 Contrato creado con datos financieros completos`

---

## 🚀 Próximos Pasos

Para verificar con datos reales del siguiente import:

1. Hacer una importación desde Logicware
2. Revisar los logs para ver qué campos vienen
3. Si hay campos adicionales, agregar en el código las variantes de nombres
4. Verificar que las cuotas se generan correctamente

---

Fecha: 2025-11-18
Autor: Sistema de Importación Logicware v2.0
