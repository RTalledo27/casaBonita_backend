<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;

echo "📋 VERIFICANDO CONTRATOS DISPONIBLES\n";
echo "===================================\n\n";

// Buscar contratos existentes
$contracts = Contract::take(10)->get(['contract_id', 'contract_number']);

echo "Contratos encontrados: {$contracts->count()}\n\n";

if ($contracts->count() > 0) {
    foreach ($contracts as $contract) {
        echo "Contract ID: {$contract->contract_id}, Contract Number: {$contract->contract_number}\n";
    }
    
    // Usar el primer contrato para crear una comisión de prueba
    $firstContract = $contracts->first();
    echo "\n🔍 Verificando comisiones para contrato ID: {$firstContract->contract_id}\n";
    
    $commissions = Commission::where('contract_id', $firstContract->contract_id)->get();
    echo "Comisiones existentes: {$commissions->count()}\n\n";
    
    if ($commissions->count() == 0) {
        echo "Creando comisión de prueba para contrato ID: {$firstContract->id}...\n";
        
        try {
            $commission = Commission::create([
                'employee_id' => 1,
                'contract_id' => $firstContract->contract_id,
                'commission_type' => 'venta',
                'sale_amount' => 100000,
                'commission_percentage' => 3.0,
                'commission_amount' => 3000,
                'payment_status' => 'pendiente',
                'status' => 'generated',
                'payment_part' => 1,
                'requires_client_payment_verification' => true,
                'payment_verification_status' => 'pending_verification',
                'is_eligible_for_payment' => false,
                'period_month' => date('n'),
                'period_year' => date('Y')
            ]);
            
            echo "✅ Comisión creada: ID {$commission->commission_id}\n";
            echo "Contract ID usado: {$firstContract->contract_id}\n";
            echo "Contract Number: {$firstContract->contract_number}\n";
        } catch (Exception $e) {
            echo "❌ Error creando comisión: {$e->getMessage()}\n";
        }
    } else {
        echo "Comisiones existentes para este contrato:\n";
        foreach ($commissions as $commission) {
            echo "- ID: {$commission->commission_id}, Parte: {$commission->payment_part}, Estado: {$commission->status}\n";
        }
    }
} else {
    echo "❌ No se encontraron contratos en la base de datos\n";
}