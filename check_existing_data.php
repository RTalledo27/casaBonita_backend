<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;

echo "🔍 VERIFICANDO DATOS EXISTENTES\n";
echo "==============================\n\n";

// Verificar AccountReceivables para contrato 94
echo "📋 Account Receivables para contrato 94:\n";
$ars = AccountReceivable::where('contract_id', 94)->get();
foreach ($ars as $ar) {
    echo "- AR ID: {$ar->ar_id}, Número: {$ar->ar_number}, Estado: {$ar->status}\n";
}
echo "Total: {$ars->count()}\n\n";

// Verificar por ar_number específicos
echo "📋 Account Receivables con números AR-94-001 y AR-94-002:\n";
$testARs = AccountReceivable::whereIn('ar_number', ['AR-94-001', 'AR-94-002'])->get();
foreach ($testARs as $ar) {
    echo "- AR ID: {$ar->ar_id}, Número: {$ar->ar_number}, Contrato: {$ar->contract_id}, Estado: {$ar->status}\n";
}
echo "Total: {$testARs->count()}\n\n";

// Verificar pagos relacionados
if ($ars->count() > 0) {
    $arIds = $ars->pluck('ar_id');
    echo "💰 Customer Payments relacionados:\n";
    $payments = CustomerPayment::whereIn('ar_id', $arIds)->get();
    foreach ($payments as $payment) {
        echo "- Payment ID: {$payment->payment_id}, AR ID: {$payment->ar_id}, Monto: {$payment->amount}\n";
    }
    echo "Total: {$payments->count()}\n\n";
}

echo "✅ Verificación completada\n";