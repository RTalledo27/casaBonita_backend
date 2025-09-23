<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== DEBUG: Verificación de Comisiones - Contrato CON20257868 ===\n\n";

// 1. Buscar el contrato por número
$contractNumber = 'CON20257868';
$contract = DB::table('contracts')->where('contract_number', $contractNumber)->first();

if (!$contract) {
    echo "❌ ERROR: No se encontró el contrato $contractNumber\n";
    exit(1);
}

echo "✅ Contrato encontrado:\n";
echo "   - ID: {$contract->contract_id}\n";
echo "   - Número: {$contract->contract_number}\n";
echo "   - Estado: {$contract->status}\n\n";

// 2. Buscar comisiones para este contrato
$commissions = Commission::where('contract_id', $contract->contract_id)->get();

echo "📊 Comisiones encontradas: {$commissions->count()}\n";
foreach ($commissions as $commission) {
    echo "   - ID: {$commission->id}, Payment Part: {$commission->payment_part}, Status: {$commission->payment_verification_status}, Eligible: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
}
echo "\n";

// 3. Verificar cuentas por cobrar (AccountReceivable)
echo "💰 Verificando cuentas por cobrar...\n";
$accountsReceivable = AccountReceivable::where('contract_id', $contract->contract_id)
    ->orderBy('due_date', 'asc')
    ->get();

echo "   Total cuentas por cobrar: {$accountsReceivable->count()}\n";
foreach ($accountsReceivable->take(5) as $index => $ar) {
    $indexDisplay = $index + 1;
    echo "   [$indexDisplay] AR ID: {$ar->ar_id}, Monto: {$ar->original_amount}, Vencimiento: {$ar->due_date}, Estado: {$ar->status}\n";
}
echo "\n";

// 4. Verificar pagos del cliente (CustomerPayment)
echo "💳 Verificando pagos del cliente...\n";
foreach ($accountsReceivable->take(2) as $index => $ar) {
    $payments = CustomerPayment::where('ar_id', $ar->ar_id)->get();
    $totalPaid = $payments->sum('amount');
    $cuotaNumber = $index + 1;
    
    echo "   Cuota #$cuotaNumber (AR ID: {$ar->ar_id}):\n";
    echo "     - Monto original: {$ar->original_amount}\n";
    echo "     - Pagos encontrados: {$payments->count()}\n";
    echo "     - Total pagado: {$totalPaid}\n";
    echo "     - ¿Está pagada?: " . ($totalPaid >= ($ar->original_amount - 0.01) ? 'SÍ' : 'NO') . "\n";
    
    if ($payments->count() > 0) {
        foreach ($payments as $payment) {
            echo "       * Pago ID: {$payment->id}, Monto: {$payment->amount}, Fecha: {$payment->payment_date}\n";
        }
    }
    echo "\n";
}

// 5. Probar el servicio de verificación
echo "🔍 Probando el servicio de verificación...\n";
$verificationService = new CommissionPaymentVerificationService();

foreach ($commissions as $commission) {
    echo "\n--- Verificando comisión ID: {$commission->id} (Payment Part: {$commission->payment_part}) ---\n";
    
    try {
        $results = $verificationService->verifyClientPayments($commission);
        
        echo "Resultados de verificación:\n";
        echo "   - Primera cuota: " . ($results['first_payment'] ? 'VERIFICADA' : 'PENDIENTE') . "\n";
        echo "   - Segunda cuota: " . ($results['second_payment'] ? 'VERIFICADA' : 'PENDIENTE') . "\n";
        echo "   - Payment Part: {$results['payment_part']}\n";
        echo "   - Mensaje: {$results['message']}\n";
        
        // Recargar la comisión para ver el estado actualizado
        $commission->refresh();
        echo "   - Estado actualizado: {$commission->payment_verification_status}\n";
        echo "   - Elegible para pago: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n";
        
    } catch (Exception $e) {
        echo "❌ ERROR en verificación: {$e->getMessage()}\n";
        echo "Stack trace: {$e->getTraceAsString()}\n";
    }
}

echo "\n=== FIN DEL DEBUG ===\n";