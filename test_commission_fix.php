<?php

// Este script debe ejecutarse con: php artisan tinker
// O crear un comando artisan personalizado

use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Modules\Collections\Models\AccountReceivable;
use App\Models\CommissionPaymentVerification;

echo "🔧 PRUEBA DE CORRECCIÓN - Verificación de Comisiones\n";
echo "================================================\n\n";

// Obtener el contrato CON20257868
$contractId = 'CON20257868';
echo "📋 Analizando contrato: $contractId\n\n";

// Obtener comisiones del contrato
$commissions = Commission::where('contract_id', $contractId)
    ->where('payment_part', 1) // Solo la primera parte
    ->get();

if ($commissions->isEmpty()) {
    echo "❌ No se encontraron comisiones para el contrato $contractId con payment_part = 1\n";
    exit(1);
}

echo "✅ Comisiones encontradas: " . $commissions->count() . "\n\n";

// Obtener cuentas por cobrar
$accountsReceivable = AccountReceivable::where('contract_id', $contractId)
    ->orderBy('due_date', 'asc')
    ->get();

echo "📊 Estado actual de cuentas por cobrar:\n";
foreach ($accountsReceivable->take(2) as $index => $ar) {
    $cuotaNum = $index + 1;
    echo "  Cuota $cuotaNum: Estado = {$ar->status}, Monto = {$ar->original_amount}\n";
}
echo "\n";

// Probar el servicio de verificación corregido
$verificationService = new CommissionPaymentVerificationService();

foreach ($commissions as $commission) {
    echo "🧪 Probando comisión ID: {$commission->id}\n";
    echo "   - Payment Part: {$commission->payment_part}\n";
    echo "   - Estado actual: {$commission->payment_verification_status}\n";
    echo "   - Elegible para pago: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n\n";
    
    try {
        // Ejecutar verificación
        $results = $verificationService->verifyClientPayments($commission);
        
        echo "📋 Resultados de verificación:\n";
        echo "   - Primera cuota: " . ($results['first_payment'] ? '✅ VERIFICADA' : '❌ NO VERIFICADA') . "\n";
        echo "   - Segunda cuota: " . ($results['second_payment'] ? '✅ VERIFICADA' : '❌ NO VERIFICADA') . "\n";
        echo "   - Mensaje: {$results['message']}\n\n";
        
        // Recargar la comisión para ver los cambios
        $commission->refresh();
        
        echo "📈 Estado después de la verificación:\n";
        echo "   - Estado de verificación: {$commission->payment_verification_status}\n";
        echo "   - Elegible para pago: " . ($commission->is_eligible_for_payment ? '✅ SÍ' : '❌ NO') . "\n";
        echo "   - Notas: {$commission->verification_notes}\n\n";
        
        // Verificar registros de verificación creados
        $verifications = CommissionPaymentVerification::where('commission_id', $commission->id)->get();
        echo "🔍 Verificaciones registradas: " . $verifications->count() . "\n";
        
        foreach ($verifications as $verification) {
            echo "   - Cuota: {$verification->payment_installment}\n";
            echo "   - Estado: {$verification->verification_status}\n";
            echo "   - Método: " . ($verification->verification_metadata['verification_method'] ?? 'customer_payment') . "\n";
            echo "   - Notas: {$verification->verification_notes}\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo "🎯 PRUEBA COMPLETADA\n";
echo "\n💡 RESUMEN:\n";
echo "- Se modificó CommissionPaymentVerificationService para considerar el estado 'PAID' de AccountReceivable\n";
echo "- Ahora las cuotas marcadas como PAID se consideran verificadas automáticamente\n";
echo "- Esto resuelve el problema de sincronización entre AccountReceivable y CustomerPayment\n";
echo "\n✅ La comisión debería estar ahora disponible para pago si las cuotas están marcadas como PAID\n";