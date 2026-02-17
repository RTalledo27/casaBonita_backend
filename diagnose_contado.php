<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CONTRATOS AL CONTADO (1 sola cuota en cronograma) ===\n\n";

// Find contracts that have only 1 payment schedule (or only 'inicial' type)
$contado = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price, c.sale_type, c.status,
        ps_count.total_cuotas,
        ps_count.tipos,
        COALESCE(sp.total_paid, 0) as total_paid,
        COALESCE(lft.precio_contado, 0) as precio_contado,
        COALESCE(lft.precio_venta, 0) as precio_venta,
        COALESCE(lft.precio_total_real, 0) as precio_total_real,
        COALESCE(lft.bono_techo_propio, 0) as bono
    FROM contracts c
    JOIN (
        SELECT contract_id, COUNT(*) as total_cuotas, GROUP_CONCAT(DISTINCT type) as tipos
        FROM payment_schedules
        WHERE deleted_at IS NULL
        GROUP BY contract_id
    ) ps_count ON ps_count.contract_id = c.contract_id
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    LEFT JOIN (
        SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid
        FROM payment_schedules WHERE status = 'pagado' AND deleted_at IS NULL
        GROUP BY contract_id
    ) sp ON sp.contract_id = c.contract_id
    WHERE c.status = 'vigente' AND ps_count.total_cuotas <= 2
    ORDER BY ps_count.total_cuotas ASC, c.contract_number
");

echo "Total contratos con ≤2 cuotas: " . count($contado) . "\n\n";

foreach ($contado as $c) {
    $pctContado = $c->precio_contado > 0 ? round($c->total_paid / $c->precio_contado * 100, 2) : 0;
    $pctPTR = $c->precio_total_real > 0 ? round($c->total_paid / $c->precio_total_real * 100, 2) : 0;
    $threshold5 = round($c->precio_contado * 0.05, 2);
    $reaches5 = $c->total_paid >= $threshold5 ? '✓ SÍ' : '✗ NO';
    echo "#{$c->contract_number} | cuotas={$c->total_cuotas} ({$c->tipos}) | pagado=S/{$c->total_paid} | p.contado=S/{$c->precio_contado} | 5%=S/{$threshold5} | {$reaches5}\n";
}

// Also check: contracts with only 'inicial' type schedules
echo "\n\n=== CONTRATOS QUE SOLO TIENEN CUOTA TIPO 'inicial' ===\n";
$soloInicial = DB::select("
    SELECT c.contract_id, c.contract_number, c.total_price,
        ps_info.total_cuotas, ps_info.tipos, ps_info.statuses,
        ps_info.monto_total, ps_info.monto_pagado,
        COALESCE(lft.precio_contado, 0) as precio_contado,
        COALESCE(lft.precio_venta, 0) as precio_venta
    FROM contracts c
    JOIN (
        SELECT contract_id, COUNT(*) as total_cuotas, 
            GROUP_CONCAT(DISTINCT type) as tipos,
            GROUP_CONCAT(DISTINCT status) as statuses,
            SUM(amount) as monto_total,
            SUM(COALESCE(logicware_paid_amount, amount_paid, 0)) as monto_pagado
        FROM payment_schedules
        WHERE deleted_at IS NULL
        GROUP BY contract_id
        HAVING tipos = 'inicial'
    ) ps_info ON ps_info.contract_id = c.contract_id
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.status = 'vigente'
    ORDER BY c.contract_number
");

echo "Total: " . count($soloInicial) . "\n\n";
foreach ($soloInicial as $s) {
    echo "#{$s->contract_number} | cuotas={$s->total_cuotas} | estados={$s->statuses} | monto=S/{$s->monto_total} | pagado=S/{$s->monto_pagado} | p.contado=S/{$s->precio_contado}\n";
}

// Now check the specific contract from the screenshot
echo "\n\n=== CONTRATO 202601-000001344 (del screenshot) ===\n";
$specific = DB::select("
    SELECT c.*, 
        COALESCE(lft.precio_contado, 0) as lft_precio_contado,
        COALESCE(lft.precio_venta, 0) as lft_precio_venta,
        COALESCE(lft.precio_total_real, 0) as lft_ptr
    FROM contracts c
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    WHERE c.contract_number = '202601-000001344'
");
if (count($specific) > 0) {
    $s = $specific[0];
    echo "  contract_id={$s->contract_id}, status={$s->status}, sale_type={$s->sale_type}\n";
    echo "  total_price=S/{$s->total_price}, bfh=S/{$s->bfh}\n";
    echo "  LFT: precio_venta=S/{$s->lft_precio_venta}, precio_contado=S/{$s->lft_precio_contado}, ptr=S/{$s->lft_ptr}\n";
    
    $schedules = DB::select("SELECT * FROM payment_schedules WHERE contract_id = ? AND deleted_at IS NULL", [$s->contract_id]);
    echo "  Cuotas:\n";
    foreach ($schedules as $sch) {
        echo "    #{$sch->installment_number} {$sch->type} | monto=S/{$sch->amount} | pagado=S/{$sch->logicware_paid_amount} | status={$sch->status}\n";
    }
}

// Summary: how many 'contado' contracts are NOT in the current 5% report
echo "\n\n=== RESUMEN: Contratos 'al contado' y el reporte 5% ===\n";
$summary = DB::select("
    SELECT 
        COUNT(*) as total_contado,
        SUM(CASE WHEN sp.total_paid > 0 THEN 1 ELSE 0 END) as con_pago,
        SUM(CASE WHEN sp.total_paid >= ROUND(COALESCE(lft.precio_total_real, c.total_price) * 0.05, 2) THEN 1 ELSE 0 END) as alcanza_5pct_ptr,
        SUM(CASE WHEN sp.total_paid >= ROUND(COALESCE(lft.precio_contado, c.total_price) * 0.05, 2) THEN 1 ELSE 0 END) as alcanza_5pct_contado,
        SUM(CASE WHEN sp.total_paid >= ROUND(c.total_price * 0.05, 2) THEN 1 ELSE 0 END) as alcanza_5pct_tp
    FROM contracts c
    JOIN (
        SELECT contract_id, COUNT(*) as total_cuotas, GROUP_CONCAT(DISTINCT type) as tipos
        FROM payment_schedules WHERE deleted_at IS NULL
        GROUP BY contract_id
    ) ps_count ON ps_count.contract_id = c.contract_id AND ps_count.total_cuotas <= 2
    LEFT JOIN lots l ON l.lot_id = COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))
    LEFT JOIN lot_financial_templates lft ON lft.lot_id = l.lot_id
    LEFT JOIN (
        SELECT contract_id, SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid
        FROM payment_schedules WHERE status = 'pagado' AND deleted_at IS NULL
        GROUP BY contract_id
    ) sp ON sp.contract_id = c.contract_id
    WHERE c.status = 'vigente'
");
$r = $summary[0];
echo "  Total contratos 'al contado' (≤2 cuotas): {$r->total_contado}\n";
echo "  Con algún pago: {$r->con_pago}\n";
echo "  Alcanzan 5% sobre precio_total_real (actual): {$r->alcanza_5pct_ptr}\n";
echo "  Alcanzan 5% sobre precio_contado: {$r->alcanza_5pct_contado}\n";
echo "  Alcanzan 5% sobre total_price: {$r->alcanza_5pct_tp}\n";

echo "\n=== FIN ===\n";
