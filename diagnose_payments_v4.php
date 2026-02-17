<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO PAGOS AL CONTADO v4 ===\n\n";

// Check ALL columns of payments table
echo "--- Columnas de payments ---\n";
$cols = DB::select("SHOW COLUMNS FROM payments");
foreach ($cols as $col) {
    echo "  {$col->Field} | {$col->Type} | {$col->Null} | {$col->Default}\n";
}

// Check ALL columns of payment_schedules table
echo "\n--- Columnas de payment_schedules ---\n";
$cols2 = DB::select("SHOW COLUMNS FROM payment_schedules");
foreach ($cols2 as $col) {
    echo "  {$col->Field} | {$col->Type} | {$col->Null} | {$col->Default}\n";
}

// Check if there's a method/payment_method field
echo "\n--- Valores de payment_method en payments ---\n";
try {
    $methods = DB::select("SELECT payment_method, COUNT(*) as cnt FROM payments GROUP BY payment_method");
    foreach ($methods as $m) {
        echo "  '{$m->payment_method}': {$m->cnt}\n";
    }
} catch (\Exception $e) {
    echo "  No existe columna payment_method\n";
}

// Check payment schedules payment_type or method fields
echo "\n--- Valores únicos de campos tipo/metodo en payment_schedules ---\n";
$typeVals = DB::select("SELECT type, COUNT(*) as cnt FROM payment_schedules GROUP BY type");
foreach ($typeVals as $t) {
    echo "  type='{$t->type}': {$t->cnt}\n";
}

// Check if there's any 'abono', 'deposito', 'cuota', 'contado', 'efectivo' fields
echo "\n--- Buscar columnas relevantes ---\n";
$allCols = DB::select("
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND (COLUMN_NAME LIKE '%method%' OR COLUMN_NAME LIKE '%tipo%' 
         OR COLUMN_NAME LIKE '%contado%' OR COLUMN_NAME LIKE '%cash%'
         OR COLUMN_NAME LIKE '%efectivo%' OR COLUMN_NAME LIKE '%abono%')
");
foreach ($allCols as $c) {
    echo "  {$c->TABLE_NAME}.{$c->COLUMN_NAME} ({$c->COLUMN_TYPE})\n";
}

// Compare: threshold using precio_total_real vs total_price vs total_price+bfh
echo "\n--- Comparación de umbral 5% ---\n";
// How many reach 5% using ONLY total_price (sin bono)?
$noBonoCount = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            COALESCE(sp.total_paid, 0) as total_paid,
            ROUND(c.total_price * 0.05, 2) as threshold
        FROM contracts c
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
");
echo "  Con total_price (sin bono): {$noBonoCount[0]->cnt} contratos alcanzan 5%\n";

// Using total_price + bfh
$bfhCount = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            COALESCE(sp.total_paid, 0) as total_paid,
            ROUND((c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
        FROM contracts c
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
");
echo "  Con total_price + bfh: {$bfhCount[0]->cnt} contratos alcanzan 5%\n";

// Using precio_total_real from lot_financial_templates
$ptrCount = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            COALESCE(sp.total_paid, 0) as total_paid,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
");
echo "  Con precio_total_real (con bono): {$ptrCount[0]->cnt} contratos alcanzan 5%\n";

// Show sample contracts that would qualify with total_price but NOT with precio_total_real
echo "\n--- Contratos que SÍ alcanzan 5% con total_price pero NO con precio_total_real ---\n";
$diff = DB::select("
    SELECT sub.contract_id, sub.contract_number, sub.total_paid, sub.threshold_tp, sub.threshold_ptr FROM (
        SELECT c.contract_id, c.contract_number,
            COALESCE(sp.total_paid, 0) as total_paid,
            ROUND(c.total_price * 0.05, 2) as threshold_tp,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold_ptr
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        WHERE c.status = 'vigente' AND c.total_price > 0
    ) sub
    WHERE sub.total_paid >= sub.threshold_tp AND sub.total_paid < sub.threshold_ptr
    LIMIT 10
");
echo "  Diferencia: " . count($diff) . " contratos\n";
foreach ($diff as $d) {
    echo "  #{$d->contract_number}: pagado=S/{$d->total_paid}, 5%_tp=S/{$d->threshold_tp}, 5%_ptr=S/{$d->threshold_ptr}\n";
}

// Check: Is total_price the same as lft.precio_venta?
echo "\n--- Comparar total_price vs precio_venta ---\n";
$priceComp = DB::select("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN ABS(c.total_price - lft.precio_venta) < 1 THEN 1 ELSE 0 END) as same,
        SUM(CASE WHEN ABS(c.total_price - lft.precio_venta) >= 1 THEN 1 ELSE 0 END) as diff,
        AVG(c.total_price) as avg_tp,
        AVG(lft.precio_venta) as avg_pv,
        AVG(lft.precio_total_real) as avg_ptr
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente'
");
echo "  Total: {$priceComp[0]->total}\n";
echo "  total_price ≈ precio_venta: {$priceComp[0]->same}\n";
echo "  total_price ≠ precio_venta: {$priceComp[0]->diff}\n";
echo "  Promedio total_price: S/{$priceComp[0]->avg_tp}\n";
echo "  Promedio precio_venta: S/{$priceComp[0]->avg_pv}\n";
echo "  Promedio precio_total_real: S/{$priceComp[0]->avg_ptr}\n";

// Top 10 contracts with most paid amount
echo "\n--- Top 10 contratos por monto pagado ---\n";
$top10 = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price,
        COALESCE(lft.precio_total_real, c.total_price) as ptr,
        COALESCE(sp.total_paid, 0) as total_paid,
        ROUND(COALESCE(sp.total_paid, 0) / NULLIF(c.total_price, 0) * 100, 2) as pct_tp,
        ROUND(COALESCE(sp.total_paid, 0) / NULLIF(COALESCE(lft.precio_total_real, c.total_price), 0) * 100, 2) as pct_ptr
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    ORDER BY total_paid DESC
    LIMIT 10
");
foreach ($top10 as $t) {
    echo "  #{$t->contract_number}: pagado=S/{$t->total_paid}, total_price=S/{$t->total_price}, ptr=S/{$t->ptr}, %tp={$t->pct_tp}%, %ptr={$t->pct_ptr}%\n";
}

echo "\n=== FIN v4 ===\n";
