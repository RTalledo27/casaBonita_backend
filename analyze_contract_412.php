<?php

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\CommissionRule;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contractNumber = '202510-000000412';

echo "=== ANÁLISIS DE CONTRATO $contractNumber ===\n\n";

$contract = Contract::where('contract_number', $contractNumber)->first();

if (!$contract) {
    echo "Contrato no encontrado.\n";
    exit;
}

echo "DETALLES DEL CONTRATO:\n";
echo "  Advisor ID:       {$contract->advisor_id}\n";
echo "  Sign Date:        {$contract->sign_date}\n";
echo "  Contract Date:    {$contract->contract_date}\n";
echo "  Status:           {$contract->status}\n";
echo "  Total Price:      S/ " . number_format($contract->total_price, 2) . "\n";
echo "  Financing Amount: S/ " . number_format($contract->financing_amount, 2) . "\n";
echo "  Term Months:      {$contract->term_months}\n";
echo "  Sale Type:        " . ($contract->financing_amount > 0 ? 'financed' : 'cash') . "\n\n";

// Contar ventas del asesor
$advisorId = $contract->advisor_id;
$month = $contract->contract_date->month;
$year = $contract->contract_date->year;

$salesCount = Contract::where('advisor_id', $advisorId)
    ->whereMonth('contract_date', $month)
    ->whereYear('contract_date', $year)
    ->where('status', 'vigente')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->count();

echo "VENTAS DEL ASESOR (ID: $advisorId) EN EL PERIODO ($year-$month):\n";
echo "  Total Ventas (Vigentes + Financiadas): $salesCount\n\n";

// Buscar regla aplicable
echo "BUSCANDO REGLA PARA:\n";
echo "  Sales: $salesCount\n";
echo "  Term: {$contract->term_months}\n";
echo "  Type: " . ($contract->financing_amount > 0 ? 'financed' : 'cash') . "\n\n";

$rules = CommissionRule::where('min_sales', '<=', $salesCount)
    ->where('max_sales', '>=', $salesCount)
    ->where('term_min_months', '<=', $contract->term_months)
    ->where('term_max_months', '>=', $contract->term_months)
    ->where(function($q) use ($contract) {
        $type = $contract->financing_amount > 0 ? 'financed' : 'cash';
        $q->where('sale_type', $type)
          ->orWhereNull('sale_type');
    })
    ->get();

if ($rules->isEmpty()) {
    echo "⚠️  NO SE ENCONTRÓ REGLA EXACTA\n";
    echo "Buscando reglas parciales (solo por ventas):\n";
    $partialRules = CommissionRule::where('min_sales', '<=', $salesCount)
        ->where('max_sales', '>=', $salesCount)
        ->get();
    foreach ($partialRules as $r) {
        echo "  - Rango Ventas {$r->min_sales}-{$r->max_sales} | Term {$r->term_min_months}-{$r->term_max_months} | Type {$r->sale_type} | %: {$r->percentage}\n";
    }
} else {
    echo "REGLAS ENCONTRADAS:\n";
    foreach ($rules as $r) {
        echo "  ✅ ID {$r->id} | %: {$r->percentage} | Priority: {$r->priority}\n";
    }
}
