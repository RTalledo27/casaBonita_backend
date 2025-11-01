<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n📋 Estructura de la tabla 'contracts'\n";
echo "=====================================\n\n";

try {
    $columns = DB::select('DESCRIBE contracts');
    
    foreach ($columns as $column) {
        echo "• {$column->Field}\n";
        echo "  Tipo: {$column->Type}\n";
        echo "  Null: {$column->Null}\n";
        echo "  Default: " . ($column->Default ?? 'NULL') . "\n\n";
    }
    
    echo "=====================================\n";
    echo "✅ Total de columnas: " . count($columns) . "\n\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}
