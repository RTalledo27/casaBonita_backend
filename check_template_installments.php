<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== VERIFICANDO INSTALLMENTS DEL TEMPLATE FINANCIERO ===\n\n";

try {
    $template = LotFinancialTemplate::where('lot_id', 1)->first();
    
    if (!$template) {
        echo "No se encontró template financiero para el lote 1\n";
        exit(1);
    }
    
    echo "Template ID: {$template->id}\n";
    echo "Lote ID: {$template->lot_id}\n";
    echo "precio_venta: {$template->precio_venta}\n";
    echo "cuota_inicial: {$template->cuota_inicial}\n";
    echo "installments_24: {$template->installments_24}\n";
    echo "installments_40: {$template->installments_40}\n";
    echo "installments_44: {$template->installments_44}\n";
    echo "installments_55: {$template->installments_55}\n";
    
    // Verificar si algún campo de installments tiene valor
    $hasInstallments = false;
    if ($template->installments_24 > 0) {
        echo "\n✓ installments_24 tiene valor: {$template->installments_24}\n";
        $hasInstallments = true;
    }
    if ($template->installments_40 > 0) {
        echo "\n✓ installments_40 tiene valor: {$template->installments_40}\n";
        $hasInstallments = true;
    }
    if ($template->installments_44 > 0) {
        echo "\n✓ installments_44 tiene valor: {$template->installments_44}\n";
        $hasInstallments = true;
    }
    if ($template->installments_55 > 0) {
        echo "\n✓ installments_55 tiene valor: {$template->installments_55}\n";
        $hasInstallments = true;
    }
    
    if (!$hasInstallments) {
        echo "\n❌ PROBLEMA: Ningún campo de installments tiene valor > 0\n";
        echo "Esto significa que el template no tiene cuotas mensuales definidas.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Fin de la verificación ===\n";