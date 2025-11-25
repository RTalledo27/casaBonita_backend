<?php

use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\CommissionRule;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = Contract::where('contract_number', '202510-000000412')->first();

echo "Contract: {$c->contract_number}\n";
echo "Term: {$c->term_months}\n";
echo "Advisor: {$c->advisor_id}\n";

$count = Contract::where('advisor_id', $c->advisor_id)
    ->whereMonth('contract_date', 10)
    ->whereYear('contract_date', 2025)
    ->where('status', 'vigente')
    ->where('financing_amount', '>', 0)
    ->count();

echo "Sales Count: $count\n";

$rules = CommissionRule::where('min_sales', '<=', $count)
    ->where('max_sales', '>=', $count)
    ->get();

foreach ($rules as $r) {
    echo "Rule: Sales {$r->min_sales}-{$r->max_sales}, Term {$r->term_min_months}-{$r->term_max_months}, %: {$r->percentage}\n";
}
