<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$c = Modules\Sales\Models\Contract::where('contract_number', '202510-000000412')->first();
echo "TERM: " . $c->term_months . "\n";
echo "SALES: " . Modules\Sales\Models\Contract::where('advisor_id', $c->advisor_id)->whereMonth('contract_date', 10)->whereYear('contract_date', 2025)->where('status', 'vigente')->where('financing_amount', '>', 0)->count();
