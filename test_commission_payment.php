<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar la aplicación Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Collections\Models\AccountReceivable;
use Modules\HumanResources\app\Services\CommissionPaymentVerificationService;

echo "=== PRUEBA DE CORRECCIÓN DE COMISIONES ===\n\n";

// 1. Buscar un contrato que tenga PaymentSchedule
echo "1. Buscando contratos con PaymentSchedule...\n";
$paymentSchedule = PaymentSchedule::first();

if (!$paymentSchedule) {
    echo "❌ No se encontraron PaymentSchedules en la base de datos\n";
    exit(1);
}

$contractId = $paymentSchedule->contract_id;
echo "✅ Encontrado contrato con PaymentSchedule: {$contractId}\n";

// 2. Verificar cuántos PaymentSchedule tiene este contrato
$scheduleCount = PaymentSchedule::where('contract_id', $contractId)->count();
echo "📊 Número de cuotas en el cronograma: {$scheduleCount}\n";

// 3. Verificar cuántos AccountReceivable tiene este contrato
$arCount = AccountReceivable::where('contract_id', $contractId)->count();
echo "📊 Número de AccountReceivable: {$arCount}\n";

// 4. Buscar una comisión para este contrato
echo "\n2. Buscando comisión para el contrato {$contractId}...\n";
$commission = Commission::where('contract_id', $contractId)->first();

if (!$commission) {
    echo "❌ No se encontró comisión para el contrato {$contractId}\n";
    echo "Creando una comisión de prueba...\n";
    
    // Crear una comisión de prueba
    $commission = new Commission();
    $commission->id = \Illuminate\Support\Str::uuid();
    $commission->contract_id = $contractId;
    $commission->payment_part = 1; // Probar con la primera parte
    $commission->amount = 1000;
    $commission->status = 'pending';
    $commission->requires_client_payment_verification = true;
    $commission->save();
    
    echo "✅ Comisión creada: {$commission->id}\n";
} else {
    echo "✅ Comisión encontrada: {$commission->id}\n";
    echo "   - Payment part: {$commission->payment_part}\n";
    echo "   - Requires verification: " . ($commission->requires_client_payment_verification ? 'Sí' : 'No') . "\n";
}

// 5. Probar el servicio de verificación
echo "\n3. Probando el servicio de verificación corregido...\n";

try {
    $verificationService = new CommissionPaymentVerificationService();
    $results = $verificationService->verifyClientPayments($commission);
    
    echo "✅ Verificación completada sin errores\n";
    echo "📋 Resultados:\n";
    echo "   - Mensaje: {$results['message']}\n";
    
    if (isset($results['first_payment'])) {
        echo "   - Primera cuota: " . ($results['first_payment'] ? '✅ Verificada' : '❌ No verificada') . "\n";
    }
    
    if (isset($results['second_payment'])) {
        echo "   - Segunda cuota: " . ($results['second_payment'] ? '✅ Verificada' : '❌ No verificada') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error en la verificación: {$e->getMessage()}\n";
    echo "📍 Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

// 6. Probar también con un contrato SIN PaymentSchedule
echo "\n4. Probando con contrato SIN PaymentSchedule...\n";

$contractWithoutSchedule = AccountReceivable::whereNotIn('contract_id', 
    PaymentSchedule::select('contract_id')->distinct()->pluck('contract_id')
)->first();

if ($contractWithoutSchedule) {
    $contractId2 = $contractWithoutSchedule->contract_id;
    echo "✅ Encontrado contrato sin PaymentSchedule: {$contractId2}\n";
    
    $arCount2 = AccountReceivable::where('contract_id', $contractId2)->count();
    echo "📊 Número de AccountReceivable: {$arCount2}\n";
    
    // Buscar o crear comisión
    $commission2 = Commission::where('contract_id', $contractId2)->first();
    
    if (!$commission2) {
        $commission2 = new Commission();
        $commission2->id = \Illuminate\Support\Str::uuid();
        $commission2->contract_id = $contractId2;
        $commission2->payment_part = 1;
        $commission2->amount = 1000;
        $commission2->status = 'pending';
        $commission2->requires_client_payment_verification = true;
        $commission2->save();
        echo "✅ Comisión creada: {$commission2->id}\n";
    } else {
        echo "✅ Comisión encontrada: {$commission2->id}\n";
    }
    
    try {
        $results2 = $verificationService->verifyClientPayments($commission2);
        echo "✅ Verificación completada sin errores\n";
        echo "📋 Resultados:\n";
        echo "   - Mensaje: {$results2['message']}\n";
        
        if (isset($results2['first_payment'])) {
            echo "   - Primera cuota: " . ($results2['first_payment'] ? '✅ Verificada' : '❌ No verificada') . "\n";
        }
        
        if (isset($results2['second_payment'])) {
            echo "   - Segunda cuota: " . ($results2['second_payment'] ? '✅ Verificada' : '❌ No verificada') . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error en la verificación: {$e->getMessage()}\n";
        echo "📍 Archivo: {$e->getFile()}:{$e->getLine()}\n";
    }
} else {
    echo "⚠️ No se encontraron contratos sin PaymentSchedule\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";