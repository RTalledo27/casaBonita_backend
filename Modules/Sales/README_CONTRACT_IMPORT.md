# Sistema de Importación de Contratos/Reservaciones

## Descripción

Este módulo permite importar contratos y reservaciones desde archivos Excel para gestionar las ventas y procesar comisiones y bonos.

## Características

- ✅ Importación desde archivos Excel (.xlsx, .xls) y CSV
- ✅ Validación de estructura de archivos
- ✅ Procesamiento síncrono y asíncrono
- ✅ Registro de historial de importaciones
- ✅ Manejo de errores detallado
- ✅ Creación automática de clientes y lotes
- ✅ Asignación de asesores
- ✅ Generación de contratos y reservaciones

## Instalación

### 1. Ejecutar Migraciones

```bash
# Ejecutar la migración específica para logs de importación
php artisan migrate --path=Modules/Sales/database/migrations/2024_01_15_000000_create_contract_import_logs_table.php
```

### 2. Configurar Colas (Opcional)

Para el procesamiento asíncrono, asegúrate de que las colas estén configuradas:

```bash
# Ejecutar worker de colas
php artisan queue:work
```

## Estructura del Archivo Excel

### Headers Requeridos

- **ASESOR**: Nombre del asesor de ventas
- **N° VENTA**: Número único de la venta
- **NOMBRE DE CLIENTE**: Nombre completo del cliente
- **N° DE LOTE**: Número del lote
- **MZ**: Manzana donde está ubicado el lote
- **FECHA**: Fecha de la venta (YYYY-MM-DD)
- **INICIAL**: Monto de cuota inicial
- **FINANCIADO**: Monto financiado

### Headers Opcionales

- **SI TIENE CONTRATO**: SI/NO si ya tiene contrato firmado
- **CELULAR1/CELULAR2**: Teléfonos del cliente
- **S/.**: Precio total del lote
- **REEMBOLSO**: Monto de reembolso
- **CUOTA INICIAL**: Cuota inicial específica
- **N° DE CUOTAS**: Cantidad de cuotas para el financiamiento
- **SEPARACION**: Monto de separación del lote
- **CONTADO**: Pago al contado
- **BPP**: Bono por Pronto Pago
- **BFH**: Bono Fondo de Habitación
- **CUOTA_INICIAL_QUOTA**: Cuota inicial específica del contrato

### Ejemplo de Archivo Excel

| ASESOR | N° VENTA | NOMBRE DE CLIENTE | N° DE LOTE | MZ | FECHA | INICIAL | FINANCIADO | BPP | BFH | CUOTA_INICIAL_QUOTA |
|--------|----------|-------------------|------------|----|---------|---------|-----------|-----|-----|--------------------|
| Juan Pérez | V001 | María García López | 15 | A | 2024-01-15 | 15000 | 135000 | 2500 | 1800 | 5000 |
| Ana Rodríguez | V002 | Carlos Mendoza Silva | 22 | B | 2024-01-20 | 0 | 0 | 0 | 0 | 0 |

## API Endpoints

### Importación Síncrona
```http
POST /api/v1/sales/import/contracts
Content-Type: multipart/form-data

file: [archivo Excel]
```

### Importación Asíncrona
```http
POST /api/v1/sales/import/contracts/async
Content-Type: multipart/form-data

file: [archivo Excel]
```

### Validar Estructura
```http
POST /api/v1/sales/import/contracts/validate
Content-Type: multipart/form-data

file: [archivo Excel]
```

### Obtener Plantilla
```http
GET /api/v1/sales/import/contracts/template
```

### Historial de Importaciones
```http
GET /api/v1/sales/import/contracts/history?per_page=15&status=completed
```

### Estado de Importación
```http
GET /api/v1/sales/import/contracts/status/{importLogId}
```

### Estadísticas
```http
GET /api/v1/sales/import/contracts/stats?days=30
```

## Respuestas de la API

### Importación Exitosa
```json
{
  "success": true,
  "message": "Procesadas 10 filas exitosamente, 0 errores",
  "data": {
    "processed": 10,
    "errors": 0,
    "error_details": []
  }
}
```

### Importación con Errores
```json
{
  "success": true,
  "message": "Procesadas 8 filas exitosamente, 2 errores",
  "data": {
    "processed": 8,
    "errors": 2,
    "error_details": [
      {
        "row": 3,
        "error": "Fila 3: Asesor es requerido",
        "data": [...]
      }
    ]
  }
}
```

## Lógica de Procesamiento

### 1. Validación de Archivo
- Verificar formato (Excel/CSV)
- Validar headers requeridos
- Verificar tamaño máximo (10MB)

### 2. Procesamiento de Datos
- Mapear headers a campos internos
- Validar datos requeridos por fila
- Buscar o crear clientes
- Buscar o crear lotes y manzanas
- Asignar asesores

### 3. Creación de Registros
- Crear reservación
- Crear contrato (si corresponde)
- Registrar en log de importación

## Manejo de Errores

### Errores Comunes
- **Archivo vacío**: El archivo no contiene datos
- **Headers faltantes**: Faltan columnas requeridas
- **Asesor no encontrado**: El asesor especificado no existe
- **Datos inválidos**: Formatos de fecha o números incorrectos

### Recuperación de Errores
- Los errores se registran por fila
- Las filas válidas se procesan normalmente
- Se proporciona detalle completo de errores

## Configuración

### Variables de Entorno
```env
# Configuración de colas (opcional)
QUEUE_CONNECTION=database

# Configuración de almacenamiento
FILESYSTEM_DISK=local
```

### Permisos Requeridos
- `import_contracts`: Permiso específico para importar
- `manage_sales`: Permiso general de gestión de ventas
- Roles permitidos: `administrador`, `gerente_ventas`, `supervisor_ventas`

## Archivos Creados

### Servicios
- `ContractImportService.php`: Lógica principal de importación

### Controladores
- `ContractImportController.php`: Endpoints de API

### Modelos
- `ContractImportLog.php`: Registro de importaciones

### Jobs
- `ProcessContractImportJob.php`: Procesamiento asíncrono

### Requests
- `ContractImportRequest.php`: Validación de peticiones

### Middleware
- `CheckContractImportPermission.php`: Verificación de permisos

### Migraciones
- `create_contract_import_logs_table.php`: Tabla de logs

## Uso Recomendado

1. **Archivos pequeños (< 100 filas)**: Usar importación síncrona
2. **Archivos grandes (> 100 filas)**: Usar importación asíncrona
3. **Validación previa**: Siempre validar estructura antes de importar
4. **Monitoreo**: Revisar logs y estadísticas regularmente

## Soporte

Para problemas o dudas sobre el sistema de importación:
1. Revisar logs de errores
2. Verificar estructura del archivo Excel
3. Consultar historial de importaciones
4. Contactar al administrador del sistema