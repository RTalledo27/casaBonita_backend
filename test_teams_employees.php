<?php

require_once 'vendor/autoload.php';

// Configurar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\app\Models\Team;
use Modules\HumanResources\app\Models\Employee;

echo "=== PRUEBA DE EQUIPOS Y EMPLEADOS ===\n\n";

// Obtener todos los equipos
$teams = Team::all();
echo "Total de equipos: " . $teams->count() . "\n\n";

foreach ($teams as $team) {
    echo "Equipo: {$team->team_name} (ID: {$team->team_id})\n";
    echo "Estado: {$team->status}\n";
    
    // Contar empleados por team_id
    $employeeCount = Employee::where('team_id', $team->team_id)->count();
    echo "Empleados asignados (por team_id): {$employeeCount}\n";
    
    // Obtener empleados específicos
    $employees = Employee::where('team_id', $team->team_id)->get();
    if ($employees->count() > 0) {
        echo "Empleados:\n";
        foreach ($employees as $employee) {
            $userName = $employee->user ? $employee->user->first_name . ' ' . $employee->user->last_name : 'Sin usuario';
            echo "  - {$userName} (ID: {$employee->employee_id}, team_id: {$employee->team_id})\n";
        }
    } else {
        echo "  No hay empleados asignados\n";
    }
    echo "\n";
}

// Verificar empleados sin equipo
$employeesWithoutTeam = Employee::whereNull('team_id')->count();
echo "Empleados sin equipo asignado: {$employeesWithoutTeam}\n\n";

// Verificar total de empleados
$totalEmployees = Employee::count();
echo "Total de empleados: {$totalEmployees}\n";

echo "\n=== FIN DE LA PRUEBA ===\n";