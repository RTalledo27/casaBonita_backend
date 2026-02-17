<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO: Descuentos en contratos ===\n\n";

// 1. How many contracts have a non-zero discount?
$withDiscount = DB::table('contracts')
    ->where('discount', '>', 0)
    ->where('status', 'vigente')
    ->count();
$total = DB::table('contracts')->where('status', 'vigente')->count();
echo "--- Contratos vigentes con descuento > 0: {$withDiscount} / {$total} ---\n";

// 2. Sample contracts with discount
echo "\n--- Muestra de contratos con descuento ---\n";
$samples = DB::table('contracts as c')
    ->where('c.discount', '>', 0)
    ->where('c.status', 'vigente')
    ->leftJoin('lot_financial_templates as lft', 'c.lot_id', '=', 'lft.lot_id')
    ->limit(15)
    ->get([
        'c.contract_id', 'c.contract_number', 'c.lot_id',
        'c.base_price', 'c.unit_price', 'c.discount', 'c.total_price',
        'c.down_payment', 'c.financing_amount', 'c.term_months',
        'lft.precio_lista', 'lft.descuento as lft_descuento', 'lft.precio_venta', 
        'lft.precio_contado', 'lft.bono_techo_propio', 'lft.precio_total_real'
    ]);

foreach ($samples as $s) {
    echo "\n  contract={$s->contract_number} | lot={$s->lot_id}\n";
    echo "    base_price=" . number_format($s->base_price ?? 0, 2);
    echo " | unit_price=" . number_format($s->unit_price ?? 0, 2);
    echo " | discount=" . number_format($s->discount ?? 0, 2);
    echo " | total_price=" . number_format($s->total_price ?? 0, 2) . "\n";
    echo "    down_payment=" . number_format($s->down_payment ?? 0, 2);
    echo " | financing=" . number_format($s->financing_amount ?? 0, 2);
    echo " | term={$s->term_months}\n";
    echo "    lft: lista=" . number_format($s->precio_lista ?? 0, 2);
    echo " | desc_lft=" . number_format($s->lft_descuento ?? 0, 2);
    echo " | venta=" . number_format($s->precio_venta ?? 0, 2);
    echo " | contado=" . number_format($s->precio_contado ?? 0, 2);
    echo " | bono=" . number_format($s->bono_techo_propio ?? 0, 2);
    echo " | total_real=" . number_format($s->precio_total_real ?? 0, 2) . "\n";
    
    // Check: is total_price = base_price - discount?
    $expectedTotal = ($s->base_price ?? 0) - ($s->discount ?? 0);
    $matches = abs($expectedTotal - ($s->total_price ?? 0)) < 0.01;
    echo "    base - discount = " . number_format($expectedTotal, 2) . " -> " . ($matches ? "MATCHES total_price" : "DOES NOT MATCH (diff=" . number_format($expectedTotal - ($s->total_price ?? 0), 2) . ")") . "\n";
    
    // Check: does lft.precio_venta reflect the discount?
    $lftReflectsDiscount = abs(($s->precio_venta ?? 0) - ($s->total_price ?? 0)) < 0.01;
    echo "    lft.precio_venta == total_price? " . ($lftReflectsDiscount ? "YES" : "NO (lft.precio_venta=" . number_format($s->precio_venta ?? 0, 2) . ")") . "\n";
}

// 3. The 5% calculation issue
echo "\n\n--- Problem Analysis ---\n";
echo "Current 5% calc uses: COALESCE(precio_venta,0) + COALESCE(bono_techo_propio,0) = precio_total_real\n";
echo "But lot_financial_templates.precio_venta is the LIST PRICE (no discount)\n";
echo "Contract.total_price already has the discount applied\n";
echo "So the 5% threshold is calculated on a higher price than what the client actually pays\n";

// 4. How many contracts match these patterns
echo "\n--- Discrepancy analysis ---\n";
$mismatched = DB::table('contracts as c')
    ->leftJoin('lot_financial_templates as lft', 'c.lot_id', '=', 'lft.lot_id')
    ->where('c.status', 'vigente')
    ->whereRaw('ABS(COALESCE(lft.precio_venta, 0) - c.total_price) > 1')
    ->count();
echo "  Contracts where lft.precio_venta != contract.total_price: {$mismatched}\n";

$discountButLftZero = DB::table('contracts as c')
    ->leftJoin('lot_financial_templates as lft', 'c.lot_id', '=', 'lft.lot_id')
    ->where('c.status', 'vigente')
    ->where('c.discount', '>', 0)
    ->where(function($q) {
        $q->where('lft.descuento', '=', 0)->orWhereNull('lft.descuento');
    })
    ->count();
echo "  Contracts with discount>0 but lft.descuento=0: {$discountButLftZero}\n";

// 5. Count by discount percentage (from logicware_data)
echo "\n--- Discount percentage distribution ---\n";
$contracts = DB::table('contracts')
    ->where('status', 'vigente')
    ->where('discount', '>', 0)
    ->get(['contract_id', 'base_price', 'discount']);
$pctBuckets = [];
foreach ($contracts as $c) {
    $pct = ($c->base_price > 0) ? round(($c->discount / $c->base_price) * 100) : 0;
    $pctBuckets[$pct] = ($pctBuckets[$pct] ?? 0) + 1;
}
ksort($pctBuckets);
foreach ($pctBuckets as $pct => $cnt) {
    echo "  {$pct}%: {$cnt} contratos\n";
}

echo "\nDone.\n";
