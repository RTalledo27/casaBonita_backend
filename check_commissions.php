<?php

use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== COMISIONES RECIENTES ===\n\n";

$commissions = Commission::with(['contract', 'employee'])
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "Total comisiones: " . Commission::count() . "\n\n";

foreach ($commissions as $comm) {
    $contract = $comm->contract;
    echo "Contract: {$contract->contract_number}\n";
    echo "  Asesor: " . ($comm->employee ? $comm->employee->name : 'N/A') . "\n";
    echo "  Monto Comisión: {$comm->commission_amount}\n";
    echo "  Porcentaje: {$comm->commission_percentage}%\n";
    echo "  Sales Count: {$comm->sales_count}\n";
    echo "  Meses: " . ($contract ? $contract->term_months : 'N/A') . "\n";
    echo "  Precio Total: " . ($contract ? $contract->total_price : 'N/A') . "\n";
    echo "  Scheme ID: {$comm->commission_scheme_id}\n";
    echo "  Rule ID: {$comm->commission_rule_id}\n";
    echo "  ---\n";
}

// Mostrar distribución de porcentajes
echo "\n=== DISTRIBUCIÓN DE PORCENTAJES ===\n";
$distribution = DB::table('commissions')
    ->select('commission_percentage', DB::raw('count(*) as count'))
    ->groupBy('commission_percentage')
    ->get();

foreach ($distribution as $dist) {
    echo "{$dist->commission_percentage}%: {$dist->count} comisiones\n";
}