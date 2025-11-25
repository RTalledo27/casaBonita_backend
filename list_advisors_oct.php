<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== BÚSQUEDA POR EMPLOYEE_ID ===\n\n";

// Listar todos los employee_ids con contratos en octubre
$advisorIds = DB::table('contracts')
    ->selectRaw('advisor_id, COUNT(*) as total')
    ->whereMonth('sign_date', 10)
    ->whereYear('sign_date', 2025)
    ->whereNotNull('advisor_id')
    ->groupBy('advisor_id')
    ->orderByDesc('total')
    ->get();

echo "Por favor, identifica el employee_id de Luis Tavara:\n\n";

foreach ($advisorIds as $row) {
    // Buscar el nombre
    $employee = DB::table('employees')->where('employee_id', $row->advisor_id)->first();
    
    if ($employee) {
        echo sprintf("ID: %3d | %s (%d contratos)\n", $row->advisor_id, $employee->name, $row->total);
    } else {
        echo sprintf("ID: %3d | (nombre no encontrado) (%d contratos)\n", $row->advisor_id, $row->total);
    }
}

echo "\n\nIngresa manualmente el employee_id de Luis Tavara o búscalo arriba.\n";
