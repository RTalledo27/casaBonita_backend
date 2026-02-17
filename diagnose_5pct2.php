<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== PAYMENTS TABLE COLUMNS ===\n";
echo implode(', ', Schema::getColumnListing('payments'))."\n\n";

echo "=== PAYMENT_SCHEDULES TABLE COLUMNS ===\n";
echo implode(', ', Schema::getColumnListing('payment_schedules'))."\n\n";

echo "=== CONTRACTS TABLE COLUMNS ===\n";
echo implode(', ', Schema::getColumnListing('contracts'))."\n\n";

echo "=== CONTRACTS TABLE - sale_type distribution ===\n";
$dist = DB::select("SELECT sale_type, COUNT(*) as cnt, SUM(CASE WHEN status='vigente' THEN 1 ELSE 0 END) as vigente FROM contracts GROUP BY sale_type");
foreach ($dist as $r) echo "{$r->sale_type} => total: {$r->cnt}, vigente: {$r->vigente}\n";

echo "\n=== SCHEDULE TYPE distribution ===\n";
$typeDist = DB::select("SELECT type, status, COUNT(*) as cnt FROM payment_schedules GROUP BY type, status ORDER BY type, status");
foreach ($typeDist as $r) echo "type={$r->type} status={$r->status}: count={$r->cnt}\n";

echo "\n=== CONTRACTS WITH ONLY 'inicial' schedule type (potential contado) ===\n";
$onlyInicial = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price,
        (SELECT COUNT(*) FROM payment_schedules WHERE contract_id = c.contract_id) as sched_count,
        (SELECT GROUP_CONCAT(DISTINCT type) FROM payment_schedules WHERE contract_id = c.contract_id) as types,
        (SELECT SUM(amount) FROM payments WHERE contract_id = c.contract_id) as payments_total,
        (SELECT SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) FROM payment_schedules WHERE contract_id = c.contract_id AND status='pagado') as sched_paid
    FROM contracts c
    WHERE c.status = 'vigente'
    HAVING types = 'inicial' OR sched_count <= 2
    ORDER BY c.contract_id
    LIMIT 30
");
echo "Found: " . count($onlyInicial) . "\n";
foreach ($onlyInicial as $r) {
    echo "  {$r->contract_number}: sched={$r->sched_count} types=[{$r->types}] payments={$r->payments_total} sched_paid={$r->sched_paid} price={$r->total_price}\n";
}

echo "\n=== PAYMENTS WITH NULL contract_id ===\n";
$orphans = DB::select("
    SELECT p.payment_id, p.schedule_id, p.contract_id, p.amount, p.method,
           ps.contract_id as schedule_contract_id
    FROM payments p
    LEFT JOIN payment_schedules ps ON p.schedule_id = ps.schedule_id
    WHERE p.contract_id IS NULL
    LIMIT 20
");
echo "Count: " . count($orphans) . "\n";
foreach ($orphans as $r) {
    echo "  pay_id={$r->payment_id} amount={$r->amount} sched_id={$r->schedule_id} sched_contract={$r->schedule_contract_id}\n";
}

echo "\n=== CONTRACTS WHERE pp + sp + rd DISAGREE ===\n";
echo "(Looking for contracts that have paid schedules but the 5% query sees 0)\n";
$disagree = DB::select("
    SELECT 
        c.contract_id, c.contract_number, c.total_price, c.sale_type,
        COALESCE(pp.total_paid, 0) as pp_paid,
        COALESCE(sp.total_paid, 0) as sp_paid,
        COALESCE(rd.deposit, 0) as rd_deposit,
        (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as calc_total,
        (SELECT SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.status='pagado') as real_sched_paid,
        (SELECT SUM(amount) FROM payments p WHERE p.contract_id = c.contract_id) as real_payments,
        ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold_5pct
    FROM contracts c
    LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
    LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
    LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    HAVING calc_total < (real_sched_paid + COALESCE(real_payments, 0))
    ORDER BY c.contract_id
    LIMIT 30
");
echo "Contracts with undercounted payments: " . count($disagree) . "\n";
foreach ($disagree as $r) {
    echo "  {$r->contract_number} [{$r->sale_type}]: 5%calc_total={$r->calc_total} (pp={$r->pp_paid} sp={$r->sp_paid} rd={$r->rd_deposit}) vs real_sched_paid={$r->real_sched_paid} real_payments={$r->real_payments} threshold={$r->threshold_5pct}\n";
}

echo "\n=== DOUBLE COUNTING CHECK: schedules that have BOTH payments rows AND are counted in sp ===\n";
$doubleCheck = DB::select("
    SELECT ps.schedule_id, ps.contract_id, ps.amount, ps.status, ps.type,
           COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0) as sched_value,
           (SELECT SUM(p.amount) FROM payments p WHERE p.schedule_id = ps.schedule_id) as payment_sum,
           (SELECT COUNT(*) FROM payments p WHERE p.schedule_id = ps.schedule_id) as payment_count
    FROM payment_schedules ps
    WHERE ps.status = 'pagado'
    AND ps.schedule_id IN (SELECT p.schedule_id FROM payments p WHERE p.schedule_id IS NOT NULL)
    LIMIT 20
");
echo "Schedules that are 'pagado' AND have payments (correctly excluded from sp): " . count($doubleCheck) . "\n";
foreach (array_slice($doubleCheck, 0, 5) as $r) {
    echo "  sched_id={$r->schedule_id} contract={$r->contract_id} type={$r->type} sched_value={$r->sched_value} payment_sum={$r->payment_sum}\n";
}

echo "\n=== PAID SCHEDULES WITH NO LINKED PAYMENT = sp source ===\n";
$spOnly = DB::select("
    SELECT ps.schedule_id, ps.contract_id, ps.amount, ps.amount_paid, ps.logicware_paid_amount, ps.status, ps.type,
           c.contract_number, c.sale_type,
           COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0) as contribution
    FROM payment_schedules ps
    JOIN contracts c ON ps.contract_id = c.contract_id
    WHERE ps.status = 'pagado'
    AND ps.schedule_id NOT IN (SELECT p.schedule_id FROM payments p WHERE p.schedule_id IS NOT NULL)
    AND c.status = 'vigente'
    ORDER BY ps.contract_id
    LIMIT 30
");
echo "Count: " . count($spOnly) . "\n";
foreach ($spOnly as $r) {
    echo "  sched_id={$r->schedule_id} contract={$r->contract_number} [{$r->sale_type}] type={$r->type} amount={$r->amount} paid={$r->amount_paid} logicware={$r->logicware_paid_amount} contribution={$r->contribution}\n";
}

echo "\n=== SAMPLE: Vigente contracts that DO NOT appear in 5% result ===\n";
$notIn5 = DB::select("
    SELECT 
        c.contract_id, c.contract_number, c.total_price, c.sale_type,
        COALESCE(c.bfh, 0) as bfh,
        COALESCE(pp.total_paid, 0) as pp_paid,
        COALESCE(sp.total_paid, 0) as sp_paid,
        COALESCE(rd.deposit, 0) as rd_deposit,
        (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as calc_total,
        ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
    FROM contracts c
    LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
    LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
    LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    HAVING calc_total > 0 AND calc_total < threshold
    ORDER BY calc_total DESC
    LIMIT 15
");
echo "Contracts with payments but below 5% threshold: " . count($notIn5) . "\n";
foreach ($notIn5 as $r) {
    echo "  {$r->contract_number} [{$r->sale_type}]: price={$r->total_price} bfh={$r->bfh} paid={$r->calc_total} (pp={$r->pp_paid} sp={$r->sp_paid} rd={$r->rd_deposit}) threshold={$r->threshold}\n";
}

echo "\n=== TOTAL SUMMARY ===\n";
$summary = DB::select("
    SELECT 
        COUNT(*) as total_vigente,
        SUM(CASE WHEN (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) > 0 THEN 1 ELSE 0 END) as with_any_payment,
        SUM(CASE WHEN (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) >= ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) THEN 1 ELSE 0 END) as above_5pct
    FROM contracts c
    LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
    LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
    LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
    WHERE c.status = 'vigente' AND c.total_price > 0
");
foreach ($summary as $r) {
    echo "Total vigente contracts: {$r->total_vigente}\n";
    echo "With any payment: {$r->with_any_payment}\n";
    echo "Above 5% threshold: {$r->above_5pct}\n";
}
