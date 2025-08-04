<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Verificando estructura de la tabla commissions...\n\n";

// Obtener columnas de la tabla
$columns = Schema::getColumnListing('commissions');
echo "Columnas disponibles en la tabla commissions:\n";
foreach ($columns as $column) {
    echo "- {$column}\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Verificando si existen los campos period_month y period_year:\n";
echo "period_month existe: " . (in_array('period_month', $columns) ? 'SÍ' : 'NO') . "\n";
echo "period_year existe: " . (in_array('period_year', $columns) ? 'SÍ' : 'NO') . "\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "Campos relacionados con período encontrados:\n";
foreach ($columns as $column) {
    if (strpos($column, 'period') !== false || strpos($column, 'month') !== false || strpos($column, 'year') !== false) {
        echo "- {$column}\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Descripción detallada de la tabla:\n";
$tableInfo = DB::select("DESCRIBE commissions");
foreach ($tableInfo as $column) {
    echo "- {$column->Field}: {$column->Type} (Null: {$column->Null}, Default: {$column->Default})\n";
}