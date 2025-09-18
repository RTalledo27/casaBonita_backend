<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use App\Models\CommissionPaymentVerification;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG: Verificación de Comisiones para CON20257868 ===\n\n";

// 1. Buscar el contrato por contract_number
echo "1. Buscando contrato CON20257868...\n";
$contract = Contract::where('contract_number', 'CON20257868')->first();

if (!$contract) {
    echo "❌ ERROR: No se encontró el contrato CON20257868\n";
    exit(1);
}

echo "✅ Contrato encontrado:\n";
echo "   - contract_id: {$contract->contract_id}\n";
echo "   - contract_number: {$contract->contract_number}\n";
echo "   - total_price: {$contract->total_price}\n";
echo "   - status: {$contract->status}\n\n";

// 2. Buscar comisiones para este contract_id
echo "2. Buscando comisiones para contract_id: {$contract->contract_id}...\n";
$commissions = Commission::where('contract_id', $contract->contract_id)->get();

if ($commissions->isEmpty()) {
    echo "❌ ERROR: No se encontraron comisiones para este contrato\n";
    exit(1);
}

echo "✅ Comisiones encontradas: {$commissions->count()}\n";
foreach ($commissions as $commission) {
    echo "   - Commission ID: {$commission->commission_id}\n";
    echo "     * Employee ID: {$commission->employee_id}\n";
    echo "     * Payment Part: {$commission->payment_part}\n";
    echo "     * Amount: {$commission->commission_amount}\n";
    echo "     * Requires Verification: " . ($commission->requires_client_payment_verification ? 'Sí' : 'No') . "\n";
    echo "     * Verification Status: {$commission->payment_verification_status}\n";
    echo "     * Is Payable: " . ($commission->is_payable ? 'Sí' : 'No') . "\n\n";
}

// 3. Buscar la comisión específica con payment_part = 1
echo "3. Buscando comisión con payment_part = 1...\n";
$targetCommission = $commissions->where('payment_part', 1)->first();

if (!$targetCommission) {
    echo "❌ ERROR: No se encontró comisión con payment_part = 1\n";
    // Mostrar todas las comisiones disponibles
    echo "Comisiones disponibles:\n";
    foreach ($commissions as $comm) {
        echo "   - Payment Part: {$comm->payment_part}\n";
    }
    exit(1);
}

echo "✅ Comisión target encontrada:\n";
echo "   - Commission ID: {$targetCommission->commission_id}\n";
echo "   - Payment Part: {$targetCommission->payment_part}\n";
echo "   - Requires Verification: " . ($targetCommission->requires_client_payment_verification ? 'Sí' : 'No') . "\n\n";

// 4. Verificar AccountsReceivable para este contract_id
echo "4. Buscando AccountsReceivable para contract_id: {$contract->contract_id}...\n";
$accountsReceivable = AccountReceivable::where('contract_id', $contract->contract_id)
    ->orderBy('due_date', 'asc')
    ->get();

if ($accountsReceivable->isEmpty()) {
    echo "❌ ERROR: No se encontraron AccountsReceivable para este contrato\n";
    
    // Verificar si existen con contract_number en lugar de contract_id
    echo "\n🔍 Verificando si existen con contract_number...\n";
    $arByNumber = AccountReceivable::where('contract_number', $contract->contract_number)->get();
    
    if ($arByNumber->isNotEmpty()) {
        echo "⚠️  PROBLEMA ENCONTRADO: AccountsReceivable usa contract_number en lugar de contract_id\n";
        echo "   Registros encontrados con contract_number: {$arByNumber->count()}\n";
        foreach ($arByNumber as $ar) {
            echo "   - AR ID: {$ar->ar_id}, Contract Number: {$ar->contract_number}, Amount: {$ar->original_amount}\n";
        }
    } else {
        echo "❌ Tampoco se encontraron con contract_number\n";
    }
    
    exit(1);
}

echo "✅ AccountsReceivable encontrados: {$accountsReceivable->count()}\n";
foreach ($accountsReceivable as $index => $ar) {
    $arNumber = $index + 1;
    echo "   - AR #{$arNumber}: ID {$ar->ar_id}\n";
    echo "     * Amount: {$ar->original_amount}\n";
    echo "     * Due Date: {$ar->due_date}\n";
    echo "     * Status: {$ar->status}\n\n";
}

// 5. Verificar CustomerPayments para cada AccountReceivable
echo "5. Verificando CustomerPayments...\n";
$firstAR = $accountsReceivable->first();
$secondAR = $accountsReceivable->skip(1)->first();

if ($firstAR) {
    echo "\n📋 Primera cuota (AR ID: {$firstAR->ar_id}):\n";
    $firstPayments = CustomerPayment::where('ar_id', $firstAR->ar_id)
        ->where('payment_date', '<=', now())
        ->get();
    
    if ($firstPayments->isEmpty()) {
        echo "   ❌ No hay pagos registrados\n";
    } else {
        $totalPaid = $firstPayments->sum('amount');
        echo "   ✅ Pagos encontrados: {$firstPayments->count()}\n";
        echo "   💰 Total pagado: {$totalPaid}\n";
        echo "   💰 Monto requerido: {$firstAR->original_amount}\n";
        echo "   📊 Estado: " . ($totalPaid >= $firstAR->original_amount ? 'PAGADO' : 'PENDIENTE') . "\n";
        
        foreach ($firstPayments as $payment) {
            echo "     - Pago: {$payment->amount} en {$payment->payment_date}\n";
        }
    }
}

if ($secondAR) {
    echo "\n📋 Segunda cuota (AR ID: {$secondAR->ar_id}):\n";
    $secondPayments = CustomerPayment::where('ar_id', $secondAR->ar_id)
        ->where('payment_date', '<=', now())
        ->get();
    
    if ($secondPayments->isEmpty()) {
        echo "   ❌ No hay pagos registrados\n";
    } else {
        $totalPaid = $secondPayments->sum('amount');
        echo "   ✅ Pagos encontrados: {$secondPayments->count()}\n";
        echo "   💰 Total pagado: {$totalPaid}\n";
        echo "   💰 Monto requerido: {$secondAR->original_amount}\n";
        echo "   📊 Estado: " . ($totalPaid >= $secondAR->original_amount ? 'PAGADO' : 'PENDIENTE') . "\n";
        
        foreach ($secondPayments as $payment) {
            echo "     - Pago: {$payment->amount} en {$payment->payment_date}\n";
        }
    }
}

// 6. Verificar registros de verificación existentes
echo "\n6. Verificando registros de verificación existentes...\n";
$verifications = CommissionPaymentVerification::where('commission_id', $targetCommission->commission_id)->get();

if ($verifications->isEmpty()) {
    echo "❌ No hay verificaciones registradas\n";
} else {
    echo "✅ Verificaciones encontradas: {$verifications->count()}\n";
    foreach ($verifications as $verification) {
        echo "   - Installment: {$verification->payment_installment}\n";
        echo "     * Status: {$verification->verification_status}\n";
        echo "     * Verified At: {$verification->verified_at}\n";
        echo "     * Amount: {$verification->payment_amount}\n\n";
    }
}

// 7. Simular el proceso de verificación
echo "\n7. 🔄 Simulando proceso de verificación...\n";

if ($targetCommission->requires_client_payment_verification) {
    echo "✅ La comisión requiere verificación de pagos\n";
    
    if ($targetCommission->payment_part == 1 && $firstAR) {
        echo "📋 Verificando primera cuota para payment_part = 1...\n";
        
        $firstPayments = CustomerPayment::where('ar_id', $firstAR->ar_id)
            ->where('payment_date', '<=', now())
            ->get();
        
        $totalPaid = $firstPayments->sum('amount');
        $tolerance = 0.01;
        $isPaid = ($totalPaid >= ($firstAR->original_amount - $tolerance));
        
        echo "   💰 Monto requerido: {$firstAR->original_amount}\n";
        echo "   💰 Total pagado: {$totalPaid}\n";
        echo "   📊 ¿Está pagado?: " . ($isPaid ? 'SÍ' : 'NO') . "\n";
        
        if ($isPaid && $firstPayments->isNotEmpty()) {
            echo "   ✅ La primera cuota está pagada - La comisión debería ser elegible\n";
        } else {
            echo "   ❌ La primera cuota NO está pagada - La comisión NO es elegible\n";
        }
    }
} else {
    echo "ℹ️  La comisión NO requiere verificación de pagos\n";
}

// 8. Resumen final
echo "\n=== RESUMEN FINAL ===\n";
echo "Contrato: {$contract->contract_number} (ID: {$contract->contract_id})\n";
echo "Comisión: {$targetCommission->commission_id} (Part: {$targetCommission->payment_part})\n";
echo "AccountsReceivable: {$accountsReceivable->count()} registros\n";
echo "Estado actual: {$targetCommission->payment_verification_status}\n";
echo "Es pagable: " . ($targetCommission->is_payable ? 'SÍ' : 'NO') . "\n";

echo "\n✅ Debug completado.\n";