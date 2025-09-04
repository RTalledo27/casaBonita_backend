<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Carbon\Carbon;

echo "=== Verificando contrato específico ===\n\n";

$contract = Contract::where('contract_number', 'CON20253216')->first();

if ($contract) {
    echo "🔍 Contrato: {$contract->contract_number}\n";
    echo "   📅 Fecha de venta: " . ($contract->sign_date ? $contract->sign_date->format('Y-m-d') : 'NULL') . "\n";
    echo "   📅 Fecha de contrato: " . ($contract->contract_date ? $contract->contract_date->format('Y-m-d') : 'NULL') . "\n";
    echo "   📅 Creado: " . $contract->created_at->format('Y-m-d H:i:s') . "\n";
    
    // Simular la lógica del backend
    $contractDate = $contract->sign_date ?? $contract->contract_date ?? $contract->created_at;
    if ($contractDate) {
        $calculatedStart = Carbon::parse($contractDate)->addMonth()->startOfMonth();
        echo "   🎯 Fecha calculada (backend): {$calculatedStart->format('Y-m-d')}\n";
        echo "   📊 Mes calculado (backend): {$calculatedStart->format('F Y')}\n";
    }
    
    // Simular la lógica del frontend (fecha fija)
    $today = new DateTime();
    $nextMonth = new DateTime($today->format('Y') . '-' . ($today->format('n') + 1) . '-01');
    echo "   🖥️  Fecha frontend (fija): {$nextMonth->format('Y-m-d')}\n";
    echo "   📊 Mes frontend (fijo): {$nextMonth->format('F Y')}\n";
    
} else {
    echo "❌ Contrato CON20253216 no encontrado\n";
}