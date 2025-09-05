<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== ESTRUCTURA DE LA TABLA lot_financial_templates ===\n\n";

try {
    // Obtener las columnas de la tabla
    $columns = Schema::getColumnListing('lot_financial_templates');
    
    echo "Columnas encontradas:\n";
    foreach ($columns as $column) {
        echo "- {$column}\n";
    }
    
    echo "\n=== DESCRIPCIÓN DETALLADA ===\n";
    
    // Obtener información detallada de las columnas
    $tableInfo = DB::select("DESCRIBE lot_financial_templates");
    
    foreach ($tableInfo as $info) {
        echo "Columna: {$info->Field}\n";
        echo "  Tipo: {$info->Type}\n";
        echo "  Null: {$info->Null}\n";
        echo "  Default: {$info->Default}\n";
        echo "  ---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";