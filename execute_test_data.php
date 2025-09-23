<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;

echo "🔧 CREANDO DATOS DE PRUEBA PARA VERIFICACIÓN DE PAGOS\n";
echo "====================================================\n\n";

try {
    // 1. Verificar contrato
    $contract = \Modules\Sales\Models\Contract::where('contract_id', 94)->first();
    if (!$contract) {
        echo "❌ Error: Contrato 94 no encontrado\n";
        exit(1);
    }
    echo "✅ Contrato encontrado: {$contract->contract_number}\n";
    echo "Cliente ID: {$contract->client_id}\n\n";

    // 2. Limpiar datos existentes
    echo "🧹 Limpiando datos existentes...\n";
    
    // Eliminar por ar_number específicos también
    $testARNumbers = ['AR-94-001', 'AR-94-002'];
    $existingARsByNumber = AccountReceivable::whereIn('ar_number', $testARNumbers)->get();
    
    foreach ($existingARsByNumber as $ar) {
        // Eliminar pagos relacionados primero
        CustomerPayment::where('ar_id', $ar->ar_id)->delete();
        // Luego eliminar el AR
        $ar->delete();
    }
    
    // Eliminar por contract_id también
    $existingPayments = CustomerPayment::whereHas('accountReceivable', function($q) {
        $q->where('contract_id', 94);
    })->get();
    
    foreach ($existingPayments as $payment) {
        $payment->delete();
    }
    
    $existingARs = AccountReceivable::where('contract_id', 94)->get();
    foreach ($existingARs as $ar) {
        $ar->delete();
    }
    echo "✅ Datos anteriores eliminados\n\n";

    // 3. Crear primera cuota (PAID)
    echo "📋 Creando primera cuota...\n";
    $timestamp = time();
    $ar1 = AccountReceivable::create([
        'client_id' => $contract->client_id,
        'contract_id' => 94,
        'ar_number' => 'AR-94-001-' . $timestamp,
        'issue_date' => Carbon::now()->subDays(60),
        'due_date' => Carbon::now()->subDays(30),
        'original_amount' => 5000.00,
        'outstanding_amount' => 0.00,
        'currency' => 'PEN',
        'status' => 'PAID',
        'description' => 'Primera cuota - PRUEBA'
    ]);
    echo "✅ Primera cuota creada: AR ID {$ar1->ar_id}\n";

    // 4. Crear segunda cuota (PAID)
    echo "📋 Creando segunda cuota...\n";
    $ar2 = AccountReceivable::create([
        'client_id' => $contract->client_id,
        'contract_id' => 94,
        'ar_number' => 'AR-94-002-' . $timestamp,
        'issue_date' => Carbon::now()->subDays(30),
        'due_date' => Carbon::now(),
        'original_amount' => 5000.00,
        'outstanding_amount' => 0.00,
        'currency' => 'PEN',
        'status' => 'PAID',
        'description' => 'Segunda cuota - PRUEBA'
    ]);
    echo "✅ Segunda cuota creada: AR ID {$ar2->ar_id}\n";

    // 5. Crear pago para primera cuota
    echo "💰 Creando pago para primera cuota...\n";
    $payment1 = CustomerPayment::create([
        'client_id' => $contract->client_id,
        'ar_id' => $ar1->ar_id,
        'payment_number' => 'PAY-000001',
        'payment_date' => Carbon::now()->subDays(25),
        'amount' => 5000.00,
        'currency' => 'PEN',
        'payment_method' => 'TRANSFER',
        'reference_number' => 'REF-123456',
        'notes' => 'Pago primera cuota - PRUEBA',
        'processed_by' => 1
    ]);
    echo "✅ Pago 1 creado: Payment ID {$payment1->payment_id}\n";

    // 6. Crear pago para segunda cuota
    echo "💰 Creando pago para segunda cuota...\n";
    $payment2 = CustomerPayment::create([
        'client_id' => $contract->client_id,
        'ar_id' => $ar2->ar_id,
        'payment_number' => 'PAY-000002',
        'payment_date' => Carbon::now()->subDays(5),
        'amount' => 5000.00,
        'currency' => 'PEN',
        'payment_method' => 'TRANSFER',
        'reference_number' => 'REF-789012',
        'notes' => 'Pago segunda cuota - PRUEBA',
        'processed_by' => 1
    ]);
    echo "✅ Pago 2 creado: Payment ID {$payment2->payment_id}\n";

    // 7. Verificar datos creados
    echo "\n🎯 VERIFICACIÓN FINAL\n";
    echo "===================\n";
    echo "AR1 ID: {$ar1->ar_id} - Estado: {$ar1->status}\n";
    echo "AR2 ID: {$ar2->ar_id} - Estado: {$ar2->status}\n";
    echo "Payment1 ID: {$payment1->payment_id} - Monto: {$payment1->amount}\n";
    echo "Payment2 ID: {$payment2->payment_id} - Monto: {$payment2->amount}\n";
    
    echo "\n✅ DATOS DE PRUEBA CREADOS EXITOSAMENTE\n";
    echo "Ahora puedes probar el pago de la comisión parte 1 del contrato 94\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
    exit(1);
}