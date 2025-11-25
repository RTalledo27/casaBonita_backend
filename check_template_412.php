<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = Modules\Sales\Models\Contract::where('contract_number', '202510-000000412')->first();
$lot = $c->getLot();
if ($lot && $lot->lotFinancialTemplate) {
    echo "Template Commission: " . $lot->lotFinancialTemplate->commission_percentage . "\n";
} else {
    echo "No template.\n";
}
