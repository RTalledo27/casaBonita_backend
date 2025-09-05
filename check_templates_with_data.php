<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== VERIFICANDO TEMPLATES CON DATOS FINANCIEROS ===\n\n";

// Buscar templates con interest_rate
$templatesWithInterest = LotFinancialTemplate::whereNotNull('interest_rate')
    ->where('interest_rate', '!=', '')
    ->where('interest_rate', '>', 0)
    ->get();

echo "Templates con interest_rate: {$templatesWithInterest->count()}\n";

// Buscar templates con installments
$templatesWithInstallments = LotFinancialTemplate::where(function($query) {
    $query->whereNotNull('installments_24')
          ->orWhereNotNull('installments_40')
          ->orWhereNotNull('installments_44')
          ->orWhereNotNull('installments_55');
})->get();

echo "Templates con installments: {$templatesWithInstallments->count()}\n\n";

// Mostrar todos los templates
$allTemplates = LotFinancialTemplate::take(10)->get();
echo "=== PRIMEROS 10 TEMPLATES ===\n";
foreach ($allTemplates as $template) {
    echo "ID: {$template->id}, Lot: {$template->lot_id}\n";
    echo "  Precio Lista: {$template->precio_lista}\n";
    echo "  Precio Venta: {$template->precio_venta}\n";
    echo "  Cuota Inicial: {$template->cuota_inicial}\n";
    echo "  Interest Rate: {$template->interest_rate}\n";
    echo "  Installments 24: {$template->installments_24}\n";
    echo "  Installments 40: {$template->installments_40}\n";
    echo "  Installments 44: {$template->installments_44}\n";
    echo "  Installments 55: {$template->installments_55}\n";
    echo "  ---\n";
}

echo "\n=== TOTAL DE TEMPLATES: " . LotFinancialTemplate::count() . " ===\n";