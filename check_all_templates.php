<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\LotFinancialTemplate;

echo "=== VERIFICANDO TODOS LOS TEMPLATES FINANCIEROS ===\n\n";

try {
    // Buscar templates que tengan valores en installments
    $templatesWithInstallments = LotFinancialTemplate::where(function($query) {
        $query->where('installments_24', '>', 0)
              ->orWhere('installments_40', '