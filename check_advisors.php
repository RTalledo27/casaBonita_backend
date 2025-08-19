<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Users\Models\User;

$output = "=== DEBUG ADVISOR LOOKUP ISSUES ===\n\n";

// 1. Mostrar todos los asesores disponibles
$output .= "1. ASESORES INMOBILIARIOS DISPONIBLES EN LA BASE DE DATOS:\n";
$advisors = Employee::with('user')
    ->where('employee_type', 'asesor_inmobiliario')
    ->get();

if ($advisors->count() > 0) {
    foreach ($advisors as $advisor) {
        $userName = $advisor->user ? 
            $advisor->user->first_name . ' ' . $advisor->user->last_name : 
            'SIN USUARIO ASOCIADO';
        $output .= "   - ID: {$advisor->employee_id}, Código: '{$advisor->employee_code}', Nombre: '{$userName}'\n";
        if ($advisor->user) {
            $output .= "     Email: {$advisor->user->email}\n";
        }
    }
} else {
    $output .= "   *** NO HAY ASESORES INMOBILIARIOS EN LA BASE DE DATOS ***\n";
}

$output .= "\n2. BÚSQUEDAS ESPECÍFICAS DEL EXCEL:\n";

// 2. Buscar los asesores específicos del Excel que están fallando
$searchNames = ['PAOLA JUDITH CANDELA NEIRA', 'DANIELA AIRAM MERINO VALIENTE', 'JUAN PEREZ'];

foreach ($searchNames as $searchName) {
    $output .= "\n   Buscando asesor: '{$searchName}'\n";
    
    // Búsqueda exacta por nombre completo
    $exactMatch = Employee::with('user')->whereHas('user', function($q) use ($searchName) {
        $q->whereRaw("CONCAT(first_name, ' ', last_name) = ?", [$searchName]);
    })->first();
    $output .= "   - Búsqueda exacta (CONCAT): " . ($exactMatch ? "ENCONTRADO (ID: {$exactMatch->employee_id})" : "NO ENCONTRADO") . "\n";
    
    // Búsqueda con LIKE por nombre completo
    $likeMatch = Employee::with('user')->whereHas('user', function($q) use ($searchName) {
        $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $searchName . '%']);
    })->first();
    $output .= "   - Búsqueda LIKE (CONCAT): " . ($likeMatch ? "ENCONTRADO (ID: {$likeMatch->employee_id})" : "NO ENCONTRADO") . "\n";
    
    // Búsqueda por partes del nombre
    $words = explode(' ', $searchName);
    if (count($words) >= 2) {
        $firstName = $words[0];
        $lastName = end($words);
        
        $partialMatch = Employee::with('user')->whereHas('user', function($q) use ($firstName, $lastName) {
            $q->where('first_name', 'LIKE', '%' . $firstName . '%')
              ->orWhere('last_name', 'LIKE', '%' . $lastName . '%');
        })->first();
        $output .= "   - Búsqueda parcial ('{$firstName}' o '{$lastName}'): " . ($partialMatch ? "ENCONTRADO (ID: {$partialMatch->employee_id})" : "NO ENCONTRADO") . "\n";
    }
}

$output .= "\n3. TODOS LOS USUARIOS DISPONIBLES (para referencia):\n";
$users = User::orderBy('first_name')->get();
foreach ($users as $user) {
    $fullName = $user->first_name . ' ' . $user->last_name;
    $output .= "   - ID: {$user->user_id}, Nombre: '{$fullName}', Email: {$user->email}\n";
}

// Write to file
file_put_contents('advisor_debug_results.txt', $output);
echo "Advisor debug results written to advisor_debug_results.txt\n";