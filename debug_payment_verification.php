<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Repositories\CommissionRepository;

echo "🔍 DEBUG: VERIFICACIÓN DE PAGOS DE COMISIÓN\n";
echo "==========================================\n\n";

// Usar el contrato CON20257868 que está reportando el error
$contractNumber = 'CON20257868';

// Buscar el contract_id por el número de contrato
$contract = Contract::where('contract_number', $contractNumber)->first();

if (!$contract) {
    echo "❌ No se encontró el contrato $contractNumber\n";
    exit;
}

$contractId = $contract->contract_id;
echo "✅ Contrato encontrado: ID $contractId, Número: $contractNumber\n\n";
$commission = Commission::where('contract_id', $contractId)
    ->where('payment_part', 1)
    ->where('status', 'generated')
    ->first();

if (!$commission) {
    echo "❌ No se encontró comisión para contrato $contractId parte 1\n";
    exit;
}

echo "✅ Comisión encontrada:\n";
echo "ID: {$commission->commission_id}\n";
echo "Contrato: {$commission->contract_id}\n";
echo "Parte: {$commission->payment_part}\n";
echo "Estado: {$commission->status}\n";
echo "Estado de verificación: {$commission->payment_verification_status}\n";
echo "Requiere verificación: " . ($commission->requires_client_payment_verification ? 'SÍ' : 'NO') . "\n\n";

// Verificar AccountReceivables para este contrato
echo "📋 VERIFICANDO ACCOUNT RECEIVABLES\n";
echo "=================================\n";
$accountReceivables = AccountReceivable::where('contract_id', $contractId)
    ->orderBy('due_date')
    ->get();

echo "Account Receivables encontrados: {$accountReceivables->count()}\n\n";

foreach ($accountReceivables as $ar) {
    echo "AR ID: {$ar->ar_id}\n";
    echo "Número AR: {$ar->ar_number}\n";
    echo "Estado: {$ar->status}\n";
    echo "Monto original: {$ar->original_amount}\n";
    echo "Monto pendiente: {$ar->outstanding_amount}\n";
    echo "Fecha vencimiento: {$ar->due_date}\n";
    echo "---\n";
}

// Verificar CustomerPayments para este contrato
echo "\n💰 VERIFICANDO CUSTOMER PAYMENTS\n";
echo "===============================\n";

// Buscar pagos a través de AccountReceivables del contrato
$arIds = AccountReceivable::where('contract_id', $contractId)->pluck('ar_id');

$customerPayments = CustomerPayment::whereIn('ar_id', $arIds)
    ->orderBy('payment_date')
    ->get();

echo "Customer Payments encontrados: {$customerPayments->count()}\n\n";

foreach ($customerPayments as $payment) {
    echo "Payment ID: {$payment->payment_id}\n";
    echo "AR ID: {$payment->ar_id}\n";
    echo "Monto: {$payment->amount}\n";
    echo "Fecha: {$payment->payment_date}\n";
    echo "Método: {$payment->payment_method}\n";
    echo "Referencia: {$payment->reference_number}\n";
    echo "---\n";
}

// Ahora probar el servicio de verificación
echo "\n🔧 PROBANDO SERVICIO DE VERIFICACIÓN\n";
echo "===================================\n";

try {
    $verificationService = new CommissionPaymentVerificationService();
    
    echo "Ejecutando verifyClientPayments para comisión {$commission->commission_id}...\n";
    
    $result = $verificationService->verifyClientPayments($commission);
    
    echo "Resultado: " . (is_array($result) ? json_encode($result) : $result) . "\n";
    
    // Recargar la comisión para ver cambios
    $commission->refresh();
    echo "\nEstado actualizado de la comisión:\n";
    echo "Estado de verificación: {$commission->payment_verification_status}\n";
    echo "Elegible para pago: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error en verificación: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
}

// NUEVA SECCIÓN: DEBUG DEL PROCESO DE PAGO
echo "\n🔧 DEBUGGEANDO PROCESO DE PAGO DE COMISIÓN\n";
echo "============================================\n";

try {
    // Simular exactamente lo que hace el controlador
    echo "Intentando pagar la comisión ID: {$commission->commission_id}\n";
    echo "Payment part: {$commission->payment_part}\n";
    echo "Estado actual: {$commission->status}\n";
    echo "Payment status: {$commission->payment_status}\n\n";
    
    // Verificar validaciones del repositorio
    echo "📋 VERIFICANDO VALIDACIONES DEL REPOSITORIO\n";
    echo "==========================================\n";
    
    // Verificar si es comisión padre o hija
    if ($commission->parent_commission_id) {
        echo "Esta es una comisión HIJA (parent_commission_id: {$commission->parent_commission_id})\n";
        
        // Verificar estado de la comisión padre
        $parentCommission = Commission::find($commission->parent_commission_id);
        if ($parentCommission) {
            echo "Estado de comisión padre: {$parentCommission->payment_status}\n";
            if ($parentCommission->payment_status === 'pagado') {
                echo "❌ PROBLEMA: La comisión padre ya está pagada, esto impide pagar la hija\n";
            } else {
                echo "✅ OK: La comisión padre no está pagada, se puede proceder\n";
            }
        }
    } else {
        echo "Esta es una comisión PADRE (sin parent_commission_id)\n";
        
        // Verificar estado de comisiones hijas
        $childCommissions = Commission::where('parent_commission_id', $commission->commission_id)->get();
        echo "Comisiones hijas encontradas: {$childCommissions->count()}\n";
        
        $paidChildren = $childCommissions->where('payment_status', 'pagado')->count();
        echo "Comisiones hijas pagadas: $paidChildren\n";
        
        if ($paidChildren > 0) {
            echo "❌ PROBLEMA: Hay comisiones hijas pagadas, esto impide pagar la padre\n";
            foreach ($childCommissions->where('payment_status', 'pagado') as $child) {
                echo "  - Hija pagada ID: {$child->commission_id}, Part: {$child->payment_part}\n";
            }
        } else {
            echo "✅ OK: No hay comisiones hijas pagadas, se puede proceder\n";
        }
    }
    
    // Intentar el pago usando el repositorio directamente
    echo "\n🔄 INTENTANDO PAGO DIRECTO\n";
    echo "=========================\n";
    
    $commissionRepo = new CommissionRepository(new Commission());
    
    $updatedCount = $commissionRepo->markMultipleAsPaid([$commission->commission_id]);
    
    echo "Registros actualizados: $updatedCount\n";
    
    if ($updatedCount > 0) {
        echo "✅ ÉXITO: El pago se procesó correctamente\n";
        
        // Recargar la comisión para ver cambios
        $commission->refresh();
        echo "Nuevo estado: {$commission->status}\n";
        echo "Nuevo payment_status: {$commission->payment_status}\n";
        echo "Payment_date: {$commission->payment_date}\n";
    } else {
        echo "❌ FALLO: No se actualizó ningún registro\n";
        echo "Esto indica que las validaciones del repositorio están bloqueando el pago\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error en proceso de pago: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
}

echo "\n✅ Debug completado\n";
echo "\n📝 RESUMEN:\n";
echo "=========\n";
echo "Contrato: $contractNumber (ID: $contractId)\n";
echo "Comisión ID: {$commission->commission_id}\n";
echo "Payment part: {$commission->payment_part}\n";
echo "Verificación de pagos: {$commission->payment_verification_status}\n";
echo "Estado actual: {$commission->status}\n";
echo "Payment status: {$commission->payment_status}\n";
echo "\nSi el pago falló, revisar las validaciones del repositorio mostradas arriba.\n";