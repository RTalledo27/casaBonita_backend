<?php

use Illuminate\Foundation\Application;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Reservation;
use Modules\CRM\Models\Client;

echo "=== CREANDO DATOS DE PRUEBA ===\n";

// Actualizar el primer empleado
$employee = Employee::find(1);
if ($employee) {
    $employee->update([
        'name' => 'Juan Pérez',
        'position' => 'Asesor de Ventas',
        'email' => 'juan.perez@casabonita.com',
        'phone' => '987654321',
        'hire_date' => '2024-01-01',
        'base_salary' => 2500.00,
        'commission_rate' => 0.05
    ]);
    echo "Empleado actualizado: {$employee->name}\n";
}

// Verificar si existe un cliente
$client = Client::first();
if (!$client) {
    echo "No hay clientes disponibles. Creando cliente de prueba...\n";
    // Aquí podrías crear un cliente si es necesario
}

// Crear una reserva de prueba si no existe
$reservation = Reservation::first();
if (!$reservation) {
    echo "No hay reservas disponibles. Necesitas crear una reserva primero.\n";
} else {
    echo "Reserva encontrada: ID {$reservation->reservation_id}\n";
    
    // Crear un contrato de prueba para el mes actual
    $currentDate = date('Y-m-d');
    
    $contract = Contract::create([
        'contract_number' => 'CONT-' . date('Ymd') . '-001',
        'reservation_id' => $reservation->reservation_id,
        'advisor_id' => 1, // El empleado que acabamos de actualizar
        'sign_date' => $currentDate,
        'total_price' => 150000.00,
        'down_payment' => 30000.00, // 20% de inicial
        'financing_amount' => 120000.00,
        'interest_rate' => 0.08, // 8% anual
        'term_months' => 120, // 10 años
        'monthly_payment' => 1456.00,
        'currency' => 'PEN',
        'status' => 'vigente'
    ]);
    
    echo "Contrato creado: {$contract->contract_number} por S/ {$contract->total_price}\n";
}

echo "\n=== DATOS DE PRUEBA CREADOS ===\n";