<?php

use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Services\CommissionEvaluator;
use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DIAGNÓSTICO DE CÁLCULO DE COMISIONES ===\n\n";

// Obtener una comisión reciente que tenga 2%
$commission = Commission::where('commission_percentage', 2.0)
    ->orderBy('created_at', 'desc')
    ->with(['contract', 'employee'])
    ->first();

if (!$commission) {
    echo "No se encontró ninguna comisión con 2%\n";
    exit;
}

$contract = $commission->contract;
echo "Contrato: {$contract->contract_number}\n";
echo "Asesor ID: {$contract->advisor_id}\n";
echo "Fecha firma: {$contract->sign_date}\n";
echo "Meses plazo: {$contract->term_months}\n";
echo "Precio Total: {$contract->total_price}\n";
echo "Monto Financiado: {$contract->financing_amount}\n\n";

// Contar ventas del asesor
$service = new CommissionService(new CommissionEvaluator());
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getAdvisorFinancedSalesCount');
$method->setAccessible(true);

$salesCount = $method->invoke($service, $contract->advisor_id, $contract->sign_date);
echo "Ventas contadas para el asesor: $salesCount\n\n";

// Verificar qué devuelve CommissionEvaluator
$evaluator = new CommissionEvaluator();
$saleType = ($contract->financing_amount && $contract->financing_amount > 0) ? 'financed' : 'cash';
$contractDate = $contract->sign_date ? $contract->sign_date->toDateString() : null;

echo "Sale Type: $saleType\n";
echo "Contract Date: $contractDate\n\n";

$result = $evaluator->evaluate($salesCount, $contract->term_months, $saleType, $contractDate);

if ($result) {
    echo "CommissionEvaluator ENCONTRÓ una regla:\n";
    echo "  Scheme ID: {$result['scheme_id']}\n";
    echo "  Rule ID: {$result['rule_id']}\n";
    echo "  Percentage: {$result['percentage']}%\n\n";
} else {
    echo "CommissionEvaluator NO encontró regla, usando fallback.\n\n";
}

// Probar el método getCommissionRate directamente
$rateMethod = $reflection->getMethod('getCommissionRate');
$rateMethod->setAccessible(true);

$rate = $rateMethod->invoke($service, $salesCount, $contract->term_months, $saleType, $contractDate);
echo "CommissionService::getCommissionRate devolvió: {$rate}%\n\n";

// Verificar reglas aplicables
echo "=== REGLAS APLICABLES ===\n";
$rules = DB::table('commission_rules')
    ->join('commission_schemes', 'commission_rules.scheme_id', '=', 'commission_schemes.id')
    ->where('commission_rules.min_sales', '<=', $salesCount)
    ->where('commission_rules.max_sales', '>=', $salesCount)
    ->where(function($q) use ($contractDate) {
        $q->whereNull('commission_schemes.effective_from')
          ->orWhere('commission_schemes.effective_from', '<=', $contractDate);
    })
    ->where(function($q) use ($contractDate) {
        $q->whereNull('commission_schemes.effective_to')
          ->orWhere('commission_schemes.effective_to', '>=', $contractDate);
    })
    ->select('commission_rules.*', 'commission_schemes.name as scheme_name')
    ->get();

foreach ($rules as $rule) {
    echo "Rule ID: {$rule->id} (Scheme: {$rule->scheme_name})\n";
    echo "  Sales: {$rule->min_sales}-{$rule->max_sales}\n";
    echo "  Term: {$rule->term_min_months}-{$rule->term_max_months} meses\n";
    echo "  Term Group: {$rule->term_group}\n";
    echo "  Sale Type: {$rule->sale_type}\n";
    echo "  Percentage: {$rule->percentage}%\n";
    echo "  Priority: {$rule->priority}\n\n";
}
