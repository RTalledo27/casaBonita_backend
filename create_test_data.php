<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\CRM\Models\Client;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Modules\Collections\Models\CustomerPayment;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Carbon\Carbon;

try {
    // Obtener un cliente, empleado y reservación existente
    $client = Client::first();
    $employee = Employee::first();
    $reservation = Reservation::first();
    
    if (!$client || !$employee || !$reservation) {
        echo "No se encontraron clientes, empleados o reservaciones\n";
        exit(1);
    }
    
    // Crear contrato de prueba
    $contract = Contract::create([
        'reservation_id' => $reservation->reservation_id,
        'contract_number' => 'TEST-001',
        'sign_date' => '2025-08-01',
        'total_price' => 50000,
        'down_payment' => 10000,
        'status' => 'active'
    ]);
    
    // Crear comisión de prueba
    $commission = Commission::create([
        'employee_id' => $employee->employee_id,
        'contract_id' => $contract->contract_id,
        'commission_type' => 'sale',
        'sale_amount' => 50000,
        'commission_percentage' => 3.00,
        'commission_amount' => 1500,
        'payment_status' => 'pending',
        'period_month' => 8,
        'period_year' => 2025,
        'status' => 'generated',
        'verification_status' => 'pending',
        'customer_id' => $client->client_id,
        'period_start' => '2025-08-01',
        'period_end' => '2025-08-31'
    ]);
    
    // Crear pago de cliente correspondiente
    $payment = CustomerPayment::create([
        'client_id' => $client->client_id,
        'payment_number' => 'PAY-TEST-001',
        'payment_date' => '2025-08-15',
        'amount' => 2000,
        'currency' => 'PEN',
        'payment_method' => 'TRANSFER',
        'reference_number' => 'REF-TEST-001',
        'notes' => 'Pago de prueba para sincronización HR-Collections'
    ]);
    
    echo "Datos de prueba creados exitosamente:\n";
    echo "- Contrato ID: {$contract->contract_id}\n";
    echo "- Comisión ID: {$commission->commission_id}\n";
    echo "- Pago ID: {$payment->payment_id}\n";
    echo "- Cliente ID: {$client->client_id}\n";
    echo "- Empleado ID: {$employee->employee_id}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}