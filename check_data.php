<?php
// Check total counts
$totalContracts = \Modules\Sales\Models\Contract::count();
$totalReservations = \Modules\Sales\Models\Reservation::count();
$totalCommissions = \Modules\HumanResources\Models\Commission::count();

$vigentes = \Modules\Sales\Models\Contract::where('status', 'vigente')->count();
$resueltos = \Modules\Sales\Models\Contract::where('status', 'resuelto')->count();

// Check contracts that have reservation-like contract numbers  
$reservationContracts = \Modules\Sales\Models\Contract::where(function($q) {
    $q->where('contract_number', 'LIKE', 'En Proceso Venta Nro.%')
      ->orWhere('contract_number', 'LIKE', 'Sep. Nro.%')
      ->orWhere('contract_number', 'LIKE', 'Res Nro.%');
})->count();

// Check contracts without advisor
$sinAsesor = \Modules\Sales\Models\Contract::whereNull('advisor_id')->count();

// Check Luis Tavara specifically
$a = \Modules\HumanResources\Models\Employee::whereHas('user', function($q) { $q->where('last_name', 'like', '%tavara%'); })->first();
$tavaraCtracts = $a ? \Modules\Sales\Models\Contract::where('advisor_id', $a->employee_id)->count() : 0;
$tavaraReservations = $a ? \Modules\Sales\Models\Reservation::where('advisor_id', $a->employee_id)->count() : 0;
$tavaraCommissions = $a ? \Modules\HumanResources\Models\Commission::where('employee_id', $a->employee_id)->count() : 0;

// List Tavara contracts with their contract_number
$tavaraContractsList = $a ? \Modules\Sales\Models\Contract::where('advisor_id', $a->employee_id)->get(['contract_id', 'contract_number', 'status', 'sign_date'])->toArray() : [];

$output = "=== TOTALES ===\n";
$output .= "Contratos: $totalContracts (Vigentes: $vigentes, Resueltos: $resueltos)\n";
$output .= "Reservas: $totalReservations\n";
$output .= "Comisiones: $totalCommissions\n";
$output .= "Contratos con numero de reserva (mal importados): $reservationContracts\n";
$output .= "Contratos sin asesor: $sinAsesor\n\n";

$output .= "=== LUIS TAVARA ===\n";
$output .= "Contratos: $tavaraCtracts\n";
$output .= "Reservas: $tavaraReservations\n";
$output .= "Comisiones: $tavaraCommissions\n";
$output .= "Contratos detalle:\n";
foreach($tavaraContractsList as $c) {
    $output .= "- ID: {$c['contract_id']} | Nro: {$c['contract_number']} | Status: {$c['status']} | Fecha: {$c['sign_date']}\n";
}

file_put_contents('out3.txt', $output);
echo "Done.\n";
