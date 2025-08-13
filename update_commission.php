<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Collections\Models\CustomerPayment;

try {
    // Obtener la primera comisión
    $commission = Commission::first();
    
    if (!$commission) {
        echo "No se encontraron comisiones\n";
        exit(1);
    }
    
    // Actualizar la comisión con los datos necesarios
    $commission->update([
        'customer_id' => 1,
        'period_start' => '2025-08-01',
        'period_end' => '2025-08-31',
        'verification_status' => 'pending'
    ]);
    
    // Crear pago de cliente correspondiente
    $payment = CustomerPayment::create([
        'client_id' => 1,
        'ar_id' => 1,
        'payment_number' => 'PAY-TEST-001',
        'payment_date' => '2025-08-15',
        'amount' => 2000,
        'currency' => 'PEN',
        'payment_method' => 'TRANSFER',
        'reference_number' => 'REF-TEST-001',
        'notes' => 'Pago de prueba para sincronización HR-Collections',
        'processed_by' => 23
    ]);
    
    echo "Datos actualizados exitosamente:\n";
    echo "- Comisión ID: {$commission->commission_id}\n";
    echo "- Customer ID: {$commission->customer_id}\n";
    echo "- Período: {$commission->period_start} a {$commission->period_end}\n";
    echo "- Pago ID: {$payment->payment_id}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}