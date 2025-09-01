<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Employee;

echo "=== DEBUG BÚSQUEDA DE ASESORES ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Simular los datos que llegan del Excel
$testData = [
    ['asesor_nombre' => 'PAOLA JUDITH CANDELA NEIRA', 'asesor_codigo' => '', 'asesor_email' => ''],
    ['asesor_nombre' => 'DANIELA AIRAM MERINO VALIENTE', 'asesor_codigo' => '', 'asesor_email' => ''],
    ['asesor_nombre' => 'PAOLA JUDITH', 'asesor_codigo' => '', 'asesor_email' => ''],
    ['asesor_nombre' => 'DANIELA MERINO', 'asesor_codigo' => '', 'asesor_email' => ''],
    ['asesor_nombre' => 'PAOLA', 'asesor_codigo' => '', 'asesor_email' => ''],
    ['asesor_nombre' => 'DANIELA', 'asesor_codigo' => '', 'asesor_email' => '']
];

foreach ($testData as $index => $data) {
    echo "\n🔍 PRUEBA " . ($index + 1) . ": Buscando '{$data['asesor_nombre']}'\n";
    
    if (empty($data['asesor_nombre']) && empty($data['asesor_codigo']) && empty($data['asesor_email'])) {
        echo "  ❌ No se proporcionaron datos de asesor\n";
        continue;
    }
    
    $query = Employee::with('user');
    $found = false;
    
    // Buscar por código primero
    if (!empty($data['asesor_codigo'])) {
        $advisor = $query->where('employee_code', $data['asesor_codigo'])->first();
        if ($advisor) {
            echo "  ✅ Encontrado por CÓDIGO: {$advisor->employee_code} - {$advisor->user->first_name} {$advisor->user->last_name}\n";
            $found = true;
            continue;
        }
    }
    
    // Buscar por email
    if (!empty($data['asesor_email'])) {
        $advisor = $query->whereHas('user', function($q) use ($data) {
            $q->where('email', $data['asesor_email']);
        })->first();
        if ($advisor) {
            echo "  ✅ Encontrado por EMAIL: {$data['asesor_email']} - {$advisor->user->first_name} {$advisor->user->last_name}\n";
            $found = true;
            continue;
        }
    }
    
    // Buscar por nombre
    if (!empty($data['asesor_nombre'])) {
        echo "  🔎 Buscando por nombre: '{$data['asesor_nombre']}'\n";
        
        // Mostrar la consulta SQL que se ejecutará
        $searchName = $data['asesor_nombre'];
        echo "  📝 Consultas SQL que se ejecutarán:\n";
        echo "    - CONCAT(first_name, ' ', last_name) LIKE '%{$searchName}%'\n";
        echo "    - first_name LIKE '%{$searchName}%'\n";
        echo "    - last_name LIKE '%{$searchName}%'\n";
        
        $advisor = $query->whereHas('user', function($q) use ($data) {
            $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $data['asesor_nombre'] . '%'])
              ->orWhere('first_name', 'LIKE', '%' . $data['asesor_nombre'] . '%')
              ->orWhere('last_name', 'LIKE', '%' . $data['asesor_nombre'] . '%');
        })->first();
        
        if ($advisor) {
            echo "  ✅ Encontrado por NOMBRE: {$advisor->user->first_name} {$advisor->user->last_name} (ID: {$advisor->employee_id})\n";
            $found = true;
        } else {
            echo "  ❌ NO encontrado por nombre\n";
            
            // Mostrar asesores similares
            echo "  🔍 Buscando asesores similares...\n";
            $similarAdvisors = DB::table('employees')
                ->join('users', 'employees.user_id', '=', 'users.user_id')
                ->where('employees.employee_type', 'asesor_inmobiliario')
                ->where('employees.employment_status', 'activo')
                ->select('employees.employee_id', 'employees.employee_code', 'users.first_name', 'users.last_name')
                ->get();
            
            foreach ($similarAdvisors as $similar) {
                $fullName = $similar->first_name . ' ' . $similar->last_name;
                $similarity = similar_text(strtoupper($data['asesor_nombre']), strtoupper($fullName), $percent);
                if ($percent > 50) {
                    echo "    - {$fullName} (Similitud: {$percent}%)\n";
                }
            }
        }
    }
    
    if (!$found) {
        echo "  ❌ ASESOR NO ENCONTRADO - Se usaría fallback\n";
    }
}

echo "\n=== FIN DEL DEBUG ===\n";