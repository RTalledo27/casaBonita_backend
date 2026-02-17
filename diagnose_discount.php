<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO: Descuentos y 5% ===\n\n";

// 1. Contrato 202512-000000958 (screenshot)
echo "--- Contrato 202512-000000958 ---\n";
$contract = DB::table('contracts')
    ->where('contract_number', '202512-000000958')
    ->first();
if ($contract) {
    foreach ((array)$contract as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
}

// 2. lot_financial_template for that lot
echo "\n--- lot_financial_template para lot_id={$contract->lot_id} ---\n";
$lft = DB::table('lot_financial_templates')
    ->where('lot_id', $contract->lot_id)
    ->first();
if ($lft) {
    foreach ((array)$lft as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
}

// 3. Payment schedules for this contract
echo "\n--- payment_schedules del contrato ---\n";
$schedules = DB::table('payment_schedules')
    ->where('contract_id', $contract->contract_id)
    ->orderBy('installment_number')
    ->get();
$totalAmount = 0;
$totalPaid = 0;
foreach ($schedules as $s) {
    $totalAmount += (float) $s->amount;
    $totalPaid += (float) max($s->amount_paid ?? 0, $s->logicware_paid_amount ?? 0);
    echo "  #{$s->installment_number} | type={$s->type} | status={$s->status}";
    echo " | amount=" . number_format($s->amount, 2);
    echo " | amount_paid=" . number_format($s->amount_paid ?? 0, 2);
    echo " | lw_paid=" . number_format($s->logicware_paid_amount ?? 0, 2);
    echo " | paid_date=" . ($s->paid_date ?? 'null');
    echo "\n";
}
echo "  TOTAL amount: " . number_format($totalAmount, 2) . "\n";
echo "  TOTAL paid: " . number_format($totalPaid, 2) . "\n";

// 4. Check contracts table for discount columns
echo "\n--- contracts columns ---\n";
$cols = DB::select('SHOW COLUMNS FROM contracts');
foreach ($cols as $c) {
    if (stripos($c->Field, 'disc') !== false || stripos($c->Field, 'desc') !== false 
        || stripos($c->Field, 'price') !== false || stripos($c->Field, 'total') !== false
        || stripos($c->Field, 'bono') !== false || stripos($c->Field, 'amount') !== false) {
        echo "  {$c->Field} | {$c->Type} | default={$c->Default}\n";
    }
}

// 5. Check lot_financial_templates for discount columns
echo "\n--- lot_financial_templates columns ---\n";
$cols2 = DB::select('SHOW COLUMNS FROM lot_financial_templates');
foreach ($cols2 as $c) {
    echo "  {$c->Field} | {$c->Type} | default={$c->Default}\n";
}

// 6. Check reservations for discount columns
echo "\n--- reservations columns ---\n";
$cols3 = DB::select('SHOW COLUMNS FROM reservations');
foreach ($cols3 as $c) {
    if (stripos($c->Field, 'disc') !== false || stripos($c->Field, 'desc') !== false 
        || stripos($c->Field, 'price') !== false || stripos($c->Field, 'total') !== false
        || stripos($c->Field, 'amount') !== false || stripos($c->Field, 'deposit') !== false) {
        echo "  {$c->Field} | {$c->Type} | default={$c->Default}\n";
    }
}

// 7. Contracts with discount_amount or similar
echo "\n--- Contracts with non-zero discount_amount (if exists) ---\n";
try {
    $discounted = DB::table('contracts')
        ->whereNotNull('discount_amount')
        ->where('discount_amount', '>', 0)
        ->limit(10)
        ->get(['contract_id', 'contract_number', 'total_price', 'discount_amount']);
    echo "  Count: " . $discounted->count() . "\n";
    foreach ($discounted as $d) {
        echo "  contract={$d->contract_number} | total_price=" . number_format($d->total_price ?? 0, 2) 
             . " | discount=" . number_format($d->discount_amount ?? 0, 2) . "\n";
    }
} catch (\Exception $e) {
    echo "  discount_amount column not found: " . $e->getMessage() . "\n";
}

// 8. Check lots table for discount info
echo "\n--- lots columns (price/discount related) ---\n";
$cols4 = DB::select('SHOW COLUMNS FROM lots');
foreach ($cols4 as $c) {
    if (stripos($c->Field, 'disc') !== false || stripos($c->Field, 'desc') !== false 
        || stripos($c->Field, 'price') !== false || stripos($c->Field, 'total') !== false
        || stripos($c->Field, 'area') !== false) {
        echo "  {$c->Field} | {$c->Type} | default={$c->Default}\n";
    }
}

// 9. lot for A-72
echo "\n--- Lot A-72 ---\n";
$lot = DB::table('lots')->where('lot_id', $contract->lot_id)->first();
if ($lot) {
    echo "  lot_id: {$lot->lot_id}\n";
    echo "  num_lot: {$lot->num_lot}\n";
    echo "  price: " . ($lot->price ?? 'null') . "\n";
    echo "  price_per_m2: " . ($lot->price_per_m2 ?? 'null') . "\n";
    echo "  area: " . ($lot->area ?? 'null') . "\n";
}

// 10. What's precio_venta, precio_contado, total_price for this contract
echo "\n--- Prices for this contract ---\n";
echo "  contract.total_price: " . number_format($contract->total_price ?? 0, 2) . "\n";
$precioVenta = $lft->precio_venta ?? null;
$precioContado = $lft->precio_contado ?? null;
$bono = $lft->bono_techo_propio ?? null;
echo "  lft.precio_venta: " . ($precioVenta ? number_format($precioVenta, 2) : 'null') . "\n";
echo "  lft.precio_contado: " . ($precioContado ? number_format($precioContado, 2) : 'null') . "\n";
echo "  lft.bono_techo_propio: " . ($bono ? number_format($bono, 2) : 'null') . "\n";

$precioTotalReal = (float)($precioVenta ?? 0) + (float)($bono ?? 0);
$fivePercent = $precioTotalReal * 0.05;
echo "  precio_total_real (venta+bono): " . number_format($precioTotalReal, 2) . "\n";
echo "  5% of precio_total_real: " . number_format($fivePercent, 2) . "\n";
echo "  5% of total_price: " . number_format(($contract->total_price ?? 0) * 0.05, 2) . "\n";
echo "  5% of precio_contado: " . number_format(($precioContado ?? 0) * 0.05, 2) . "\n";
echo "  Total paid vs 5% threshold: " . number_format($totalPaid, 2) . " vs " . number_format($fivePercent, 2) . "\n";

echo "\nDone.\n";
