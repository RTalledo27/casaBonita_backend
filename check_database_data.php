<?php

require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\Employee;

echo "=== VERIFICACIÓN DE DATOS EN BASE DE DATOS ===\n\n";

// 1. Verificar empleados
echo "1. EMPLEADOS:\n";
$employees = Employee::all();
echo "Total empleados: " . $employees->count() . "\n";
foreach ($employees as $employee) {
    echo "  - ID: {$employee->id}, Nombre: {$employee->first_name} {$employee->last_name}, Estado: {$employee->status}\n";
}
echo "\n";

// 2. Verificar comisiones
echo "2. COMISIONES:\n";
$commissions = Commission::all();
echo "Total comisiones: " . $commissions->count() . "\n";
if ($commissions->count() > 0) {
    foreach ($commissions->take(10) as $commission) {
        echo "  - ID: {$commission->id}, Empleado: {$commission->employee_id}, Monto: {$commission->amount}, Fecha: {$commission->created_at}\n";
    }
} else {
    echo "  No hay comisiones registradas\n";
}
echo "\n";

// 3. Verificar bonos
echo "3. BONOS:\n";
$bonuses = Bonus::all();
echo "Total bonos: " . $bonuses->count() . "\n";
if ($bonuses->count() > 0) {
    foreach ($bonuses->take(10) as $bonus) {
        echo "  - ID: {$bonus->id}, Empleado: {$bonus->employee_id}, Monto: {$bonus->amount}, Fecha: {$bonus->created_at}\n";
    }
} else {
    echo "  No hay bonos registrados\n";
}
echo "\n";

// 4. Verificar tablas relacionadas
echo "4. TABLAS RELACIONADAS:\n";
try {
    $contracts = DB::table('contracts')->count();
    echo "Total contratos: $contracts\n";
} catch (Exception $e) {
    echo "Error al consultar contratos: " . $e->getMessage() . "\n";
}

try {
    $sales = DB::table('sales')->count();
    echo "Total ventas: $sales\n";
} catch (Exception $e) {
    echo "Error al consultar ventas: " . $e->getMessage() . "\n";
}

echo "\n=== FIN VERIFICACIÓN ===\n";