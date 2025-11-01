<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUGGING ASESORES EN CONTRATOS ===\n\n";

// Ver los contratos de enero 2025 (como en las capturas)
echo "1. CONTRATOS DE ENERO 2025 - COMPARACIÓN FRONTEND VS DATABASE:\n\n";

$contracts = DB::table('contracts as c')
    ->join('clients as cl', 'c.client_id', '=', 'cl.client_id')
    ->leftJoin('users as adv', 'c.advisor_id', '=', 'adv.user_id')
    ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
    ->select(
        'c.contract_id',
        'c.contract_number',
        'c.sign_date',
        DB::raw('CONCAT(cl.first_name, " ", cl.last_name) as cliente'),
        'c.advisor_id as contract_advisor_id',
        'r.advisor_id as reservation_advisor_id',
        DB::raw('CONCAT(COALESCE(adv.first_name, ""), " ", COALESCE(adv.last_name, "")) as asesor_desde_contract'),
        'c.total_price'
    )
    ->whereYear('c.sign_date', 2025)
    ->whereMonth('c.sign_date', 1)
    ->orderBy('c.sign_date', 'asc')
    ->get();

echo "FECHA       | CLIENTE                          | contract.advisor_id | reservation.advisor_id | ASESOR (desde contract)\n";
echo str_repeat("-", 140) . "\n";

foreach ($contracts as $contract) {
    printf(
        "%s | %-32s | %-19s | %-22s | %s\n",
        $contract->sign_date,
        substr($contract->cliente, 0, 32),
        $contract->contract_advisor_id ?? 'NULL',
        $contract->reservation_advisor_id ?? 'NULL',
        $contract->asesor_desde_contract
    );
}

// Verificar si el problema es con las reservaciones
echo "\n\n2. ¿HAY DIFERENCIA ENTRE contract.advisor_id y reservation.advisor_id?\n\n";

$differences = DB::table('contracts as c')
    ->join('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
    ->leftJoin('users as u1', 'c.advisor_id', '=', 'u1.user_id')
    ->leftJoin('users as u2', 'r.advisor_id', '=', 'u2.user_id')
    ->select(
        'c.contract_number',
        'c.advisor_id as contract_advisor',
        'r.advisor_id as reservation_advisor',
        DB::raw('CONCAT(COALESCE(u1.first_name, ""), " ", COALESCE(u1.last_name, "SIN NOMBRE")) as asesor_contract'),
        DB::raw('CONCAT(COALESCE(u2.first_name, ""), " ", COALESCE(u2.last_name, "SIN NOMBRE")) as asesor_reservation')
    )
    ->whereRaw('c.advisor_id != r.advisor_id')
    ->get();

if ($differences->isEmpty()) {
    echo "✅ NO hay diferencias - contract.advisor_id y reservation.advisor_id coinciden\n";
} else {
    echo "❌ SÍ HAY DIFERENCIAS:\n\n";
    echo "Contrato     | contract.advisor_id | reservation.advisor_id | Asesor Contract        | Asesor Reservation\n";
    echo str_repeat("-", 120) . "\n";
    foreach ($differences as $diff) {
        printf(
            "%-12s | %-19s | %-22s | %-22s | %s\n",
            $diff->contract_number,
            $diff->contract_advisor ?? 'NULL',
            $diff->reservation_advisor ?? 'NULL',
            $diff->asesor_contract,
            $diff->asesor_reservation
        );
    }
}

// Verificar quién es JOSE EDUARDO RONDOY TALLEDO
echo "\n\n3. ¿QUIÉN ES JOSE EDUARDO RONDOY TALLEDO?\n\n";

$joseEduardo = DB::table('users')
    ->where('first_name', 'LIKE', '%JOSE EDUARDO%')
    ->orWhere('last_name', 'LIKE', '%RONDOY%')
    ->get();

foreach ($joseEduardo as $user) {
    echo "user_id: {$user->user_id}\n";
    echo "Nombre: {$user->first_name} {$user->last_name}\n";
    echo "Position: {$user->position}\n";
    echo "Department: {$user->department}\n\n";
    
    // Ver cuántos contratos tiene asignados
    $contractCount = DB::table('contracts')->where('advisor_id', $user->user_id)->count();
    echo "Contratos asignados: {$contractCount}\n";
    
    // Ver cuántas reservaciones tiene
    $reservationCount = DB::table('reservations')->where('advisor_id', $user->user_id)->count();
    echo "Reservaciones asignadas: {$reservationCount}\n\n";
}

// Ver los asesores que SÍ aparecen en el frontend
echo "\n4. ASESORES QUE DEBERÍAN APARECER (según frontend):\n\n";
$frontendAdvisors = [
    'LUIS ENRIQUE TAVARA CASTILLO',
    'LEWIS TEODORO FARFAN MERINO',
    'ADRIANA JOSELINE ASTOCONDOR SERNAQUE',
    'RENATO JUVENAL MORAN QUIROZ',
    'FERNANDO DAVID FEIJOO QUIROZ',
    'NUIT ALEXANDRA SUAREZ TUSE',
    'RENZO ALEXANDER CASTILLO ABRAMONTE',
    'JIMY OCAÑA CHOQUE HUANCA',
    'DANIELA AIRAM MERINO VALIENTE',
    'PAOLA JUDITH CANDELA NEIRA'
];

foreach ($frontendAdvisors as $name) {
    $parts = explode(' ', $name);
    $firstName = $parts[0] . ' ' . ($parts[1] ?? '');
    
    $user = DB::table('users')
        ->where(DB::raw('CONCAT(first_name, " ", last_name)'), 'LIKE', '%' . $name . '%')
        ->first();
    
    if ($user) {
        $contractsAsContract = DB::table('contracts')->where('advisor_id', $user->user_id)->count();
        $contractsAsReservation = DB::table('reservations')->where('advisor_id', $user->user_id)->count();
        
        echo "{$name}\n";
        echo "  user_id: {$user->user_id}\n";
        echo "  Contratos (contract.advisor_id): {$contractsAsContract}\n";
        echo "  Reservaciones (reservation.advisor_id): {$contractsAsReservation}\n\n";
    } else {
        echo "{$name} - ❌ NO ENCONTRADO\n\n";
    }
}

echo "\n✅ Análisis completo\n";
