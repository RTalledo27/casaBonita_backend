<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO PAGOS AL CONTADO v3 ===\n\n";

// Key finding from v2: ALL 819 contracts are sale_type='financed', 0 are 'cash'
// Let me check if there are non-vigente cash contracts
echo "--- Contratos por sale_type y status ---\n";
$types = DB::select("SELECT sale_type, status, COUNT(*) as cnt FROM contracts GROUP BY sale_type, status ORDER BY sale_type, status");
foreach ($types as $t) {
    echo "  sale_type='{$t->sale_type}', status='{$t->status}': {$t->cnt}\n";
}

// Check bono_bpp schedules - are these supposed to count as payments?
echo "\n--- bono_bpp schedules detail ---\n";
$bpp = DB::select("
    SELECT ps.contract_id, c.contract_number, ps.amount, ps.status, ps.amount_paid, ps.logicware_paid_amount
    FROM payment_schedules ps
    JOIN contracts c ON c.contract_id = ps.contract_id
    WHERE ps.type = 'bono_bpp'
    LIMIT 10
");
foreach ($bpp as $b) {
    echo "  #{$b->contract_number}: amount=S/{$b->amount}, status={$b->status}, paid={$b->amount_paid}, lw_paid={$b->logicware_paid_amount}\n";
}

// Check: schedules with amount_paid > 0 but status != 'pagado'
echo "\n--- Schedules con amount_paid > 0 pero NO pagado ---\n";
$notPagado = DB::select("
    SELECT type, status, COUNT(*) as cnt, SUM(amount_paid) as total_paid
    FROM payment_schedules
    WHERE (amount_paid > 0 OR logicware_paid_amount > 0) AND status != 'pagado'
    GROUP BY type, status
");
if (count($notPagado) === 0) {
    echo "  Ninguno - todos los que tienen monto pagado tienen status='pagado'\n";
} else {
    foreach ($notPagado as $n) {
        echo "  type='{$n->type}', status='{$n->status}': cnt={$n->cnt}, total_paid=S/{$n->total_paid}\n";
    }
}

// Current 5% report count
echo "\n--- Reporte 5% actual (via query) ---\n";
$fivePercent = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
");
echo "  Contratos alcanzando 5%: {$fivePercent[0]->cnt}\n";

// Same query but counting ALL paid (without the 5% filter)  
echo "\n--- Todos los contratos vigentes con cualquier pago ---\n";
$allWithPay = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid
        FROM contracts c
        LEFT JOIN (SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL) GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid > 0
    ) sub
");
echo "  Contratos con algún pago: {$allWithPay[0]->cnt}\n";

// Check: from payment_schedules directly, how many contracts have paid > 5%
echo "\n--- Verificación alternativa: schedules SIN exclusión de payments ---\n";
$altCheck = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT ps.contract_id,
            SUM(COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0)) as total_paid,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
        FROM payment_schedules ps
        JOIN contracts c ON c.contract_id = ps.contract_id
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        WHERE ps.status = 'pagado' AND c.status = 'vigente' AND c.total_price > 0
        GROUP BY ps.contract_id, threshold
        HAVING total_paid >= threshold
    ) sub
");
echo "  Contratos que alcanzan 5% (solo schedules): {$altCheck[0]->cnt}\n";

// Check: including reservation deposits
echo "\n--- Verificación con reservations ---\n";
$withReserv = DB::select("
    SELECT COUNT(*) as cnt FROM (
        SELECT c.contract_id,
            (COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid FROM payment_schedules WHERE status = 'pagado' GROUP BY contract_id) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
");
echo "  Contratos que alcanzan 5% (schedules + reservations, no exclusion): {$withReserv[0]->cnt}\n";

// Check reservations
echo "\n--- Reservation deposits ---\n";
$resDep = DB::select("
    SELECT COUNT(*) as cnt, SUM(COALESCE(deposit_amount, 0)) as total
    FROM reservations
    WHERE deposit_paid_at IS NOT NULL AND deposit_amount > 0
");
echo "  Reservaciones con depósito pagado: {$resDep[0]->cnt}, total=S/{$resDep[0]->total}\n";

// Contracts with reservations that have deposits but contract NOT in 5% report
echo "\n--- Contracts with reservation deposits ---\n";
$resContracts = DB::select("
    SELECT c.contract_id, c.contract_number, r.deposit_amount,
        COALESCE(lft.precio_total_real, c.total_price) as precio_total_real
    FROM contracts c
    JOIN reservations r ON r.reservation_id = c.reservation_id
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, r.lot_id)
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente' AND r.deposit_paid_at IS NOT NULL AND r.deposit_amount > 0
    LIMIT 10
");
echo "  Contratos con depósito de reservación:\n";
foreach ($resContracts as $rc) {
    echo "  #{$rc->contract_number}: deposito=S/{$rc->deposit_amount}, total_real=S/{$rc->precio_total_real}\n";
}

// Final: What does 'pago al contado' look like in the DB?
echo "\n--- Buscar 'contado' en toda la BD ---\n";
$tables = ['payments', 'payment_schedules', 'logicware_payments', 'contracts', 'reservations'];
foreach ($tables as $table) {
    try {
        $cols = DB::select("SHOW COLUMNS FROM {$table}");
        foreach ($cols as $col) {
            if (in_array($col->Type, ['text', 'varchar(255)', 'varchar(100)', 'varchar(50)', 'varchar(60)']) || strpos($col->Type, 'varchar') !== false || strpos($col->Type, 'text') !== false) {
                $cnt = DB::table($table)->where($col->Field, 'like', '%contado%')->count();
                if ($cnt > 0) {
                    echo "  {$table}.{$col->Field} contiene 'contado': {$cnt} registros\n";
                }
            }
        }
    } catch (\Exception $e) {}
}

echo "\n=== FIN v3 ===\n";
