<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG: Sincronización de Pagos - Contrato CON20257868 ===\n\n";

// 1. Buscar el contrato
$contractNumber = 'CON20257868';
$contract = DB::table('contracts')->where('contract_number', $contractNumber)->first();

if (!$contract) {
    echo "❌ ERROR: No se encontró el contrato $contractNumber\n";
    exit(1);
}

echo "✅ Contrato ID: {$contract->contract_id}\n\n";

// 2. Verificar todas las cuentas por cobrar
$accountsReceivable = AccountReceivable::where('contract_id', $contract->contract_id)
    ->orderBy('due_date', 'asc')
    ->get();

echo "📊 Análisis detallado de cuentas por cobrar:\n";
foreach ($accountsReceivable->take(5) as $index => $ar) {
    $accountNumber = $index + 1;
    echo "\n--- Cuenta por Cobrar #$accountNumber ---\n";
    echo "AR ID: {$ar->ar_id}\n";
    echo "Monto Original: {$ar->original_amount}\n";
    echo "Monto Pendiente: {$ar->outstanding_amount}\n";
    echo "Estado: {$ar->status}\n";
    echo "Fecha Vencimiento: {$ar->due_date}\n";
    echo "Creado: {$ar->created_at}\n";
    echo "Actualizado: {$ar->updated_at}\n";
    
    // Buscar pagos relacionados
    $payments = CustomerPayment::where('ar_id', $ar->ar_id)->get();
    echo "Pagos encontrados: {$payments->count()}\n";
    
    if ($payments->count() > 0) {
        foreach ($payments as $payment) {
            echo "  * Pago ID: {$payment->id}\n";
            echo "    - Monto: {$payment->amount}\n";
            echo "    - Fecha: {$payment->payment_date}\n";
            echo "    - Método: {$payment->payment_method}\n";
            echo "    - Estado: {$payment->status}\n";
        }
    } else {
        echo "  ⚠️  NO HAY PAGOS REGISTRADOS pero el estado es: {$ar->status}\n";
        
        // Si está marcada como PAID pero no hay pagos, investigar más
        if ($ar->status === 'PAID') {
            echo "  🔍 PROBLEMA DETECTADO: Estado PAID sin pagos registrados\n";
            
            // Verificar si hay registros en otras tablas relacionadas
            $installmentNum = $index + 1;
            $paymentSchedules = DB::table('payment_schedules')
                ->where('contract_id', $contract->contract_id)
                ->where('installment_number', $installmentNum)
                ->get();
                
            echo "  📅 Payment Schedules relacionados: {$paymentSchedules->count()}\n";
            foreach ($paymentSchedules as $schedule) {
                echo "    - Schedule ID: {$schedule->schedule_id}, Estado: {$schedule->status}, Monto: {$schedule->amount}\n";
            }
        }
    }
}

// 3. Verificar si hay pagos huérfanos (sin AR asociado)
echo "\n\n🔍 Verificando pagos huérfanos...\n";
$orphanPayments = DB::table('customer_payments as cp')
    ->leftJoin('accounts_receivable as ar', 'cp.ar_id', '=', 'ar.ar_id')
    ->where('ar.contract_id', $contract->contract_id)
    ->orWhereNull('ar.ar_id')
    ->select('cp.*', 'ar.contract_id')
    ->get();

echo "Pagos relacionados al contrato (incluyendo huérfanos): {$orphanPayments->count()}\n";
foreach ($orphanPayments as $payment) {
    echo "  * Pago ID: {$payment->id}, AR ID: {$payment->ar_id}, Monto: {$payment->amount}, Fecha: {$payment->payment_date}\n";
}

// 4. Proponer solución
echo "\n\n💡 DIAGNÓSTICO Y SOLUCIÓN:\n";
echo "El problema detectado es que las cuentas por cobrar están marcadas como 'PAID'\n";
echo "pero no tienen registros correspondientes en la tabla 'customer_payments'.\n";
echo "\nEsto puede deberse a:\n";
echo "1. Actualización manual del estado sin crear el registro de pago\n";
echo "2. Problema en el proceso de sincronización\n";
echo "3. Migración de datos incompleta\n";
echo "\nSOLUCIÓN RECOMENDADA:\n";
echo "Crear registros de CustomerPayment para las cuentas marcadas como PAID\n";
echo "o actualizar el CommissionPaymentVerificationService para considerar\n";
echo "el estado de AccountReceivable además de los pagos registrados.\n";

echo "\n=== FIN DEL ANÁLISIS ===\n";