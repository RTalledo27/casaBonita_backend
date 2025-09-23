<?php

use Modules\HumanResources\Models\Commission;
use Modules\Collections\Models\AccountReceivable;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Modules\Collections\Models\CustomerPayment;

echo "🎭 SIMULANDO ERROR DE COMISIÓN\n";
echo "==============================\n\n";

// Usar el contrato 50 que sabemos que tiene datos
$contractId = 50;
$paymentPart = 1;

echo "📋 Preparando escenario de prueba para contrato: {$contractId}\n\n";

// 1. Buscar la comisión
$commission = Commission::where('contract_id', $contractId)
    ->where('payment_part', $paymentPart)
    ->first();

if (!$commission) {
    echo "❌ No se encontró comisión\n";
    exit(1);
}

echo "✅ Comisión encontrada: ID {$commission->id}\n";
echo "📊 Estado actual: {$commission->payment_verification_status}\n";
echo "🎯 Requiere verificación: " . ($commission->requires_client_payment_verification ? 'SÍ' : 'NO') . "\n\n";

// 2. Forzar que la comisión requiera verificación de pagos
echo "🔧 MODIFICANDO COMISIÓN PARA REQUERIR VERIFICACIÓN\n";
echo "================================================\n";

$commission->requires_client_payment_verification = true;
$commission->payment_verification_status = 'pending';
$commission->is_eligible_for_payment = false;
$commission->save();

echo "✅ Comisión modificada:\n";
echo "  - Requiere verificación: SÍ\n";
echo "  - Estado: pending\n";
echo "  - Elegible: NO\n\n";

// 3. Verificar cuentas por cobrar
$accountsReceivable = AccountReceivable::where('contract_id', $contractId)
    ->orderBy('due_date', 'asc')
    ->take(2)
    ->get();

echo "📊 Cuentas por cobrar para las primeras 2 cuotas:\n";
foreach ($accountsReceivable as $index => $ar) {
    $cuotaNum = $index + 1;
    echo "  - Cuota {$cuotaNum}: ID={$ar->id}, Estado={$ar->status}, Monto={$ar->original_amount}\n";
}

// 4. Modificar temporalmente el estado de las cuentas por cobrar para simular el error
echo "\n🔧 SIMULANDO ESCENARIO DE ERROR\n";
echo "===============================\n";

if ($accountsReceivable->count() >= 2) {
    $firstAR = $accountsReceivable->first();
    $secondAR = $accountsReceivable->skip(1)->first();
    
    // Cambiar temporalmente a PENDING para simular el error original
    $originalFirstStatus = $firstAR->status;
    $originalSecondStatus = $secondAR->status;
    
    $firstAR->status = 'PENDING';
    $secondAR->status = 'PENDING';
    $firstAR->save();
    $secondAR->save();
    
    echo "✅ Cuentas por cobrar cambiadas a PENDING temporalmente\n\n";
    
    // 5. Probar el servicio de verificación (debería fallar)
    echo "🔍 PROBANDO SERVICIO CON CUENTAS PENDING\n";
    echo "========================================\n";
    
    $verificationService = new CommissionPaymentVerificationService();
    
    try {
        $result = $verificationService->verifyClientPayments($commission);
        
        echo "📊 Resultado: " . ($result ? 'ÉXITO' : 'FALLÓ') . "\n";
        
        $commission->refresh();
        echo "📊 Estado después de verificación: {$commission->payment_verification_status}\n";
        echo "🎯 Elegible para pago: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n\n";
    }
    
    // 6. Restaurar estados originales y probar nuevamente
    echo "🔄 RESTAURANDO ESTADOS ORIGINALES\n";
    echo "=================================\n";
    
    $firstAR->status = $originalFirstStatus;
    $secondAR->status = $originalSecondStatus;
    $firstAR->save();
    $secondAR->save();
    
    echo "✅ Estados restaurados a: {$originalFirstStatus}\n\n";
    
    // 7. Probar nuevamente con la corrección
    echo "🔍 PROBANDO SERVICIO CON CORRECCIÓN\n";
    echo "===================================\n";
    
    try {
        $result = $verificationService->verifyClientPayments($commission);
        
        echo "📊 Resultado: " . ($result ? 'ÉXITO' : 'FALLÓ') . "\n";
        
        $commission->refresh();
        echo "📊 Estado después de verificación: {$commission->payment_verification_status}\n";
        echo "🎯 Elegible para pago: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n\n";
    }
}

echo "🏁 Simulación completada\n";
echo "\n💡 RESUMEN DE LA CORRECCIÓN:\n";
echo "============================\n";
echo "✅ El servicio ahora verifica primero el estado de AccountReceivable\n";
echo "✅ Si está marcado como PAID, crea registro de verificación y retorna true\n";
echo "✅ Solo verifica CustomerPayment si AccountReceivable no está PAID\n";
echo "✅ Esto soluciona el error original de sincronización\n";