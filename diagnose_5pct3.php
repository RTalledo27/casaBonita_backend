<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Total payments table size
echo "=== PAYMENTS TABLE STATS ===\n";
$pStats = DB::select("SELECT COUNT(*) as cnt, SUM(amount) as total FROM payments");
echo "Total rows: {$pStats[0]->cnt}, Total amount: {$pStats[0]->total}\n";

$pByContract = DB::select("SELECT COUNT(DISTINCT contract_id) as cnt FROM payments WHERE contract_id IS NOT NULL");
echo "Distinct contracts with payments: {$pByContract[0]->cnt}\n";

$pNullSchedule = DB::select("SELECT COUNT(*) as cnt, SUM(amount) as total FROM payments WHERE schedule_id IS NULL");
echo "Payments with NULL schedule_id: {$pNullSchedule[0]->cnt} (amount: {$pNullSchedule[0]->total})\n\n";

// Check soft-deleted schedules
echo "=== SOFT-DELETED SCHEDULES ===\n";
$deleted = DB::select("SELECT COUNT(*) as cnt FROM payment_schedules WHERE deleted_at IS NOT NULL");
echo "Soft-deleted schedules: {$deleted[0]->cnt}\n";
$delPaid = DB::select("SELECT COUNT(*) as cnt, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total FROM payment_schedules WHERE deleted_at IS NOT NULL AND status = 'pagado'");
echo "Soft-deleted AND pagado: {$delPaid[0]->cnt} (amount: {$delPaid[0]->total})\n\n";

// Focus on contado-like contracts (only inicial schedules or <= 2 schedules)
echo "=== CONTADO-LIKE CONTRACTS: Checking if they appear in 5% report ===\n";
$contadoContracts = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price, COALESCE(c.bfh, 0) as bfh,
           c.lot_id, c.reservation_id,
           (SELECT GROUP_CONCAT(DISTINCT ps.type) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL) as sched_types,
           (SELECT COUNT(*) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL) as sched_count,
           (SELECT COUNT(*) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL AND ps.status='pagado') as paid_count,
           (SELECT SUM(COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0)) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL AND ps.status='pagado') as sched_paid,
           (SELECT SUM(p.amount) FROM payments p WHERE p.contract_id = c.contract_id) as payments_paid,
           (SELECT COALESCE(r.deposit_amount, 0) FROM reservations r WHERE r.reservation_id = c.reservation_id AND r.deposit_paid_at IS NOT NULL) as deposit,
           lft.precio_total_real, lft.precio_venta as lft_precio_venta, lft.bono_techo_propio
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r2.lot_id FROM reservations r2 WHERE r2.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    HAVING sched_count <= 6 AND (sched_types IS NULL OR sched_types = 'inicial' OR sched_types LIKE '%inicial%')
    ORDER BY sched_paid DESC
    LIMIT 30
");

foreach ($contadoContracts as $r) {
    $pp = $r->payments_paid ?? 0;
    $sp_schedIds = DB::select("SELECT ps.schedule_id FROM payment_schedules ps WHERE ps.contract_id = ? AND ps.status = 'pagado' AND ps.deleted_at IS NULL AND ps.schedule_id NOT IN (SELECT p.schedule_id FROM payments p WHERE p.schedule_id IS NOT NULL)", [$r->contract_id]);
    $spTotal = 0;
    foreach ($sp_schedIds as $sid) {
        $sval = DB::select("SELECT COALESCE(logicware_paid_amount, amount_paid, amount, 0) as val FROM payment_schedules WHERE schedule_id = ?", [$sid->schedule_id]);
        $spTotal += $sval[0]->val ?? 0;
    }
    $deposit = $r->deposit ?? 0;
    $calcTotal = $pp + $spTotal + $deposit;
    
    $realPrice = $r->precio_total_real ?? ($r->total_price + $r->bfh);
    $threshold = round($realPrice * 0.05, 2);
    $meets = $calcTotal >= $threshold ? 'YES' : 'NO';
    
    echo "Contract {$r->contract_number}: types=[{$r->sched_types}] sched={$r->sched_count} paid={$r->paid_count}\n";
    echo "  total_price={$r->total_price} bfh={$r->bfh} lft_real={$r->precio_total_real} realPrice={$realPrice} threshold_5%={$threshold}\n";
    echo "  pp={$pp} sp={$spTotal} rd={$deposit} calcTotal={$calcTotal} meets5%: {$meets}\n";
    echo "  sched_paid_raw={$r->sched_paid} payment_paid_raw={$r->payments_paid}\n\n";
}

// Now check: are there paid schedules being excluded by the NOT IN clause improperly?
echo "=== CHECK: schedules with schedule_id IN payments but status=pagado ===\n";
echo "(These are correctly counted via pp, not sp)\n";
$overlap = DB::select("
    SELECT ps.schedule_id, ps.contract_id, ps.status, ps.type,
           COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0) as sched_val,
           (SELECT SUM(p.amount) FROM payments p WHERE p.schedule_id = ps.schedule_id) as pay_val
    FROM payment_schedules ps
    WHERE ps.status = 'pagado'
    AND ps.schedule_id IN (SELECT p.schedule_id FROM payments p WHERE p.schedule_id IS NOT NULL)
    AND ps.deleted_at IS NULL
");
echo "Count: " . count($overlap) . "\n";
foreach ($overlap as $r) {
    echo "  sched_id={$r->schedule_id} contract={$r->contract_id} type={$r->type} sched_val={$r->sched_val} pay_val={$r->pay_val}\n";
}

// Check for payments with NULL schedule_id (direct contract payments)
echo "\n=== PAYMENTS WITH NULL schedule_id (direct payments) ===\n";
$directPayments = DB::select("SELECT p.*, c.contract_number, c.sale_type FROM payments p LEFT JOIN contracts c ON p.contract_id = c.contract_id WHERE p.schedule_id IS NULL LIMIT 20");
echo "Count: " . count($directPayments) . "\n";
foreach ($directPayments as $r) {
    echo "  pay_id={$r->payment_id} contract={$r->contract_number} [{$r->sale_type}] amount={$r->amount} method={$r->method} date={$r->payment_date}\n";
}

// Check the exact 5% report query output count
echo "\n=== EXACT 5% REPORT QUERY RESULT COUNT ===\n";
// Replicate exactly the buildFivePercentQuery
$result = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as five_percent_threshold,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= five_percent_threshold
    ) t
");
echo "5% report returns: {$result[0]->cnt} contracts\n";

// Now check if lot_financial_templates join is causing issues
echo "\n=== CONTRACTS WITHOUT lot_financial_templates ===\n";
$noLft = DB::select("
    SELECT COUNT(*) as cnt
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente' AND lft.lot_id IS NULL
");
echo "Vigente contracts without lot_financial_templates: {$noLft[0]->cnt}\n";

// Check if there's a pattern with contracts.lot_id being NULL
echo "\n=== CONTRACTS WITH NULL lot_id ===\n";
$nullLot = DB::select("SELECT COUNT(*) as cnt FROM contracts WHERE status='vigente' AND lot_id IS NULL");
echo "Vigente contracts with NULL lot_id: {$nullLot[0]->cnt}\n";
$nullLotHasReserv = DB::select("SELECT COUNT(*) as cnt FROM contracts c WHERE c.status='vigente' AND c.lot_id IS NULL AND c.reservation_id IS NOT NULL AND EXISTS (SELECT 1 FROM reservations r WHERE r.reservation_id = c.reservation_id AND r.lot_id IS NOT NULL)");
echo "  ...but have reservation with lot_id: {$nullLotHasReserv[0]->cnt}\n";
