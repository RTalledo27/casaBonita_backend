# 📊 Mapeo de Datos: API LOGICWARE → Base de Datos Casa Bonita

## ⚠️ IMPORTANTE: Revisar Mañana con Datos Reales

Este documento se basa en la estructura esperada. **Debes ejecutar esto mañana** cuando se resetee el límite diario:

```bash
php artisan logicware:inspect-data --limit=10
```

Esto te mostrará la estructura REAL de los datos del API.

---

## 🗂️ Estructura de tu Tabla `lots`

```sql
CREATE TABLE lots (
    lot_id                BIGINT PRIMARY KEY AUTO_INCREMENT,
    manzana_id            BIGINT NOT NULL,           -- FK a manzanas
    street_type_id        BIGINT NOT NULL,           -- FK a street_types (REQUERIDO)
    num_lot               TINYINT NOT NULL,          -- Número del lote (0-255)
    area_m2               DECIMAL(10,2) NOT NULL,    -- Área en m²
    area_construction_m2  DECIMAL(14,2) NULL,        -- Área de construcción
    total_price           DECIMAL(14,2) NOT NULL,    -- Precio total
    funding               DECIMAL(14,2) NULL,        -- Financiamiento
    BPP                   DECIMAL(14,2) NULL,        -- Bono BPP
    BFH                   DECIMAL(14,2) NULL,        -- Bono BFH
    initial_quota         DECIMAL(14,2) NULL,        -- Cuota inicial
    currency              CHAR(3) NOT NULL,          -- MXN, USD, etc.
    status                ENUM('disponible', 'reservado', 'vendido') DEFAULT 'disponible',
    UNIQUE (manzana_id, num_lot)
);
```

---

## 🔄 Mapeo Implementado

### Del API LOGICWARE (esperado):
```json
{
  "code": "A-01",                    // ⚠️ CONFIRMAR FORMATO MAÑANA
  "status": "Disponible",            // o "Bloqueado", "Vendido"
  "area": 120.50,                    // o podría ser string "120.50"
  "construction_area": null,         // Opcional
  "price": 850000.00,                // Precio
  "currency": "MXN",                 // Moneda
  "funding": null,                   // ⚠️ VERIFICAR SI EXISTE
  "bpp": null,                       // ⚠️ VERIFICAR SI EXISTE
  "bfh": null,                       // ⚠️ VERIFICAR SI EXISTE
  "initial_quota": null              // ⚠️ VERIFICAR SI EXISTE
}
```

### A tu Base de Datos:
```php
[
    'manzana_id' => 5,                    // Auto-creado si no existe
    'num_lot' => 1,                       // Parseado de "A-01"
    'area_m2' => 120.50,
    'area_construction_m2' => null,
    'total_price' => 850000.00,
    'funding' => null,
    'BPP' => null,
    'BFH' => null,
    'initial_quota' => null,
    'currency' => 'MXN',
    'status' => 'disponible',
    'street_type_id' => 1                 // Default (primer tipo disponible)
]
```

---

## 🔍 Parsing de Códigos

### Formato Esperado: "A-01"
- Manzana: `A`
- Lote: `01` → `1` (convertido a int)

### Regex Implementados:
1. **Letra-Número**: `A-01`, `B-15`, `E2-02`
   - Pattern: `/^([A-Z]+\d*)-(\d+)$/i`

2. **Con guión bajo**: `A_01`, `E2_15`
   - Pattern: `/^([A-Z]+\d*)_(\d+)$/i`

3. **Formato alternativo**: `MZ-A-LT-05`
   - Pattern: `/^MZ[.-]?([A-Z]+\d*)[.-]?LT[.-]?(\d+)$/i`

---

## 📋 Estados Mapeados

| Estado API (LOGICWARE) | Estado BD (Casa Bonita) |
|------------------------|-------------------------|
| `Disponible`           | `disponible`            |
| `Bloqueado`            | `reservado`             |
| `Vendido`              | `vendido`               |
| `Reservado`            | `reservado`             |
| `Available`            | `disponible`            |
| `Reserved`             | `reservado`             |
| `Sold`                 | `vendido`               |
| `Blocked`              | `reservado`             |

⚠️ **Tu BD solo soporta 3 estados**:
- `disponible`
- `reservado`
- `vendido`

---

## ⚠️ Campos Críticos a Verificar Mañana

Cuando ejecutes `php artisan logicware:inspect-data`, verifica si el API trae estos campos:

### Campos que SÍ necesitamos:
- ✅ `code` - Para parsear manzana y lote
- ✅ `status` - Para el estado
- ✅ `area` - Para area_m2
- ✅ `price` - Para total_price
- ✅ `currency` - Para moneda

### Campos que PUEDEN venir o no:
- ❓ `construction_area` - Para area_construction_m2
- ❓ `funding` - Para funding
- ❓ `bpp` - Para BPP
- ❓ `bfh` - Para BFH  
- ❓ `initial_quota` - Para initial_quota

### Campos que necesitamos resolver:
- ❌ `street_type_id` - **No viene del API**
  - Solución: Usamos el primer tipo de calle disponible en tu BD
  - O creamos uno llamado "Sin Especificar"

---

## 🛠️ Ajustes Necesarios Después de Inspeccionar

### 1. Si los nombres de campos son diferentes:

**Ejemplo**: Si el API trae `superficie` en vez de `area`:

```php
// En ExternalLotImportService.php, método transformPropertyToLot()
'area_m2' => $this->parseNumericValue($property['superficie'] ?? 0),
```

### 2. Si los estados tienen nombres diferentes:

**Ejemplo**: Si trae `"En Stock"` en vez de `"Disponible"`:

```php
$statusMap = [
    'En Stock' => 'disponible',
    'Bloqueado' => 'reservado',
    'Vendido' => 'vendido',
];
```

### 3. Si el formato de código es diferente:

**Ejemplo**: Si viene `"Manzana-A-Lote-01"`:

```php
if (preg_match('/^Manzana-([A-Z]+\d*)-Lote-(\d+)$/i', $code, $matches)) {
    return [
        'manzana' => strtoupper($matches[1]),
        'lote' => $matches[2]
    ];
}
```

---

## ✅ Checklist para Mañana

### Paso 1: Inspeccionar datos reales
```bash
php artisan logicware:inspect-data --limit=10
```

### Paso 2: Anotar estructura real
- ¿Qué campos trae el API?
- ¿Cómo se llaman exactamente?
- ¿Qué formato tienen los valores?

### Paso 3: Ajustar mapeo si es necesario
- Editar `ExternalLotImportService.php`
- Método `transformPropertyToLot()`
- Agregar/modificar campos según estructura real

### Paso 4: Prueba de importación
```bash
# Prueba con UN solo lote primero
php artisan lots:sync-external --code=A-01

# Si funciona, importar todos
php artisan lots:sync-external
```

### Paso 5: Verificar en base de datos
```bash
php artisan tinker --execute="echo 'Lotes importados: ' . \Modules\Inventory\Models\Lot::count() . PHP_EOL;"
```

---

## 🚨 Problemas Comunes y Soluciones

### Problema 1: "street_type_id is required"
**Causa**: Tu tabla requiere este campo pero el API no lo trae
**Solución**: Ya implementada - Se usa el primer tipo de calle disponible
**Verificar**: Que tengas al menos un registro en `street_types`

### Problema 2: "num_lot debe ser tinyInteger (0-255)"
**Causa**: El lote tiene número mayor a 255
**Solución**: Cambiar tipo de dato en migración a `smallInteger` o `integer`

### Problema 3: "Duplicate entry for key 'manzana_id_num_lot'"
**Causa**: Ya existe ese lote en esa manzana
**Solución**: Ya manejado - Se actualiza en vez de crear

### Problema 4: "Invalid currency 'XXX'"
**Causa**: Moneda no es de 3 caracteres
**Solución**: Validar y normalizar:
```php
'currency' => strtoupper(substr($property['currency'] ?? 'MXN', 0, 3))
```

---

## 📞 Siguiente Paso

**Mañana a las 6:00 AM** (cuando se resetee el límite):

1. Ejecuta: `php artisan logicware:inspect-data --limit=10`
2. Copia la salida completa
3. Compártela conmigo
4. Ajustaremos el mapeo con los datos reales
5. Haremos la primera importación exitosa

---

## 💡 Tip Final

Si ves que faltan campos críticos (como `funding`, `BPP`, `BFH`), puedes:
- Dejarlos en `null` por ahora
- Completarlos manualmente después
- O pedir a LOGICWARE que los incluyan en la respuesta
