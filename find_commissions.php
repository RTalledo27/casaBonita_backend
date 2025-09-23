<?php

use Modules\HumanResources\Models\Commission;
use Modules\Collections\Models\AccountReceivable;

echo "🔍 BUSCANDO COMISIONES DISPONIBLES\n";
echo "===================================\n\n";

// Buscar todas las comisiones
$allCommissions = Commission::with(['contract'])
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get();

if ($allCommissions->isEmpty()) {
    echo "❌ No se encontraron comisiones en el sistema\n";
    exit(1);
}

echo "📊 Comisiones encontradas: " . $allCommissions->count() . "\n\n";

foreach ($allCommissions as $commission) {
    echo "--- Comisión ID: {$commission->id} ---\n";
    echo "Contract ID: {$commission->contract_id}\n";
    echo "Employee ID: {$commission->employee_id}\n";
    echo "Payment Part: {$commission->payment_part}\n";
    echo "Estado: {$commission->payment_verification_status}\n";
    echo "Elegible: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n";
    echo "Requiere verificación: " . ($commission->requires_client_payment_verification ? 'SÍ' : 'NO') . "\n";
    
    // Verificar si tiene cuentas por cobrar
    $accountsReceivable = AccountReceivable::where('contract_id', $commission->contract_id)
        ->orderBy('due_date', 'asc')
        ->get();
    
    echo "Cuentas por cobrar: " . $accountsReceivable->count() . "\n";
    
    if ($accountsReceivable->count() >= 2) {
        $firstAR = $accountsReceivable->first();
        $secondAR = $accountsReceivable->skip(1)->first();
        
        echo "  - Cuota 1: Estado = {$firstAR->status}, Monto = {$firstAR->original_amount}\n";
        echo "  - Cuota 2: Estado = {$secondAR->status}, Monto = {$secondAR->original_amount}\n";
    }
    
    echo "\n";
}

echo "\n💡 Usa uno de estos contract_id para probar la corrección\n";