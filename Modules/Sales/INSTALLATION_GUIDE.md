# Guía de Instalación - Sistema de Importación de Contratos

## Estado Actual

Se han creado todos los archivos necesarios para el sistema de importación de contratos/reservaciones:

✅ **Archivos Creados:**
- `ContractImportService.php` - Servicio principal de importación
- `ContractImportController.php` - Controlador con endpoints API
- `ContractImportRequest.php` - Validación de peticiones
- `ContractImportLog.php` - Modelo para logs de importación
- `ProcessContractImportJob.php` - Job para procesamiento asíncrono
- `CheckContractImportPermission.php` - Middleware de permisos
- Migración para tabla `contract_import_logs`
- Rutas API configuradas

## Pasos Pendientes para Completar la Instalación

### 1. Resolver Problemas de Migración

**Problema:** Las migraciones fallan porque algunas tablas ya existen.

**Solución:**
```bash
# Opción A: Migrar solo la tabla específica (recomendado)
php artisan migrate --path=Modules/Sales/database/migrations --force

# Opción B: Si persisten errores, crear la tabla manualmente
# Ejecutar este SQL en la base de datos:
```

```sql
CREATE TABLE IF NOT EXISTS `contract_import_logs` (
  `import_log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `total_rows` int(11) DEFAULT 0,
  `success_count` int(11) DEFAULT 0,
  `error_count` int(11) DEFAULT 0,
  `error_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`error_details`)),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`import_log_id`),
  KEY `contract_import_logs_user_id_index` (`user_id`),
  KEY `contract_import_logs_status_index` (`status`),
  KEY `contract_import_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Verificar Dependencias

```bash
# Verificar que PhpSpreadsheet esté instalado
composer show phpoffice/phpspreadsheet

# Si no está instalado:
composer require phpoffice/phpspreadsheet
```

### 3. Configurar Colas (Opcional)

Para el procesamiento asíncrono:

```bash
# En .env agregar:
QUEUE_CONNECTION=database

# Ejecutar worker:
php artisan queue:work
```

### 4. Configurar Permisos

Asegurarse de que los usuarios tengan los permisos necesarios:
- `import_contracts`
- `manage_sales`
- O roles: `administrador`, `gerente_ventas`, `supervisor_ventas`

### 5. Probar la Funcionalidad

```bash
# Verificar rutas
php artisan route:list --name=sales

# Probar endpoint de validación
curl -X POST http://localhost:8000/api/v1/sales/import/contracts/validate \
  -H "Authorization: Bearer {token}" \
  -F "file=@ejemplo.xlsx"
```

## Endpoints Disponibles

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/v1/sales/import/contracts` | Importación síncrona |
| POST | `/api/v1/sales/import/contracts/async` | Importación asíncrona |
| POST | `/api/v1/sales/import/contracts/validate` | Validar estructura |
| GET | `/api/v1/sales/import/contracts/template` | Descargar plantilla |
| GET | `/api/v1/sales/import/contracts/history` | Historial |
| GET | `/api/v1/sales/import/contracts/status/{id}` | Estado de importación |
| GET | `/api/v1/sales/import/contracts/stats` | Estadísticas |

## Headers del Excel Soportados

### Requeridos:
- `ASESOR` - Nombre del asesor
- `N° VENTA` - Número de venta único
- `NOMBRE DE CLIENTE` - Nombre completo
- `N° DE LOTE` - Número del lote
- `MZ` - Manzana
- `FECHA` - Fecha de venta

### Opcionales:
- `SI TIENE CONTRATO` - SI/NO
- `CELULAR1`, `CELULAR2` - Teléfonos
- `S/.` - Precio total
- `REEMBOLSO` - Monto reembolso
- `CUOTA INICIAL` - Cuota inicial
- `N° DE CUOTAS` - Cantidad cuotas
- `SEPARACION` - Monto separación
- `CONTADO` - Pago contado
- `INICIAL` - Monto inicial
- `FINANCIADO` - Monto financiado

## Funcionalidades Implementadas

✅ **Validación de archivos Excel/CSV**
✅ **Procesamiento síncrono y asíncrono**
✅ **Creación automática de clientes**
✅ **Búsqueda y creación de lotes**
✅ **Asignación de asesores**
✅ **Generación de reservaciones y contratos**
✅ **Registro detallado de errores**
✅ **Historial de importaciones**
✅ **Estadísticas de importación**
✅ **Control de permisos**
✅ **Plantilla de ejemplo**

## Próximos Pasos

1. **Ejecutar migración** para crear tabla de logs
2. **Probar endpoints** con archivos de ejemplo
3. **Configurar frontend** para usar los endpoints
4. **Entrenar usuarios** en el uso del sistema
5. **Monitorear logs** y ajustar según necesidades

## Notas Importantes

- El sistema maneja automáticamente la creación de clientes si no existen
- Los lotes se buscan por número y manzana
- Los asesores deben existir previamente en el sistema
- Se generan números de contrato únicos automáticamente
- Los errores se registran por fila sin detener el procesamiento

## Soporte

Todos los archivos están listos y el sistema está completamente implementado. Solo falta resolver el tema de las migraciones para que esté 100% funcional.