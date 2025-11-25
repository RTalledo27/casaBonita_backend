<?php

use Modules\HumanResources\Models\CommissionRule;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== REGLAS DE COMISIÓN ===\n\n";

$rules = CommissionRule::orderBy('min_sales')->get();

foreach ($rules as $rule) {
    echo sprintf("Sales: %d-%d | Term: %d-%d | Type: %s | %%: %s\n",
        $rule->min_sales,
        $rule->max_sales,
        $rule->term_min_months,
        $rule->term_max_months,
        $rule->sale_type ?? 'ANY',
        $rule->percentage
    );
}

echo "\n\nChequeo específico:\n";
echo "Para 16 ventas:\n";
$rule16 = CommissionRule::where('min_sales', '<=', 16)
    ->where('max_sales', '>=', 16)
    ->get();
foreach ($rule16 as $r) echo "  - " . $r->percentage . "% (Term: {$r->term_min_months}-{$r->term_max_months})\n";

echo "Para 21 ventas:\n";
$rule21 = CommissionRule::where('min_sales', '<=', 21)
    ->where('max_sales', '>=', 21)
    ->get();
foreach ($rule21 as $r) echo "  - " . $r->percentage . "% (Term: {$r->term_min_months}-{$r->term_max_months})\n";
