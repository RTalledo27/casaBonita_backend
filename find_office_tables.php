<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== BUSCANDO TABLAS DE OFICINAS/DEPARTAMENTOS ===\n\n";

$tables = DB::select('SHOW TABLES');
$dbName = DB::getDatabaseName();

echo "Todas las tablas:\n";
foreach ($tables as $table) {
    $tableName = $table->{"Tables_in_$dbName"};
    echo "  - $tableName\n";
}

echo "\n=== VERIFICANDO COLUMNAS EN USERS ===\n";
$columns = DB::select("DESCRIBE users");
echo "\nColumnas en users:\n";
foreach ($columns as $col) {
    echo "  {$col->Field} ({$col->Type}) - Default: " . ($col->Default ?? 'NULL') . "\n";
}

echo "\n=== ¿HAY DATOS EN department? ===\n";
$depts = DB::table('users')
    ->select('department', DB::raw('COUNT(*) as count'))
    ->whereNotNull('department')
    ->where('department', '!=', '')
    ->groupBy('department')
    ->get();

if ($depts->isEmpty()) {
    echo "❌ La columna 'department' está VACÍA o es NULL en todos los usuarios\n";
} else {
    echo "✅ Departamentos encontrados:\n";
    foreach ($depts as $dept) {
        echo "  - {$dept->department}: {$dept->count} usuarios\n";
    }
}

echo "\n=== VERIFICANDO RESERVATIONS (puede tener oficina) ===\n";
$resColumns = DB::select("DESCRIBE reservations");
echo "\nColumnas en reservations:\n";
foreach ($resColumns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}
