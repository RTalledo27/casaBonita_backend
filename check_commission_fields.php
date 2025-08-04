<?php

require_once 'vendor/autoload.php';

// Configurar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;

echo "Verificando campos de comisiones...\n";
echo str_repeat("=", 50) . "\n";

$commission = Commission::first();

if ($commission) {
    echo "Commission ID: " . $commission->commission_id . "\n";
    echo "Commission Period: " . ($commission->commission_period ?? 'NULL') . "\n";
    echo "Payment Period: " . ($commission->payment_period ?? 'NULL') . "\n";
    echo "Payment Percentage: " . ($commission->payment_percentage ?? 'NULL') . "\n";
    echo "Status: " . ($commission->status ?? 'NULL') . "\n";
    echo "Parent Commission ID: " . ($commission->parent_commission_id ?? 'NULL') . "\n";
    echo "Payment Part: " . ($commission->payment_part ?? 'NULL') . "\n";
    echo "Payment Status (old): " . ($commission->payment_status ?? 'NULL') . "\n";
    echo "Payment Type (old): " . ($commission->payment_type ?? 'NULL') . "\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Verificando estructura de tabla...\n";
    
    // Verificar si los campos existen en la tabla
    $fillable = $commission->getFillable();
    echo "Campos fillable: " . implode(', ', $fillable) . "\n";
    
    // Verificar atributos del modelo
    $attributes = $commission->getAttributes();
    echo "\nCampos disponibles en el registro:\n";
    foreach ($attributes as $key => $value) {
        echo "- {$key}: " . ($value ?? 'NULL') . "\n";
    }
    
} else {
    echo "No se encontraron comisiones en la base de datos.\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Verificación completada.\n";