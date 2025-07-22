<?php

use Illuminate\Foundation\Application;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Security\Models\User;

echo "=== ACTUALIZANDO EMPLEADO Y USUARIO ===\n";

$employee = Employee::find(1);
if ($employee) {
    echo "Empleado encontrado: ID {$employee->employee_id}\n";
    
    // Actualizar campos del empleado
    $employee->employee_type = 'asesor_inmobiliario';
    $employee->base_salary = 2500.00;
    $employee->commission_percentage = 5.00;
    $employee->individual_goal = 200000.00; // Meta mensual de S/ 200,000
    $employee->is_commission_eligible = true;
    $employee->is_bonus_eligible = true;
    $employee->employment_status = 'activo';
    $employee->hire_date = '2024-01-01';
    $employee->save();
    
    echo "Empleado actualizado\n";
    
    // Verificar si tiene usuario asociado
    if ($employee->user_id) {
        $user = User::find($employee->user_id);
        if ($user) {
            $user->first_name = 'Juan';
            $user->last_name = 'Pérez';
            $user->email = 'juan.perez@casabonita.com';
            $user->save();
            echo "Usuario actualizado: {$user->first_name} {$user->last_name}\n";
        }
    } else {
        // Crear un usuario si no existe
        $user = User::create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan.perez@casabonita.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now()
        ]);
        
        $employee->user_id = $user->user_id;
        $employee->save();
        
        echo "Usuario creado y asociado: {$user->first_name} {$user->last_name}\n";
    }
    
    echo "Nombre completo del empleado: {$employee->full_name}\n";
} else {
    echo "Empleado no encontrado\n";
}

echo "\n=== EMPLEADO Y USUARIO ACTUALIZADOS ===\n";