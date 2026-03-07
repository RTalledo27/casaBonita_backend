<?php
$a = \Modules\HumanResources\Models\Employee::whereHas('user', function($q) { $q->where('last_name', 'like', '%tavara%'); })->first();
if (!$a) die("No advisor\n");
$c = \Modules\Sales\Models\Contract::where('advisor_id', $a->employee_id)->count();
$r = \Modules\Sales\Models\Reservation::where('advisor_id', $a->employee_id)->count();
$c_resuelto = \Modules\Sales\Models\Contract::where('advisor_id', $a->employee_id)->where('status', 'resuelto')->count();
$r_anulada = \Modules\Sales\Models\Reservation::where('advisor_id', $a->employee_id)->where('status', 'anulada')->count();
file_put_contents('out2.txt', "Contratos: $c (Resueltos: $c_resuelto)\nReservas: $r (Anuladas: $r_anulada)\n");
