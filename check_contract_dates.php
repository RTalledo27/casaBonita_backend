<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Carbon\Carbon;

echo "=== Verificando fechas de contratos ===\n\n";

$contracts = Contract::orderBy('sign_date', 'desc')->take(10)->get();

foreach($contracts as $contract) {
    echo "Contract: {$contract->contract_number}\n";
    echo "  - Sign Date: " . ($contract->sign_date ? $contract->sign_date->format('Y-m-d') : 'NULL') . "\n";
    echo "  - Contract Date: " . ($contract->contract_date ? $contract->contract_date->format('Y-m-d') : 'NULL') . "\n";
    echo "  - Created At: " . ($contract->created_at ? $contract->created_at->format('Y-m-d H:i:s') : 'NULL') . "\n";
    
    // Simular la lógica de fecha de inicio
    $contractDate = $contract->sign_date ?? $contract->contract_date ?? $contract->created_at;
    if ($contractDate) {
        $defaultStartDate = Carbon::parse($contractDate)->addMonth()->startOfMonth()->format('Y-m-d');
        echo "  - Calculated Start Date: {$defaultStartDate}\n";
    }
    echo "\n";
}