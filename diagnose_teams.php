<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\app\Models\Team;
use Modules\HumanResources\app\Models\Employee;

echo "=== DIAGNÓSTICO DE EQUIPOS Y EMPLEADOS ===\n\n";

try {
    // Obtener todos los equipos
    $teams = Team::all();
    echo "📊 Total de equipos: " . $teams->count() . "\n\n";
    
    // Obtener todos los empleados
    $employees = Employee::all();
    echo "👥 Total de empleados: " . $employees->count() . "\n\n";
    
    // Empleados con team_id
    $employeesWithTeam = Employee::whereNotNull('team_id')->get();
    echo "🏢 Empleados con team_id asignado: " . $employeesWithTeam->count() . "\n\n";
    
    // Mostrar algunos ejemplos de empleados con team_id
    echo "📋 Ejemplos de empleados con team_id:\n";
    foreach ($employeesWithTeam->take(5) as $employee) {
        $user = $employee->user;
        $userName = $user ? $user->first_name . ' ' . $user->last_name : 'Sin usuario';
        echo "  - ID: {$employee->employee_id}, Nombre: {$userName}, Team ID: {$employee->team_id}\n";
    }
    echo "\n";
    
    // Contar miembros por equipo
    echo "🏆 Conteo de miembros por equipo:\n";
    foreach ($teams as $team) {
        $memberCount = Employee::where('team_id', $team->team_id)->count();
        echo "  - {$team->team_name}: {$memberCount} miembros\n";
    }
    echo "\n";
    
    // Verificar si hay empleados sin team_id
    $employeesWithoutTeam = Employee::whereNull('team_id')->count();
    echo "❓ Empleados sin team_id: {$employeesWithoutTeam}\n\n";
    
    // Verificar estructura de datos de un empleado
    $sampleEmployee = Employee::with('user')->first();
    if ($sampleEmployee) {
        echo "🔍 Estructura de datos de empleado de muestra:\n";
        echo "  - employee_id: {$sampleEmployee->employee_id}\n";
        echo "  - team_id: " . ($sampleEmployee->team_id ?? 'NULL') . "\n";
        echo "  - user: " . ($sampleEmployee->user ? 'Sí' : 'No') . "\n";
        if ($sampleEmployee->user) {
            echo "  - user_name: {$sampleEmployee->user->first_name} {$sampleEmployee->user->last_name}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . "\n";
    echo "📍 Línea: " . $e->getLine() . "\n";
}