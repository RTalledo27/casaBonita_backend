<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Check lot_financial_templates for duplicates
echo "=== LOT_FINANCIAL_TEMPLATES STATS ===\n";
$lftTotal = DB::select("SELECT COUNT(*) as cnt FROM lot_financial_templates");
echo "Total templates: {$lftTotal[0]->cnt}\n";
$lftLots = DB::select("SELECT COUNT(DISTINCT lot_id) as cnt FROM lot_financial_templates");
echo "Distinct lot_ids: {$lftLots[0]->cnt}\n";
$dupes = DB::select("SELECT lot_id, COUNT(*) as cnt FROM lot_financial_templates GROUP BY lot_id HAVING cnt > 1 LIMIT 10");
echo "Lots with multiple templates: " . count($dupes) . "\n";
foreach ($dupes as $d) echo "  lot_id={$d->lot_id} count={$d->cnt}\n";

echo "\n=== LOT_FINANCIAL_TEMPLATES COLUMNS ===\n";
$cols = DB::select("SHOW COLUMNS FROM lot_financial_templates");
foreach ($cols as $c) echo "  {$c->Field} ({$c->Type}) null={$c->Null} default={$c->Default}\n";

// Count with just total_price vs with lft_precio_total_real
echo "\n=== 5% COMPARISON: With vs Without lot_financial_templates ===\n";

$withoutLft = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid
        FROM contracts c
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) t
");
echo "Without lft (using c.total_price + bfh): {$withoutLft[0]->cnt}\n";

$withLft = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) t
");
echo "With lft (COALESCE precio_total_real): {$withLft[0]->cnt}\n";

// Sample of contracts that pass without lft but fail with lft
echo "\n=== CONTRACTS THAT PASS WITH total_price BUT FAIL WITH precio_total_real ===\n";
$diff = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price, COALESCE(c.bfh, 0) as bfh,
           lft.precio_total_real, lft.precio_venta,
           ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold_simple,
           ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold_lft,
           (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
    LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status='pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
    LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    HAVING total_paid >= threshold_simple AND total_paid < threshold_lft
    ORDER BY total_paid DESC
    LIMIT 15
");
echo "Found " . count($diff) . " contracts:\n";
foreach ($diff as $r) {
    echo "  {$r->contract_number}: total_price={$r->total_price} bfh={$r->bfh} lft_real={$r->precio_total_real} simple_thresh={$r->threshold_simple} lft_thresh={$r->threshold_lft} paid={$r->total_paid}\n";
}

// Check if these lft are including a LOT value (terrain value) that inflates the price
echo "\n=== SAMPLE lot_financial_templates data ===\n";
$samples = DB::select("SELECT lft.*, l.total_price as lot_price FROM lot_financial_templates lft JOIN lots l ON l.lot_id = lft.lot_id LIMIT 5");
foreach ($samples as $s) {
    echo "  lot_id={$s->lot_id}: precio_venta={$s->precio_venta} precio_total_real={$s->precio_total_real} bono={$s->bono_techo_propio} lot_price={$s->lot_price}\n";
    // Print all properties
    foreach ((array)$s as $k => $v) {
        if (!in_array($k, ['lot_id', 'precio_venta', 'precio_total_real', 'bono_techo_propio', 'lot_price'])) {
            echo "    {$k}={$v}\n";
        }
    }
}

// Key question: what is precio_total_real composed of?
echo "\n=== CONTRACT vs LFT PRICE COMPARISON ===\n";
$comparison = DB::select("
    SELECT c.contract_number, c.total_price as contract_price, c.down_payment, c.financing_amount,
           COALESCE(c.bfh, 0) as bfh, COALESCE(c.bpp, 0) as bpp,
           lft.precio_venta, lft.precio_total_real, lft.bono_techo_propio,
           lft.precio_contado
    FROM contracts c
    JOIN lots l ON l.lot_id = c.lot_id
    JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente'
    ORDER BY (lft.precio_total_real - c.total_price) DESC
    LIMIT 10
");
foreach ($comparison as $r) {
    echo "  {$r->contract_number}: contract_price={$r->contract_price} down={$r->down_payment} financing={$r->financing_amount} bfh={$r->bfh} bpp={$r->bpp} | lft: venta={$r->precio_venta} real={$r->precio_total_real} bono={$r->bono_techo_propio} contado={$r->precio_contado}\n";
}
