<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Check logicware_payments table
echo "=== LOGICWARE_PAYMENTS TABLE ===\n";
if (Schema::hasTable('logicware_payments')) {
    $cols = Schema::getColumnListing('logicware_payments');
    echo "Columns: " . implode(', ', $cols) . "\n";
    $cnt = DB::select("SELECT COUNT(*) as cnt FROM logicware_payments");
    echo "Total rows: {$cnt[0]->cnt}\n";
    
    // Check how many have schedule_id vs direct
    $withSched = DB::select("SELECT COUNT(*) as cnt FROM logicware_payments WHERE schedule_id IS NOT NULL");
    $withoutSched = DB::select("SELECT COUNT(*) as cnt FROM logicware_payments WHERE schedule_id IS NULL");
    echo "With schedule_id: {$withSched[0]->cnt}\n";
    echo "Without schedule_id: {$withoutSched[0]->cnt}\n";
    
    echo "(logicware_payments table is empty - 0 rows)\n";
} else {
    echo "Table does not exist\n";
}

// Check customer_payments table
echo "\n=== CUSTOMER_PAYMENTS TABLE ===\n";
if (Schema::hasTable('customer_payments')) {
    $cols = Schema::getColumnListing('customer_payments');
    echo "Columns: " . implode(', ', $cols) . "\n";
    $cnt = DB::select("SELECT COUNT(*) as cnt FROM customer_payments");
    echo "Total rows: {$cnt[0]->cnt}\n";
    
    $sample = DB::select("SELECT * FROM customer_payments LIMIT 5");
    foreach ($sample as $r) {
        $props = (array)$r;
        echo "  ";
        foreach ($props as $k => $v) {
            if ($v !== null) echo "{$k}={$v} ";
        }
        echo "\n";
    }
} else {
    echo "Table does not exist\n";
}

// THE KEY QUESTION: Are there payment_schedules with amount_paid > 0 but status != 'pagado'?
echo "\n=== SCHEDULES WITH amount_paid > 0 BUT NOT status=pagado ===\n";
$partialPaid = DB::select("
    SELECT ps.schedule_id, ps.contract_id, ps.amount, ps.amount_paid, ps.logicware_paid_amount, ps.status, ps.type,
           c.contract_number
    FROM payment_schedules ps
    JOIN contracts c ON ps.contract_id = c.contract_id
    WHERE (ps.amount_paid > 0 OR ps.logicware_paid_amount > 0) AND ps.status != 'pagado'
    AND ps.deleted_at IS NULL
    LIMIT 20
");
echo "Count: " . count($partialPaid) . "\n";
foreach ($partialPaid as $r) {
    echo "  sched={$r->schedule_id} contract={$r->contract_number} type={$r->type} status={$r->status} amount={$r->amount} paid={$r->amount_paid} logicware={$r->logicware_paid_amount}\n";
}

// Also check: total_paid using ONLY sp (which is where most data lives) broken down by type
echo "\n=== PAID SCHEDULES BREAKDOWN BY TYPE ===\n";
$byType = DB::select("
    SELECT ps.type, COUNT(*) as cnt,
        SUM(COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0)) as total_amount,
        COUNT(DISTINCT ps.contract_id) as contracts
    FROM payment_schedules ps
    WHERE ps.status = 'pagado' AND ps.deleted_at IS NULL
    GROUP BY ps.type
    ORDER BY ps.type
");
foreach ($byType as $r) {
    echo "  type={$r->type}: count={$r->cnt} contracts={$r->contracts} total={$r->total_amount}\n";
}

// Check: how many contado contracts are in the 5% report OUTPUT
echo "\n=== CONTADO-LIKE CONTRACTS IN 5% REPORT ===\n";
$contadoIn5 = DB::select("
    SELECT sub.* FROM (
        SELECT c.contract_id, c.contract_number, c.total_price, c.down_payment, c.financing_amount,
            (SELECT GROUP_CONCAT(DISTINCT ps.type) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL) as sched_types,
            (SELECT COUNT(*) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL) as sched_count,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid,
            CASE WHEN c.financing_amount = 0 OR c.financing_amount IS NULL THEN 'contado' ELSE 'financed' END as real_type
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
    WHERE sub.real_type = 'contado'
    ORDER BY sub.contract_number
");
echo "Contado contracts in 5% report: " . count($contadoIn5) . "\n";
foreach ($contadoIn5 as $r) {
    echo "  {$r->contract_number}: types=[{$r->sched_types}] sched={$r->sched_count} financing={$r->financing_amount} total_paid={$r->total_paid} threshold={$r->threshold}\n";
}

// Total contado-like contracts
$totalContado = DB::select("
    SELECT COUNT(*) as cnt FROM contracts WHERE status='vigente' AND (financing_amount = 0 OR financing_amount IS NULL) AND total_price > 0
");
echo "\nTotal contado-like contracts (financing=0): {$totalContado[0]->cnt}\n";

// Which of those are NOT in the 5% report?
$contadoNotIn5 = DB::select("
    SELECT sub.* FROM (
        SELECT c.contract_id, c.contract_number, c.total_price, c.financing_amount, c.down_payment,
            (SELECT GROUP_CONCAT(DISTINCT ps.type) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL) as sched_types,
            (SELECT COUNT(*) FROM payment_schedules ps WHERE ps.contract_id = c.contract_id AND ps.deleted_at IS NULL AND ps.status='pagado') as paid_sched,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        AND (c.financing_amount = 0 OR c.financing_amount IS NULL)
        HAVING total_paid < threshold OR total_paid = 0
    ) sub
    ORDER BY sub.total_paid DESC
");
echo "\nContado contracts NOT in 5% report: " . count($contadoNotIn5) . "\n";
foreach ($contadoNotIn5 as $r) {
    echo "  {$r->contract_number}: financing={$r->financing_amount} down={$r->down_payment} types=[{$r->sched_types}] paid_sched={$r->paid_sched} paid={$r->total_paid} threshold={$r->threshold} price={$r->total_price}\n";
}
