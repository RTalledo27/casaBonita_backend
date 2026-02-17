<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO DE PAGOS AL CONTADO ===\n\n";

// 1. Totales de payment_schedules por tipo y status
echo "--- payment_schedules por tipo y status ---\n";
$stats = DB::select("
    SELECT type, status, COUNT(*) as cnt, 
           SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_amount
    FROM payment_schedules
    GROUP BY type, status
    ORDER BY type, status
");
foreach ($stats as $s) {
    echo "  type='{$s->type}', status='{$s->status}' => cnt={$s->cnt}, total=S/{$s->total_amount}\n";
}

// 2. Check logicware_payments table
echo "\n--- logicware_payments tabla ---\n";
$lwCount = DB::table('logicware_payments')->count();
echo "  Total registros: {$lwCount}\n";
if ($lwCount > 0) {
    $lwTypes = DB::select("
        SELECT COALESCE(method, 'NULL') as method, COUNT(*) as cnt, SUM(paid_amount) as total
        FROM logicware_payments
        GROUP BY method
    ");
    foreach ($lwTypes as $lt) {
        echo "  method='{$lt->method}': cnt={$lt->cnt}, total=S/{$lt->total}\n";
    }
}

// 3. Check contracts with tipo_venta or payment_type
echo "\n--- contracts: columnas financieras ---\n";
$contractCols = DB::select("SHOW COLUMNS FROM contracts");
$financialCols = [];
foreach ($contractCols as $col) {
    $name = strtolower($col->Field);
    if (strpos($name, 'type') !== false || strpos($name, 'tipo') !== false || 
        strpos($name, 'contado') !== false || strpos($name, 'payment') !== false ||
        strpos($name, 'price') !== false || strpos($name, 'total') !== false ||
        strpos($name, 'bfh') !== false || strpos($name, 'bono') !== false) {
        $financialCols[] = $col;
        echo "  {$col->Field} ({$col->Type})\n";
    }
}

// 4. Check if contracts have tipo_venta or sale_type
echo "\n--- Tipos de contrato vigentes ---\n";
$tipoVenta = DB::select("
    SELECT COALESCE(sale_type, 'NULL') as sale_type, COUNT(*) as cnt 
    FROM contracts 
    WHERE status = 'vigente' 
    GROUP BY sale_type
");
if (count($tipoVenta) > 0) {
    foreach ($tipoVenta as $t) {
        echo "  sale_type='{$t->sale_type}': {$t->cnt}\n";
    }
} else {
    echo "  No sale_type column or no data\n";
}

// 5. Contracts where total_paid from schedules is > 0
echo "\n--- Contratos vigentes: cuanto han pagado según schedules ---\n";
$contractPaid = DB::select("
    SELECT 
        c.contract_id,
        c.contract_number,
        COALESCE(lft.precio_total_real, c.total_price) as precio_total_real,
        ROUND(COALESCE(lft.precio_total_real, c.total_price) * 0.05, 2) as threshold_5pct,
        COALESCE(SUM(
            CASE WHEN ps.status = 'pagado' 
            THEN COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0) 
            ELSE 0 END
        ), 0) as schedule_paid,
        COUNT(CASE WHEN ps.status = 'pagado' THEN 1 END) as cuotas_pagadas,
        GROUP_CONCAT(DISTINCT ps.type ORDER BY ps.type) as tipos_cuota
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    LEFT JOIN payment_schedules ps ON ps.contract_id = c.contract_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    GROUP BY c.contract_id, c.contract_number, precio_total_real, threshold_5pct
    HAVING schedule_paid > 0
    ORDER BY schedule_paid / precio_total_real DESC
    LIMIT 20
");

echo "  Top 20 contracts by % paid:\n";
foreach ($contractPaid as $c) {
    $pct = $c->precio_total_real > 0 ? round(($c->schedule_paid / $c->precio_total_real) * 100, 2) : 0;
    $reaches5 = $c->schedule_paid >= $c->threshold_5pct ? 'SI' : 'NO';
    echo "  #{$c->contract_number}: pagado=S/{$c->schedule_paid}, total_real=S/{$c->precio_total_real}, 5%=S/{$c->threshold_5pct}, actual={$pct}%, alcanza5%={$reaches5}, cuotas_pagadas={$c->cuotas_pagadas}, tipos={$c->tipos_cuota}\n";
}

// 6. Now check what the 5% report actually returns
echo "\n--- Resultado actual del reporte 5% ---\n";
$fivePercent = DB::select("
    SELECT COUNT(*) as total FROM (
        SELECT c.contract_id,
            (COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid,
            ROUND(COALESCE(lft.precio_total_real, c.total_price + COALESCE(c.bfh, 0)) * 0.05, 2) as threshold
        FROM contracts c
        LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
        LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
        LEFT JOIN (
            SELECT contract_id, SUM(amount) as total_paid FROM payments WHERE contract_id IS NOT NULL GROUP BY contract_id
        ) pp ON pp.contract_id = c.contract_id
        LEFT JOIN (
            SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid
            FROM payment_schedules 
            WHERE status = 'pagado' 
            AND schedule_id NOT IN (SELECT schedule_id FROM payments WHERE schedule_id IS NOT NULL)
            GROUP BY contract_id
        ) sp ON sp.contract_id = c.contract_id
        LEFT JOIN (
            SELECT reservation_id, COALESCE(deposit_amount, 0) as deposit FROM reservations WHERE deposit_paid_at IS NOT NULL
        ) rd ON rd.reservation_id = c.reservation_id
        WHERE c.status = 'vigente' AND c.total_price > 0
        HAVING total_paid >= threshold
    ) sub
");
echo "  Contratos que alcanzan 5%: {$fivePercent[0]->total}\n";

// 7. Contracts with paid schedules that DON'T reach 5% but are close
echo "\n--- Contratos que NO alcanzan 5% pero tienen pagos ---\n";
$notReaching = DB::select("
    SELECT c.contract_id, c.contract_number,
        COALESCE(lft.precio_total_real, c.total_price) as precio_total_real,
        COALESCE(SUM(CASE WHEN ps.status = 'pagado' THEN COALESCE(ps.logicware_paid_amount, ps.amount_paid, ps.amount, 0) ELSE 0 END), 0) as schedule_paid,
        ROUND(COALESCE(lft.precio_total_real, c.total_price) * 0.05, 2) as threshold
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    LEFT JOIN payment_schedules ps ON ps.contract_id = c.contract_id
    WHERE c.status = 'vigente' AND c.total_price > 0
    GROUP BY c.contract_id, c.contract_number, precio_total_real, threshold
    HAVING schedule_paid > 0 AND schedule_paid < threshold
    ORDER BY schedule_paid DESC
    LIMIT 10
");
echo "  Contratos con pagos pero bajo 5%:\n";
foreach ($notReaching as $c) {
    $pct = $c->precio_total_real > 0 ? round(($c->schedule_paid / $c->precio_total_real) * 100, 2) : 0;
    echo "  #{$c->contract_number}: pagado=S/{$c->schedule_paid}, total_real=S/{$c->precio_total_real}, 5%=S/{$c->threshold}, actual={$pct}%\n";
}

// 8. Check if there's double-counting exclusion issue
echo "\n--- Doble conteo check ---\n";
$schedWithPayment = DB::table('payments')->whereNotNull('schedule_id')->pluck('schedule_id')->toArray();
echo "  Schedules con pago en tabla payments: " . count($schedWithPayment) . "\n";
if (count($schedWithPayment) > 0) {
    $affectedContracts = DB::table('payment_schedules')
        ->whereIn('schedule_id', $schedWithPayment)
        ->where('status', 'pagado')
        ->pluck('contract_id')
        ->unique();
    echo "  Contratos afectados por exclusión: " . $affectedContracts->count() . "\n";
    foreach ($affectedContracts as $cid) {
        $fromPayments = DB::table('payments')->where('contract_id', $cid)->sum('amount');
        $fromSchedules = DB::table('payment_schedules')
            ->where('contract_id', $cid)
            ->where('status', 'pagado')
            ->whereNotIn('schedule_id', $schedWithPayment)
            ->sum(DB::raw('COALESCE(logicware_paid_amount, amount_paid, amount, 0)'));
        $totalBoth = $fromPayments + $fromSchedules;
        echo "  contract_id={$cid}: payments=S/{$fromPayments}, schedules(excl)=S/{$fromSchedules}, total=S/{$totalBoth}\n";
    }
}

echo "\n=== FIN ===\n";
