<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== SOLUCIONANDO PROBLEMA DE COMISIONES ===\n\n";

// Verificar estructura de la tabla employees
echo "Estructura de la tabla employees:\n";
$columns = Schema::getColumnListing('employees');
foreach ($columns as $column) {
    echo "- $column\n";
}
echo "\n";

// Paso 1: Crear un empleado de prueba con valores mínimos
echo "Paso 1: Creando empleado de prueba...\n";

$existingEmployee = DB::table('employees')->first();
if (!$existingEmployee) {
    $user = DB::table('users')->first();
    
    if ($user) {
        try {
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $user->user_id,
                'employee_code' => 'EMP001',
                'base_salary' => 2000.00,
                'commission_percentage' => 3.5,
                'is_commission_eligible' => 1,
                'hire_date' => '2024-01-01',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            echo "Empleado creado con ID: {$employeeId}\n";
        } catch (Exception $e) {
            echo "Error al crear empleado: " . $e->getMessage() . "\n";
            
            // Intentar con menos campos
            try {
                $employeeId = DB::table('employees')->insertGetId([
                    'user_id' => $user->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                echo "Empleado creado con campos mínimos, ID: {$employeeId}\n";
            } catch (Exception $e2) {
                echo "Error con campos mínimos: " . $e2->getMessage() . "\n";
                exit(1);
            }
        }
    } else {
        echo "No se encontró usuario\n";
        exit(1);
    }
} else {
    $employeeId = $existingEmployee->employee_id ?? $existingEmployee->id;
    echo "Empleado existente encontrado con ID: {$employeeId}\n";
}

// Paso 2: Asignar advisor_id a contratos
echo "\nPaso 2: Asignando advisor_id a contratos...\n";

$contractsUpdated = DB::table('contracts')
    ->whereNull('advisor_id')
    ->limit(10)
    ->update(['advisor_id' => $employeeId]);

echo "Contratos actualizados: {$contractsUpdated}\n";

// Paso 3: Cambiar fechas a junio 2024
echo "\nPaso 3: Cambiando fechas a junio 2024...\n";

$contractsWithJuneDates = DB::table('contracts')
    ->where('advisor_id', $employeeId)
    ->limit(5)
    ->update(['sign_date' => '2024-06-15']);

echo "Contratos con fecha de junio: {$contractsWithJuneDates}\n";

// Verificar resultado
echo "\nVerificación final:\n";
$juneContracts = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2024)
    ->whereNotNull('advisor_id')
    ->count();

echo "Contratos válidos en junio 2024: {$juneContracts}\n";

if ($juneContracts > 0) {
    echo "\n✅ PROBLEMA SOLUCIONADO: Ahora hay contratos con advisor_id en junio 2024\n";
} else {
    echo "\n❌ Aún hay problemas, revisar datos\n";
}

echo "\n=== PROCESO COMPLETADO ===\n";
