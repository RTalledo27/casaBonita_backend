<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Estructura de la tabla reservations:\n";
echo "=====================================\n";

$columns = DB::select('SHOW COLUMNS FROM reservations');

foreach($columns as $col) {
    echo sprintf("%-20s | %-15s | Null: %-3s | Key: %-3s | Default: %s\n", 
        $col->Field, 
        $col->Type, 
        $col->Null, 
        $col->Key, 
        $col->Default ?? 'NULL'
    );
}

echo "\n";
echo "Verificando si advisor_id existe y es nullable:\n";
echo "=============================================\n";

$advisorColumn = collect($columns)->firstWhere('Field', 'advisor_id');

if ($advisorColumn) {
    echo "✓ La columna advisor_id existe\n";
    echo "  Tipo: {$advisorColumn->Type}\n";
    echo "  Nullable: {$advisorColumn->Null}\n";
    echo "  Key: {$advisorColumn->Key}\n";
    echo "  Default: " . ($advisorColumn->Default ?? 'NULL') . "\n";
} else {
    echo "✗ La columna advisor_id NO existe\n";
}