<?php
use Modules\HumanResources\Models\CommissionRule;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rules = CommissionRule::where('min_sales', '<=', 6)
    ->where('max_sales', '>=', 6)
    ->whereNull('term_min_months')
    ->get();

echo "Reglas sin rango de meses (Any Term) para 6 ventas:\n";
foreach ($rules as $r) {
    echo "  %: {$r->percentage}\n";
}
