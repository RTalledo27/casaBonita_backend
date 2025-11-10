<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\CRM\Models\Client;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;

echo "=== VERIFICACIÓN DE IMPORTACIÓN ===\n\n";

echo "📊 Totales:\n";
echo "  • Clientes: " . Client::count() . "\n";
echo "  • Contratos: " . Contract::count() . "\n";
echo "  • Cuotas: " . PaymentSchedule::count() . "\n\n";

echo "📋 Últimos 2 contratos:\n";
$contracts = Contract::with(['client', 'lot.manzana', 'advisor.user'])
    ->latest('contract_id')
    ->take(2)
    ->get();

foreach ($contracts as $contract) {
    $clientName = $contract->client ? $contract->client->first_name . ' ' . $contract->client->last_name : 'N/A';
    $lotName = $contract->lot && $contract->lot->manzana ? $contract->lot->manzana->name . '-' . $contract->lot->num_lot : 'N/A';
    $advisorName = $contract->advisor && $contract->advisor->user ? $contract->advisor->user->first_name . ' ' . $contract->advisor->user->last_name : 'Sin asesor';
    
    echo "\n  Contrato: {$contract->contract_number}\n";
    echo "  • Cliente: {$clientName}\n";
    echo "  • Lote: {$lotName}\n";
    echo "  • Asesor: {$advisorName}\n";
    echo "  • Total: {$contract->currency} {$contract->total_price}\n";
    echo "  • Inicial: {$contract->currency} {$contract->down_payment}\n";
    echo "  • Financiamiento: {$contract->currency} {$contract->financing_amount}\n";
    echo "  • Plazo: {$contract->term_months} meses\n";
    echo "  • Cuota mensual: {$contract->currency} {$contract->monthly_payment}\n";
    
    $cuotas = PaymentSchedule::where('contract_id', $contract->contract_id)->count();
    echo "  • Cuotas generadas: {$cuotas}\n";
}
