<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ESTRUCTURA DE PAGOS ===\n\n";

// 1. Check payments table columns
$cols = DB::select("SHOW COLUMNS FROM payments");
echo "--- Columnas de payments ---\n";
foreach ($cols as $col) {
    echo "  {$col->Field} ({$col->Type}) " . ($col->Null === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

// 2. Check if payments.contract_id is populated
$totalPayments = DB::table('payments')->count();
$withContractId = DB::table('payments')->whereNotNull('contract_id')->where('contract_id', '>', 0)->count();
$withScheduleId = DB::table('payments')->whereNotNull('schedule_id')->where('schedule_id', '>', 0)->count();
$noContractId = DB::table('payments')->where(function($q) { $q->whereNull('contract_id')->orWhere('contract_id', 0); })->count();
echo "\n--- Pagos en tabla payments ---\n";
echo "  Total: {$totalPayments}\n";
echo "  Con contract_id: {$withContractId}\n";
echo "  Con schedule_id: {$withScheduleId}\n";
echo "  SIN contract_id: {$noContractId}\n";

// 3. Check payment_schedules types
echo "\n--- Tipos en payment_schedules ---\n";
$types = DB::table('payment_schedules')
    ->select('type', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(CASE WHEN status="pagado" THEN 1 ELSE 0 END) as paid_cnt'))
    ->groupBy('type')
    ->get();
foreach ($types as $t) {
    echo "  type='{$t->type}' => total={$t->cnt}, pagados={$t->paid_cnt}\n";
}

// 4. Check if 'contado' or cash schedules exist
echo "\n--- Buscar 'contado' en schedules ---\n";
$contado = DB::table('payment_schedules')
    ->where(function($q) {
        $q->where('type', 'like', '%contado%')
          ->orWhere('notes', 'like', '%contado%')
          ->orWhere('type', 'like', '%cash%');
    })
    ->select('type', 'notes', 'status', 'amount', 'contract_id')
    ->limit(10)
    ->get();
echo "  Encontrados: " . $contado->count() . "\n";
foreach ($contado as $c) {
    echo "  contract_id={$c->contract_id}, type='{$c->type}', status={$c->status}, amount={$c->amount}, notes=" . substr($c->notes ?? '', 0, 50) . "\n";
}

// 5. Check payment_schedules with logicware_paid_amount
echo "\n--- Schedules con logicware_paid_amount ---\n";
$lwPaid = DB::table('payment_schedules')
    ->whereNotNull('logicware_paid_amount')
    ->where('logicware_paid_amount', '>', 0)
    ->count();
$lwPaidPagado = DB::table('payment_schedules')
    ->whereNotNull('logicware_paid_amount')
    ->where('logicware_paid_amount', '>', 0)
    ->where('status', 'pagado')
    ->count();
echo "  Con logicware_paid_amount > 0: {$lwPaid}\n";
echo "  De esos, status=pagado: {$lwPaidPagado}\n";

// 6. Payments that reference schedule but have NO contract_id set
echo "\n--- Payments via schedule sin contract_id directo ---\n";
$viaSched = DB::table('payments as p')
    ->join('payment_schedules as ps', 'p.schedule_id', '=', 'ps.schedule_id')
    ->where(function($q) { $q->whereNull('p.contract_id')->orWhere('p.contract_id', 0); })
    ->whereNotNull('ps.contract_id')
    ->count();
echo "  Pagos con schedule_id pero sin contract_id en payments: {$viaSched}\n";

// 7. Vigente contracts NOT reaching 5% - check what's in their schedules
echo "\n--- Contratos vigentes con pagos que NO aparecen en reporte 5% ---\n";
$contractsWithPaidSchedules = DB::table('payment_schedules as ps')
    ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
    ->join('lots as l', 'l.lot_id', '=', DB::raw('COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))'))
    ->leftJoin('lot_financial_templates as lft', 'lft.lot_id', '=', 'l.lot_id')
    ->where('c.status', 'vigente')
    ->where('ps.status', 'pagado')
    ->groupBy('ps.contract_id')
    ->select(
        'ps.contract_id',
        'c.contract_number',
        DB::raw('SUM(COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0)) as schedule_paid'),
        DB::raw('COALESCE(lft.precio_total_real, c.total_price) as precio_total_real'),
        DB::raw('ROUND(COALESCE(lft.precio_total_real, c.total_price) * 0.05, 2) as threshold')
    )
    ->havingRaw('schedule_paid > 0 AND schedule_paid < threshold')
    ->orderByDesc('schedule_paid')
    ->limit(10)
    ->get();

echo "  Contratos con pagos (en schedules) que NO alcanzan el 5%:\n";
foreach ($contractsWithPaidSchedules as $c) {
    $pct = $c->precio_total_real > 0 ? round(($c->schedule_paid / $c->precio_total_real) * 100, 2) : 0;
    echo "  #{$c->contract_number}: pagado=S/{$c->schedule_paid}, total_real=S/{$c->precio_total_real}, 5%=S/{$c->threshold}, actual={$pct}%\n";
}

// 8. Check contracts with schedule type 'contado' and their paid amounts
echo "\n--- Tipos de cuota pagados agrupados ---\n";
$typesPaid = DB::table('payment_schedules')
    ->where('status', 'pagado')
    ->select('type', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total'))
    ->groupBy('type')
    ->get();
foreach ($typesPaid as $t) {
    echo "  type='{$t->type}': pagados={$t->cnt}, total=S/{$t->total}\n";
}

// 9. Check if payments table has entries WITHOUT schedule_id (direct payments)
echo "\n--- Pagos directos (sin schedule_id) ---\n";
$directPayments = DB::table('payments')
    ->where(function($q) { $q->whereNull('schedule_id')->orWhere('schedule_id', 0); })
    ->count();
$directWithContract = DB::table('payments')
    ->where(function($q) { $q->whereNull('schedule_id')->orWhere('schedule_id', 0); })
    ->whereNotNull('contract_id')
    ->where('contract_id', '>', 0)
    ->count();
echo "  Sin schedule_id: {$directPayments}\n";
echo "  De esos, con contract_id: {$directWithContract}\n";

if ($directPayments > 0) {
    $samples = DB::table('payments')
        ->where(function($q) { $q->whereNull('schedule_id')->orWhere('schedule_id', 0); })
        ->select('payment_id', 'contract_id', 'amount', 'method', 'payment_date')
        ->limit(5)
        ->get();
    foreach ($samples as $s) {
        echo "  payment_id={$s->payment_id}, contract_id={$s->contract_id}, amount={$s->amount}, method={$s->method}, date={$s->payment_date}\n";
    }
}

// 10. Cuota inicial - check if initial payment schedules are being counted
echo "\n--- Cuotas iniciales pagadas ---\n";
$iniciales = DB::table('payment_schedules')
    ->where('status', 'pagado')
    ->where(function($q) {
        $q->where('type', 'like', '%inicial%')
          ->orWhere('type', 'like', '%initial%')
          ->orWhere('type', 'like', '%down%')
          ->orWhere('installment_number', 0);
    })
    ->count();
echo "  Cuotas iniciales pagadas: {$iniciales}\n";

echo "\n=== FIN DIAGNÓSTICO ===\n";
