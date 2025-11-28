<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
$numbers = [
    '202511-000000473',
    '202511-000000490',
    '202511-000000492',
    '202511-000000493',
];
foreach ($numbers as $num) {
    $c = DB::table('contracts as c')
        ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
        ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
        ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
        ->select(
            'c.contract_id','c.contract_number','c.sign_date','c.total_price','c.down_payment','c.monthly_payment','c.term_months',
            'r.deposit_amount','l.external_code','l.num_lot','m.name as manzana_name','l.manzana_id'
        )
        ->where('c.contract_number', $num)
        ->first();
    if (!$c) { echo "N° VENTA {$num} not found\n"; continue; }
    $sign = $c->sign_date ? Carbon::parse($c->sign_date) : null;
    $schedules = DB::table('payment_schedules')->where('contract_id',$c->contract_id)->orderBy('due_date','asc')->get();
    $preInitialSum = 0.0; $postInitials = []; $notesSep = 0.0; $finCount = 0; $balloon = 0.0;
    foreach ($schedules as $sc) {
        $type = $sc->type ?? ''; $amt = (float)($sc->amount ?? 0); $due = $sc->due_date ? Carbon::parse($sc->due_date) : null;
        if ($type === 'inicial') {
            if ($sign && $due && $due->lt($sign)) { $preInitialSum += $amt; if ($notesSep<=0 && stripos($sc->notes ?? '', 'separ')!==false) { $notesSep=$amt; } }
            else { if (count($postInitials)<5) { $postInitials[] = $amt; } }
        } elseif ($type === 'financiamiento') { $finCount++; }
        elseif ($type === 'balon') { $balloon = $amt; }
    }
    $ext = $c->external_code; $mz = null; $lot = null;
    if ($ext && strpos($ext,'-')!==false) { [$mzPart,$lotPart]=explode('-', $ext,2); $mz=trim($mzPart); $lot=ltrim(trim($lotPart),'0'); }
    else { $mz = $c->manzana_name ?: $c->manzana_id; $lot = $c->num_lot; }
    $sep = (float)($c->deposit_amount ?? 0);
    if ($sep<=0) { $sep = $notesSep>0 ? $notesSep : $preInitialSum; }
    echo "\nN° VENTA: {$c->contract_number}\n";
    echo "MZ: " . ($mz ?? 'N/A') . " | LOTE: " . ($lot ?? 'N/A') . "\n";
    echo "RESERVA(separacion): S/ " . number_format($sep,2) . "\n";
    echo "Iniciales post-firma: " . implode(', ', array_map(function($v){return 'S/ '.number_format($v,2);}, $postInitials)) . "\n";
    echo "Cuotas financiamiento: {$finCount} | Balloon: S/ " . number_format($balloon,2) . "\n";
}
