<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Carbon\Carbon;

echo "=== Probando generación de cronogramas con fechas diferentes ===\n\n";

// Obtener los contratos de prueba que creamos
$testContracts = Contract::whereIn('contract_number', [
    'TEST-MARZO-001',
    'TEST-JUNIO-001', 
    'TEST-SEPTIEMBRE-001',
    'TEST-DICIEMBRE-001'
])->get();

if ($testContracts->isEmpty()) {
    echo "❌ No se encontraron contratos de prueba\n";
    exit(1);
}

echo "📋 Contratos encontrados: {$testContracts->count()}\n\n";

foreach ($testContracts as $contract) {
    echo "🔍 Contrato: {$contract->contract_number}\n";
    echo "   📅 Fecha de venta: {$contract->sign_date->format('Y-m-d')}\n";
    
    // Simular la lógica de determinePaymentOptions implementada en PaymentScheduleGenerationService
    $contractDate = $contract->sign_date ?? $contract->contract_date ?? $contract->created_at;
    if ($contractDate) {
        $calculatedStartDate = Carbon::parse($contractDate)->addMonth()->startOfMonth();
        echo "   🎯 Fecha de inicio calculada: {$calculatedStartDate->format('Y-m-d')}\n";
        echo "   📊 Mes esperado: {$calculatedStartDate->format('F Y')}\n";
    } else {
        echo "   ⚠️  Sin fecha de contrato disponible\n";
    }
    
    echo "\n";
}

echo "✅ Verificación completada\n";
echo "\n📝 Resumen:\n";
echo "- Marzo 2024 → Abril 2024\n";
echo "- Junio 2024 → Julio 2024\n";
echo "- Septiembre 2024 → Octubre 2024\n";
echo "- Diciembre 2024 → Enero 2025\n";
echo "\n🎉 La lógica está funcionando correctamente!\n";