<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Modules\HumanResources\Models\Employee;
use Carbon\Carbon;

$employeeId = 1;
$month = 7;
$year = 2025;

echo "=== CREANDO CONTRATOS DE PRUEBA PARA EMPLEADO ID {$employeeId} EN {$month}/{$year} ===\n";

// Verificar si el empleado existe
$employee = Employee::find($employeeId);
if (!$employee) {
    echo "ERROR: Empleado con ID {$employeeId} no encontrado\n";
    exit(1);
}

echo "Empleado: {$employee->first_name} {$employee->last_name}\n";
echo "Tipo: {$employee->employee_type}\n\n";

// Obtener algunas reservaciones existentes
$reservations = Reservation::take(3)->get();

if ($reservations->count() == 0) {
    echo "ERROR: No hay reservaciones en la base de datos\n";
    exit(1);
}

echo "Reservaciones disponibles: " . $reservations->count() . "\n\n";

// Crear 3 contratos de prueba para julio 2025
$contractsCreated = 0;
for ($i = 1; $i <= 3; $i++) {
    $signDate = Carbon::create($year, $month, rand(1, 28));
    $reservation = $reservations->get($i - 1); // Usar reservación existente
    
    $contract = Contract::create([
        'reservation_id' => $reservation->reservation_id,
        'advisor_id' => $employeeId,
        'contract_number' => 'TEST-' . $year . $month . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
        'sign_date' => $signDate,
        'total_price' => rand(50000, 200000),
        'down_payment' => rand(10000, 30000),
        'financing_amount' => rand(40000, 170000),
        'interest_rate' => 0.085, // 8.5%
        'term_months' => rand(60, 120),
        'monthly_payment' => rand(800, 2000),
        'currency' => 'USD',
        'status' => 'vigente'
    ]);
    
    echo "Contrato creado: {$contract->contract_number}\n";
    echo "- Reservación ID: {$contract->reservation_id}\n";
    echo "- Fecha firma: {$contract->sign_date}\n";
    echo "- Valor: {$contract->total_price}\n";
    echo "- Estado: {$contract->status}\n\n";
    
    $contractsCreated++;
}

echo "=== CONTRATOS CREADOS: {$contractsCreated} ===\n";

// Verificar que se crearon correctamente
$verifyContracts = Contract::where('advisor_id', $employeeId)
    ->whereMonth('sign_date', $month)
    ->whereYear('sign_date', $year)
    ->get();

echo "Total de contratos verificados para {$month}/{$year}: " . $verifyContracts->count() . "\n";
echo "\n=== PROCESO COMPLETADO ===\n";