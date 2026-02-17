<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== PAYMENTS TABLE COLUMNS ===\n";
$cols = Schema::getColumnListing('payments');
echo implode(', ', $cols)."\n\n";

echo "=== PAYMENT_SCHEDULES TABLE COLUMNS ===\n";
$cols = Schema::getColumnListing('payment_schedules');
echo implode(', ', $cols)."\n\n";

echo "=== CONTRACTS TABLE - sale_type distribution ===\n";
$dist = DB::table('contracts')
    ->select('sale_type', DB::raw('COUNT(*) as cnt'), DB::raw("SUM(CASE WHEN status='vigente' THEN 1 ELSE 0 END) as vigente"))
    ->groupBy('sale_type')
    ->get();
foreach ($dist as $r) echo "{$r->sale_type} => total: {$r->cnt}, vigente: {$r->vigente}\n";

echo "\n=== CASH CONTRACTS (vigente) - payment details ===\n";
$cashContracts = DB::select("
    SELECT 
        c.contract_id, c.contract_number, c.total_price, c.financing_type,
        (SELECT COUNT(*) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id) as schedule_count,
        (SELECT COUNT(*) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.status='pagado') as paid_schedule_count,
        (SELECT SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.status='pagado') as schedule_paid_total,
        (SELECT COUNT(*) FROM payments p WHERE p.contract_id = c.contract_id) as payment_count,
        (SELECT SUM(amount) FROM payments p WHERE p.contract_id = c.contract_id) as payment_total,
        (SELECT COUNT(*) FROM payments p WHERE p.schedule_id IN (SELECT schedule_id FROM payment_schedules ps2 WHERE ps2.contract_id = c.contract_id)) as payment_via_schedule_count,
        (SELECT deposit_amount FROM reservations r WHERE r.reservation_id = c.reservation_id AND r.deposit_paid_at IS NOT NULL) as reservation_deposit
    FROM contracts c
    WHERE c.sale_type = 'cash' AND c.status = 'vigente'
    ORDER BY c.contract_id
");
foreach ($cashContracts as $r) {
    echo "Contract {$r->contract_number} (ID:{$r->contract_id}): financing_type={$r->financing_type}\n";
    echo "  schedules={$r->schedule_count}, paid_schedules={$r->paid_schedule_count}, sched_paid_total={$r->schedule_paid_total}\n";
    echo "  payments_count={$r->payment_count}, payment_total={$r->payment_total}, payments_via_schedule={$r->payment_via_schedule_count}\n";
    echo "  reservation_deposit={$r->reservation_deposit}\n";
    echo "  total_price={$r->total_price}\n\n";
}

echo "=== CHECKING: payments WITHOUT contract_id (NULL) ===\n";
$orphanPayments = DB::select("
    SELECT p.payment_id, p.schedule_id, p.contract_id, p.amount, p.payment_date, p.method,
           ps.contract_id as schedule_contract_id,
           c.contract_number, c.sale_type
    FROM payments p
    LEFT JOIN payment_schedules ps ON p.schedule_id = ps.schedule_id
    LEFT JOIN contracts c ON ps.contract_id = c.contract_id
    WHERE p.contract_id IS NULL
    LIMIT 20
");
echo "Payments with NULL contract_id: " . count($orphanPayments) . "\n";
foreach ($orphanPayments as $r) {
    echo "  payment_id={$r->payment_id} amount={$r->amount} schedule_id={$r->schedule_id} schedule_contract_id={$r->schedule_contract_id} contract={$r->contract_number} sale_type={$r->sale_type}\n";
}

echo "\n=== CHECKING: paid schedules for cash contracts NOT IN payments ===\n";
$unlinkedSchedules = DB::select("
    SELECT ps.schedule_id, ps.contract_id, ps.amount, ps.amount_paid, ps.logicware_paid_amount, 
           ps.status, ps.type, ps.installment_number,
           c.contract_number, c.sale_type,
           (SELECT COUNT(*) FROM payments p WHERE p.schedule_id = ps.schedule_id) as linked_payments
    FROM payment_schedules ps
    JOIN contracts c ON ps.contract_id = c.contract_id
    WHERE c.sale_type = 'cash' AND c.status = 'vigente' AND ps.status = 'pagado'
    ORDER BY c.contract_id, ps.schedule_id
    LIMIT 50
");
echo "Paid schedules for cash contracts: " . count($unlinkedSchedules) . "\n";
foreach ($unlinkedSchedules as $r) {
    echo "  schedule_id={$r->schedule_id} contract={$r->contract_number} type={$r->type} #={$r->installment_number} amount={$r->amount} paid={$r->amount_paid} logicware={$r->logicware_paid_amount} linked_payments={$r->linked_payments}\n";
}

echo "\n=== 5% REPORT - ALL vigente contracts with any payment, sorted by sale_type ===\n";
$all = DB::select("
    SELECT 
        c.contract_id, c.contract_number, c.sale_type, c.total_price, c.financing_type,
        COALESCE(c.bfh, 0) as bfh,
        COALESCE(pp.total_paid, 0) as pp_paid,
        COALESCE(sp.total_paid, 0) as sp_paid,
        COALESCE(rd.deposit, 0) as rd_deposit,
        (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid,
        ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold_5pct,
        lft.precio_total_real, lft.precio_venta as lft_precio_venta
    FROM contracts c
    LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
    LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
    LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    ORDER BY c.sale_type, c.contract_number
");
$cashYes = 0; $cashNo = 0; $finYes = 0; $finNo = 0;
foreach ($all as $r) {
    $realPrice = $r->precio_total_real ?? ($r->total_price + $r->bfh);
    $thresh = round($realPrice * 0.05, 2);
    $met = $r->total_paid >= $thresh ? 'YES' : 'NO';
    if ($r->sale_type === 'cash') { $met === 'YES' ? $cashYes++ : $cashNo++; }
    else { $met === 'YES' ? $finYes++ : $finNo++; }
    
    if ($r->total_paid > 0 || $r->sale_type === 'cash') {
        echo "{$r->contract_number} [{$r->sale_type}] price={$r->total_price} bfh={$r->bfh} realPrice={$realPrice} pp={$r->pp_paid} sp={$r->sp_paid} rd={$r->rd_deposit} total={$r->total_paid} >=5%({$thresh}): {$met}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Cash: {$cashYes} meet 5%, {$cashNo} don't\n";
echo "Financed: {$finYes} meet 5%, {$finNo} don't\n";
echo "Total contracts analyzed: " . count($all) . "\n";

// Check for cash contracts with 0 total_paid that actually have schedule data
echo "\n=== CASH CONTRACTS WITH 0 TOTAL IN 5% CALC BUT HAVE SCHEDULE DATA ===\n";
$zeroCash = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price,
        (SELECT SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.status='pagado') as all_sched_paid,
        (SELECT SUM(amount) FROM payments p WHERE p.contract_id = c.contract_id) as all_payments,
        (SELECT SUM(amount) FROM payments p WHERE p.schedule_id IN (SELECT schedule_id FROM payment_schedules ps2 WHERE ps2.contract_id = c.contract_id)) as payments_via_schedule,
        (SELECT GROUP_CONCAT(ps.schedule_id) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.status='pagado') as paid_schedule_ids,
        (SELECT GROUP_CONCAT(p.schedule_id) FROM payments p WHERE p.contract_id = c.contract_id) as payment_schedule_ids
    FROM contracts c
    WHERE c.sale_type = 'cash' AND c.status = 'vigente'
    HAVING (all_sched_paid > 0 OR all_payments > 0)
    ORDER BY c.contract_id
");
foreach ($zeroCash as $r) {
    echo "Contract {$r->contract_number}: all_sched_paid={$r->all_sched_paid} all_payments={$r->all_payments} payments_via_schedule={$r->payments_via_schedule}\n";
    echo "  paid_schedule_ids: {$r->paid_schedule_ids}\n";
    echo "  payment_schedule_ids: {$r->payment_schedule_ids}\n";
}
