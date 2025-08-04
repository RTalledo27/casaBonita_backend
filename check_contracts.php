<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\DB;

echo "Verificando contratos disponibles...\n\n";

// Verificar contratos por mes
$contractsByMonth = DB::table('contracts')
    ->selectRaw('YEAR(sign_date) as year, MONTH(sign_date) as month, COUNT(*) as count')
    ->whereNotNull('sign_date')
    ->groupBy('year', 'month')
    ->orderBy('year', 'desc')
    ->orderBy('month', 'desc')
    ->get();

echo "Contratos por mes:\n";
foreach($contractsByMonth as $row) {
    echo "Año {$row->year}, Mes {$row->month}: {$row->count} contratos\n";
}

echo "\n";

// Verificar contratos de julio 2025 con detalles
echo "Contratos de julio 2025:\n";
$augustContracts = Contract::with('advisor')
    ->whereMonth('sign_date', 7)
    ->whereYear('sign_date', 2025)
    ->take(10)
    ->get();

foreach($augustContracts as $contract) {
    echo "Contract ID: {$contract->contract_id}\n";
    echo "Sign Date: {$contract->sign_date}\n";
    echo "Status: {$contract->status}\n";
    echo "Advisor ID: " . ($contract->advisor_id ?? 'NULL') . "\n";
    echo "Financing Amount: " . ($contract->financing_amount ?? 'NULL') . "\n";
    echo "Advisor: " . ($contract->advisor ? $contract->advisor->user->first_name . ' ' . $contract->advisor->user->last_name : 'No advisor') . "\n";
    echo "---\n";
}

echo "\nTotal contratos julio 2025 (primeros 10): " . $augustContracts->count() . "\n";
echo "Contratos con advisor: " . $augustContracts->whereNotNull('advisor_id')->count() . "\n";
echo "Contratos vigentes: " . $augustContracts->where('status', 'vigente')->count() . "\n";
echo "Contratos con financiamiento > 0: " . $augustContracts->where('financing_amount', '>', 0)->count() . "\n";