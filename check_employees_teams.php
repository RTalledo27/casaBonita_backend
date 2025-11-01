<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICANDO EMPLOYEES Y TEAMS ===\n\n";

// 1. Estructura de employees
echo "1. COLUMNAS EN EMPLOYEES:\n";
$empColumns = DB::select("DESCRIBE employees");
foreach ($empColumns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}

// 2. Ver datos de employees
echo "\n2. DATOS EN EMPLOYEES (primeros 10):\n";
$employees = DB::table('employees')->limit(10)->get();
foreach ($employees as $emp) {
    echo json_encode($emp, JSON_PRETTY_PRINT) . "\n";
}

// 3. Ver si hay relación con users
echo "\n3. ¿HAY RELACIÓN EMPLOYEES <-> USERS?\n";
$relation = DB::table('employees as e')
    ->join('users as u', 'e.user_id', '=', 'u.user_id')
    ->select('e.employee_id', 'e.user_id', 'u.first_name', 'u.last_name', 'e.*')
    ->limit(5)
    ->get();

if ($relation->isEmpty()) {
    echo "❌ NO hay relación directa\n";
} else {
    echo "✅ SÍ hay relación:\n";
    foreach ($relation as $r) {
        echo json_encode($r, JSON_PRETTY_PRINT) . "\n";
    }
}

// 4. Ver teams
echo "\n4. COLUMNAS EN TEAMS:\n";
$teamColumns = DB::select("DESCRIBE teams");
foreach ($teamColumns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}

echo "\n5. DATOS EN TEAMS:\n";
$teams = DB::table('teams')->get();
if ($teams->isEmpty()) {
    echo "❌ Tabla teams está VACÍA\n";
} else {
    foreach ($teams as $team) {
        echo json_encode($team, JSON_PRETTY_PRINT) . "\n";
    }
}
