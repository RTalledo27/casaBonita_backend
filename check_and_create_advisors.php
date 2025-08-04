<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Security\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "=== VERIFICANDO Y CREANDO ASESORES INMOBILIARIOS ===\n\n";

try {
    // 1. Verificar asesores existentes
    $existingAdvisors = Employee::where('employee_type', 'asesor_inmobiliario')
                               ->where('employment_status', 'activo')
                               ->with('user')
                               ->get();
    
    echo "Asesores inmobiliarios encontrados: " . $existingAdvisors->count() . "\n";
    
    if ($existingAdvisors->count() > 0) {
        echo "\nAsesores existentes:\n";
        foreach ($existingAdvisors as $advisor) {
            $userName = $advisor->user ? $advisor->user->first_name . ' ' . $advisor->user->last_name : 'Sin usuario';
            echo "- ID: {$advisor->employee_id}, Código: {$advisor->employee_code}, Nombre: {$userName}\n";
        }
        echo "\n✅ Ya existen asesores en el sistema. No es necesario crear nuevos.\n";
    } else {
        echo "\n❌ No se encontraron asesores inmobiliarios activos.\n";
        echo "Creando asesor por defecto...\n\n";
        
        DB::beginTransaction();
        
        try {
            // 2. Crear usuario por defecto
            $defaultUser = User::create([
                'username' => 'asesor.defecto',
                'first_name' => 'Asesor',
                'last_name' => 'Por Defecto',
                'email' => 'asesor.defecto@casabonita.com',
                'password_hash' => Hash::make('password123'),
                'phone' => '999999999',
                'dni' => '12345678',
                'status' => 'active',
                'position' => 'Asesor Inmobiliario',
                'department' => 'Ventas'
            ]);
            
            echo "✅ Usuario creado: {$defaultUser->first_name} {$defaultUser->last_name} (ID: {$defaultUser->user_id})\n";
            
            // 3. Generar código de empleado
            $lastEmployee = Employee::orderBy('employee_id', 'desc')->first();
            $nextNumber = $lastEmployee ? (int)substr($lastEmployee->employee_code, 3) + 1 : 1;
            $employeeCode = 'EMP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            
            // 4. Crear empleado asesor
            $defaultAdvisor = Employee::create([
                'user_id' => $defaultUser->user_id,
                'employee_code' => $employeeCode,
                'employee_type' => 'asesor_inmobiliario',
                'base_salary' => 1500.00,
                'commission_percentage' => 3.00,
                'individual_goal' => 50000.00,
                'is_commission_eligible' => true,
                'is_bonus_eligible' => true,
                'hire_date' => now(),
                'employment_status' => 'activo',
                'contract_type' => 'indefinido',
                'work_schedule' => 'tiempo_completo'
            ]);
            
            echo "✅ Empleado asesor creado: {$employeeCode} (ID: {$defaultAdvisor->employee_id})\n";
            
            DB::commit();
            
            echo "\n🎉 Asesor por defecto creado exitosamente:\n";
            echo "   - Nombre: {$defaultUser->first_name} {$defaultUser->last_name}\n";
            echo "   - Email: {$defaultUser->email}\n";
            echo "   - Código Empleado: {$employeeCode}\n";
            echo "   - Tipo: asesor_inmobiliario\n";
            echo "   - Estado: activo\n";
            echo "\n✅ Ahora puede proceder con la importación de contratos.\n";
            
        } catch (Exception $e) {
            DB::rollBack();
            echo "❌ Error al crear asesor por defecto: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    // 5. Verificación final
    $finalCount = Employee::where('employee_type', 'asesor_inmobiliario')
                         ->where('employment_status', 'activo')
                         ->count();
    
    echo "\n=== RESUMEN FINAL ===\n";
    echo "Total de asesores inmobiliarios activos: {$finalCount}\n";
    
    if ($finalCount > 0) {
        echo "✅ El sistema está listo para importar contratos.\n";
    } else {
        echo "❌ Aún no hay asesores disponibles. Verifique los errores anteriores.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL SCRIPT ===\n";