<?php
$advisorName = 'Tavara';
$advisor = \Modules\HumanResources\Models\Employee::whereHas('user', function($q) use ($advisorName) {
    $q->whereRaw('last_name LIKE ?', ['%'.$advisorName.'%']);
})->first();

if (!$advisor) {
    echo 'Asesor no encontrado' . PHP_EOL;
    return;
}

echo 'Advisor ID: ' . $advisor->employee_id . PHP_EOL;

echo PHP_EOL . 'CONTRATOS:' . PHP_EOL;
$contracts = \Modules\Sales\Models\Contract::where('advisor_id', $advisor->employee_id)->get();
echo 'Total Contratos: ' . $contracts->count() . PHP_EOL;
foreach($contracts as $c) {
    echo '- ID: ' . $c->contract_id . ' | Nro: ' . $c->contract_number . ' | Status: ' . $c->status . ' | Lote ID: ' . $c->lot_id . ' | Creado: ' . $c->created_at . ' | Fecha Contrato: ' . $c->sign_date . PHP_EOL;
}

echo PHP_EOL . 'RESERVAS:' . PHP_EOL;
$reservations = \Modules\Sales\Models\Reservation::where('advisor_id', $advisor->employee_id)->get();
echo 'Total Reservas: ' . $reservations->count() . PHP_EOL;
foreach($reservations as $r) {
    echo '- ID: ' . $r->reservation_id . ' | Status: ' . $r->status . ' | Lote ID: ' . $r->lot_id . ' | D.Amount: ' . $r->deposit_amount . ' | Creado: ' . $r->created_at . ' | Fecha Reserva: ' . $r->reservation_date . PHP_EOL;
}
