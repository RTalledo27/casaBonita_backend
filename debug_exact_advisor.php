<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Employee;

echo "=== DEBUG DATOS EXACTOS DEL LOG ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Datos exactos del log de importación
$logData = [
    'asesor_nombre' => 'PAOLA JUDITH CANDELA NEIRA',
    'asesor_codigo' => 'ASE001',
    'asesor_email' => 'paola@email.com'
];

echo "🔍 DATOS DEL LOG DE IMPORTACIÓN:\n";
echo "  - asesor_nombre: '{$logData['asesor_nombre']}'\n";
echo "  - asesor_codigo: '{$logData['asesor_codigo']}'\n";
echo "  - asesor_email: '{$logData['asesor_email']}'\n\n";

// Simular exactamente el método findAdvisorSimplified
echo "🔎 SIMULANDO findAdvisorSimplified PASO A PASO:\n\n";

// Paso 1: Verificar si hay datos
if (empty($logData['asesor_nombre']) && empty($logData['asesor_codigo']) && empty($logData['asesor_email'])) {
    echo "❌ No se proporcionaron datos de asesor\n";
    exit;
}

echo "✅ Paso 1: Datos de asesor proporcionados\n\n";

// Paso 2: Buscar por código
echo "🔍 Paso 2: Buscando por CÓDIGO '{$logData['asesor_codigo']}'\n";
if (!empty($logData['asesor_codigo'])) {
    $advisorByCode = Employee::where('employee_code', $logData['asesor_codigo'])->first();
    if ($advisorByCode) {
        echo "  ✅ ENCONTRADO por código: {$advisorByCode->employee_code}\n";
        echo "  📋 Datos: ID={$advisorByCode->employee_id}, Código={$advisorByCode->employee_code}\n";
        exit; // Se encontró, no continúa
    } else {
        echo "  ❌ NO encontrado por código '{$logData['asesor_codigo']}'\n";
        
        // Mostrar todos los códigos existentes
        echo "  📋 Códigos existentes en la base de datos:\n";
        $allCodes = Employee::whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->pluck('employee_code', 'employee_id');
        foreach ($allCodes as $id => $code) {
            echo "    - ID: {$id}, Código: {$code}\n";
        }
    }
}
echo "\n";

// Paso 3: Buscar por email
echo "🔍 Paso 3: Buscando por EMAIL '{$logData['asesor_email']}'\n";
if (!empty($logData['asesor_email'])) {
    $advisorByEmail = Employee::with('user')
        ->whereHas('user', function($q) use ($logData) {
            $q->where('email', $logData['asesor_email']);
        })->first();
    if ($advisorByEmail) {
        echo "  ✅ ENCONTRADO por email: {$logData['asesor_email']}\n";
        echo "  📋 Datos: ID={$advisorByEmail->employee_id}, Email={$advisorByEmail->user->email}\n";
        exit; // Se encontró, no continúa
    } else {
        echo "  ❌ NO encontrado por email '{$logData['asesor_email']}'\n";
        
        // Mostrar todos los emails existentes
        echo "  📋 Emails existentes de asesores:\n";
        $allEmails = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->where('employees.employee_type', 'asesor_inmobiliario')
            ->where('employees.employment_status', 'activo')
            ->pluck('users.email', 'employees.employee_id');
        foreach ($allEmails as $id => $email) {
            echo "    - ID: {$id}, Email: {$email}\n";
        }
    }
}
echo "\n";

// Paso 4: Buscar por nombre
echo "🔍 Paso 4: Buscando por NOMBRE '{$logData['asesor_nombre']}'\n";
if (!empty($logData['asesor_nombre'])) {
    $advisorByName = Employee::with('user')
        ->whereHas('user', function($q) use ($logData) {
            $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $logData['asesor_nombre'] . '%'])
              ->orWhere('first_name', 'LIKE', '%' . $logData['asesor_nombre'] . '%')
              ->orWhere('last_name', 'LIKE', '%' . $logData['asesor_nombre'] . '%');
        })->first();
    
    if ($advisorByName) {
        echo "  ✅ ENCONTRADO por nombre: {$advisorByName->user->first_name} {$advisorByName->user->last_name}\n";
        echo "  📋 Datos: ID={$advisorByName->employee_id}, Nombre completo={$advisorByName->user->first_name} {$advisorByName->user->last_name}\n";
        echo "  🎯 ESTE DEBERÍA SER EL RESULTADO CORRECTO\n";
    } else {
        echo "  ❌ NO encontrado por nombre '{$logData['asesor_nombre']}'\n";
    }
}

echo "\n🔍 Paso 5: Fallback al asesor por defecto\n";
$defaultAdvisor = Employee::where('employee_id', 1)->first();
if ($defaultAdvisor) {
    echo "  ✅ Usando asesor por defecto: ID={$defaultAdvisor->employee_id}\n";
} else {
    echo "  ❌ No se encontró asesor por defecto con ID=1\n";
}

echo "\n=== CONCLUSIÓN ===\n";
echo "El problema está en que findAdvisorSimplified busca PRIMERO por código,\n";
echo "y como 'ASE001' no existe, debería continuar con email y luego nombre.\n";
echo "Si el método está funcionando correctamente, debería encontrar por nombre.\n";
echo "Si no lo encuentra, hay un bug en la lógica del método.\n";