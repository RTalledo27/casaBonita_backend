# 🎁 Sistema Automático de Bonos - Guía Completa

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Tipos de Bonos](#tipos-de-bonos)
4. [Cálculo Automático](#cálculo-automático)
5. [Integración con Nóminas](#integración-con-nóminas)
6. [Comandos Artisan](#comandos-artisan)
7. [Scheduler (Tareas Programadas)](#scheduler-tareas-programadas)
8. [API Endpoints](#api-endpoints)
9. [Ejemplos de Uso](#ejemplos-de-uso)

---

## 🎯 Descripción General

El sistema de bonos automático calcula y asigna bonos a empleados basándose en sus logros, metas cumplidas y rendimiento. Los bonos se integran automáticamente en el sistema de nóminas.

### Características Principales:

✅ **Cálculo automático** basado en reglas configurables  
✅ **Integración con nóminas** - Los bonos se incluyen automáticamente  
✅ **Múltiples tipos** - Individual, equipo, trimestral, quincenal, cobranza  
✅ **Flexible** - Soporta bonos por monto fijo o porcentaje  
✅ **Auditable** - Registra quién creó/aprobó cada bono  
✅ **Programable** - Ejecución automática mediante scheduler  

---

## 🏗️ Arquitectura del Sistema

### Tablas Principales:

#### 1. `bonus_types` - Tipos de Bonos
Define las categorías de bonos disponibles.

```sql
- bonus_type_id (PK)
- type_code (unique): INDIVIDUAL_GOAL, TEAM_GOAL, QUARTERLY, etc.
- type_name
- calculation_method: percentage_of_goal, fixed_amount, sales_count, etc.
- is_automatic: boolean - Si se calcula automáticamente
- requires_approval: boolean - Si necesita aprobación
- applicable_employee_types: JSON array - Tipos de empleados elegibles
- frequency: monthly, quarterly, biweekly, annual
```

#### 2. `bonus_goals` - Metas de Bonos
Define rangos y montos para cada tipo de bono.

```sql
- bonus_goal_id (PK)
- bonus_type_id (FK)
- goal_name
- min_achievement: % mínimo para calificar
- max_achievement: % máximo considerado
- bonus_amount: Monto fijo del bono
- bonus_percentage: O porcentaje del salario
- employee_type: Tipo de empleado aplicable
- valid_from / valid_until: Vigencia
```

#### 3. `bonuses` - Bonos Asignados
Registros de bonos reales asignados a empleados.

```sql
- bonus_id (PK)
- employee_id (FK)
- bonus_type_id (FK)
- bonus_goal_id (FK) - Opcional
- bonus_amount: Monto a pagar
- target_amount: Meta objetivo
- achieved_amount: Monto logrado
- achievement_percentage: % de cumplimiento
- payment_status: pendiente, pagado, cancelado
- period_month / period_year: Período del bono
- approved_by / approved_at: Aprobación
```

---

## 💰 Tipos de Bonos

### 1. **Meta Individual** (`INDIVIDUAL_GOAL`)
Basado en el cumplimiento de metas individuales de venta.

**Ejemplo de Configuración:**
- 120%+ de meta → $1,000
- 102-119% de meta → $600
- Menos de 102% → $0

**Frecuencia:** Mensual

---

### 2. **Meta de Equipo** (`TEAM_GOAL`)
Bono adicional cuando el equipo/sucursal cumple su meta.

**Requisito:** El empleado debe haber obtenido bono individual primero.

**Ejemplo:**
- 110%+ de meta de sucursal → $500
- 102-109% de meta → $300

**Frecuencia:** Mensual

---

### 3. **Bono Trimestral** (`QUARTERLY`)
Para asesores inmobiliarios por ventas trimestrales.

**Ejemplo:**
- 30+ ventas en el trimestre → $1,000

**Frecuencia:** Trimestral (marzo, junio, septiembre, diciembre)

---

### 4. **Bono Quincenal** (`BIWEEKLY`)
Para asesores inmobiliarios por ventas quincenales.

**Ejemplo:**
- 6+ ventas en la quincena → $500

**Frecuencia:** Quincenal (días 1 y 16 de cada mes)

---

### 5. **Bono por Cobranza** (`COLLECTION`)
Basado en el monto cobrado en el período.

**Ejemplo:**
- $50,000+ cobrado → $500

**Frecuencia:** Mensual

---

## 🤖 Cálculo Automático

### Comando Principal:

```bash
php artisan bonuses:calculate
```

### Opciones Disponibles:

```bash
# Calcular bonos de un mes específico
php artisan bonuses:calculate --month=10 --year=2025

# Calcular solo para un empleado
php artisan bonuses:calculate --employee=5

# Calcular solo un tipo de bono
php artisan bonuses:calculate --type=INDIVIDUAL_GOAL

# Modo simulación (no crea bonos)
php artisan bonuses:calculate --dry-run

# Combinaciones
php artisan bonuses:calculate --month=11 --year=2025 --employee=10 --dry-run
```

### Flujo de Cálculo Automático:

```
1. Obtener tipos de bonos automáticos activos
   ↓
2. Para cada tipo de bono:
   ↓
3. Obtener empleados elegibles (filtros de tipo, team, etc.)
   ↓
4. Verificar que no exista bono del mismo tipo/período
   ↓
5. Calcular logro del empleado (ventas, metas, cobranza)
   ↓
6. Buscar meta (BonusGoal) aplicable según el logro
   ↓
7. Si cumple con min_achievement:
   ↓
8. Crear bono con estado 'pendiente'
```

---

## 💼 Integración con Nóminas

### Flujo Completo:

```
1. CALCULAR BONOS (automático o manual)
   ↓
   Bonos creados con payment_status='pendiente'
   
2. GENERAR NÓMINA
   ↓
   PayrollService obtiene:
   - Salario base
   - Comisiones pendientes
   - BONOS PENDIENTES ✅
   - Horas extra
   ↓
   Calcula salario bruto = suma de todos los conceptos
   ↓
   Calcula descuentos (impuestos, AFP, etc.)
   ↓
   Genera registro de nómina con estado='borrador'

3. PROCESAR NÓMINA
   ↓
   Status cambio a 'procesado'

4. APROBAR NÓMINA
   ↓
   Status cambio a 'aprobado'
   ↓
   BONOS MARCADOS COMO 'PAGADO' ✅
   COMISIONES MARCADAS COMO 'PAGADO' ✅
   ↓
   payment_date = fecha actual
```

### Código Relevante:

```php
// En PayrollService::generatePayrollForEmployee()
$bonuses = $this->bonusRepo->getAll([
    'employee_id' => $employeeId,
    'period_month' => $month,
    'period_year' => $year,
    'payment_status' => 'pendiente'  // Solo bonos pendientes
]);

$bonusesAmount = $bonuses->sum('bonus_amount');
$grossSalary = $baseSalary + $commissionsAmount + $bonusesAmount + $overtimeAmount;
```

```php
// En PayrollService::approvePayroll()
// Marcar bonos como pagados automáticamente
protected function markBonusesAsPaid(Payroll $payroll): void
{
    $bonuses = $this->bonusRepo->getAll([
        'employee_id' => $payroll->employee_id,
        'period_month' => (int) $month,
        'period_year' => (int) $year,
        'payment_status' => 'pendiente'
    ]);

    foreach ($bonuses as $bonus) {
        $this->bonusRepo->update($bonus->bonus_id, [
            'payment_status' => 'pagado',
            'payment_date' => now()->toDateString()
        ]);
    }
}
```

---

## ⏰ Scheduler (Tareas Programadas)

### Configuración en `routes/console.php`:

```php
// Bonos mensuales - Día 1 de cada mes a las 00:05
Schedule::command('bonuses:calculate')
    ->monthlyOn(1, '00:05')
    ->timezone('America/Lima');

// Bonos quincenales - Días 1 y 16 a la 1:00 AM
Schedule::command('bonuses:calculate --type=BIWEEKLY')
    ->cron('0 1 1,16 * *')
    ->timezone('America/Lima');

// Bonos trimestrales - Primer día de marzo, junio, sept, dic a las 2:00 AM
Schedule::command('bonuses:calculate --type=QUARTERLY')
    ->cron('0 2 1 3,6,9,12 *')
    ->timezone('America/Lima');
```

### Activar el Scheduler:

#### Opción 1: Cron Job (Producción)
Agregar a crontab:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

#### Opción 2: Supervisor (Recomendado para Producción)
```ini
[program:laravel-scheduler]
process_name=%(program_name)s_%(process_num)02d
command=php /path/artisan schedule:work
autostart=true
autorestart=true
user=www-data
numprocs=1
```

#### Opción 3: Windows Task Scheduler
Crear tarea que ejecute cada minuto:
```powershell
schtasks /create /sc minute /mo 1 /tn "LaravelScheduler" /tr "C:\xampp\php\php.exe C:\path\artisan schedule:run"
```

#### Opción 4: Desarrollo Local
```bash
php artisan schedule:work
```

---

## 🌐 API Endpoints

### Listar Bonos
```http
GET /api/hr/bonuses
```

**Query Params:**
- `employee_id` - Filtrar por empleado
- `period_month` - Filtrar por mes
- `period_year` - Filtrar por año
- `payment_status` - pendiente, pagado, cancelado
- `bonus_type_id` - Filtrar por tipo

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "bonus_id": 1,
      "employee_id": 5,
      "bonus_name": "Meta Individual 120%",
      "bonus_amount": 1000.00,
      "payment_status": "pendiente",
      "period_month": 10,
      "period_year": 2025
    }
  ]
}
```

---

### Procesar Bonos Automáticos
```http
POST /api/hr/bonuses/process-automatic
```

**Body:**
```json
{
  "month": 10,
  "year": 2025,
  "employee_id": null,  // Opcional
  "bonus_type": null     // Opcional
}
```

**Response:**
```json
{
  "success": true,
  "message": "Bonos procesados exitosamente",
  "data": {
    "individual": [...],
    "team": [...],
    "quarterly": [...]
  }
}
```

---

## 📚 Ejemplos de Uso

### Ejemplo 1: Calcular Bonos Mensuales (Modo Simulación)

```bash
php artisan bonuses:calculate --month=10 --year=2025 --dry-run
```

**Salida:**
```
🎁 Iniciando cálculo de bonos automáticos...

📅 Período: 2025-10
⚠️  MODO SIMULACIÓN - No se crearán bonos reales

📊 RESUMEN DE BONOS CALCULADOS
─────────────────────────────────────────
  Individual: 15 bonos - S/ 12,600.00
  Team: 12 bonos - S/ 5,400.00
  Collection: 5 bonos - S/ 2,500.00

  TOTAL: 32 bonos - S/ 20,500.00

✅ Cálculo de bonos completado exitosamente
```

---

### Ejemplo 2: Generar Nómina Incluyendo Bonos

```php
// En el controlador o servicio
$payroll = $payrollService->generatePayrollForEmployee(
    employeeId: 5,
    month: 10,
    year: 2025
);

// La nómina incluirá automáticamente:
// - Salario base: $3,000
// - Comisiones: $2,500
// - Bonos: $1,000 (Meta individual) + $500 (Meta equipo)
// - Total bruto: $7,000
```

---

### Ejemplo 3: Aprobar Nómina y Marcar Bonos como Pagados

```php
$approved = $payrollService->approvePayroll(
    payrollId: 123,
    approvedBy: $currentUser->employee_id
);

// Esto automáticamente:
// 1. Cambia payroll.status a 'aprobado'
// 2. Marca TODOS los bonos del período como 'pagado'
// 3. Marca TODAS las comisiones del período como 'pagado'
// 4. Registra approved_by y approved_at
```

---

## ✅ Checklist de Implementación

- [x] Modelos: Bonus, BonusType, BonusGoal
- [x] Servicio: BonusService con cálculos automáticos
- [x] Comando Artisan: bonuses:calculate
- [x] Integración: PayrollService incluye bonos
- [x] Marcado automático: Bonos como 'pagado' al aprobar nómina
- [x] Scheduler: Tareas programadas configuradas
- [ ] Tests unitarios para cálculos
- [ ] Documentación de API completa
- [ ] Notificaciones por email cuando se crean bonos
- [ ] Dashboard de bonos en el frontend

---

## 🚀 Próximos Pasos

1. **Configurar BonusTypes y BonusGoals** en la base de datos
2. **Ejecutar comando en modo simulación** para validar
3. **Configurar scheduler** según entorno (cron/supervisor/task scheduler)
4. **Crear roles y permisos** para gestión de bonos
5. **Implementar notificaciones** a empleados cuando reciben bonos
6. **Agregar reportes** de bonos pagados por período
7. **Integrar con contabilidad** para registros financieros

---

## 📞 Soporte

Para dudas o problemas:
- Revisar logs: `storage/logs/laravel.log`
- Ejecutar en modo debug: `php artisan bonuses:calculate --dry-run -vvv`
- Verificar configuración de timezone en `config/app.php`

---

**Fecha de creación:** 2025-11-06  
**Versión:** 1.0  
**Autor:** Sistema Casa Bonita
