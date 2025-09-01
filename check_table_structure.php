<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Verificando estructura de la tabla lot_financial_templates:\n";
    echo "=" . str_repeat("=", 60) . "\n";
    
    $columns = DB::select('SHOW COLUMNS FROM lot_financial_templates');
    
    foreach ($columns as $column) {
        echo sprintf("%-20s | %-15s | %-8s | %-8s\n", 
            $column->Field, 
            $column->Type, 
            $column->Null, 
            $column->Key
        );
    }
    
    echo "\n\nVerificando específicamente la columna 'descuento':\n";
    $descuentoColumn = collect($columns)->firstWhere('Field', 'descuento');
    if ($descuentoColumn) {
        echo "Columna descuento encontrada:\n";
        echo "Tipo: " . $descuentoColumn->Type . "\n";
        echo "Permite NULL: " . $descuentoColumn->Null . "\n";
    } else {
        echo "Columna 'descuento' no encontrada.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}