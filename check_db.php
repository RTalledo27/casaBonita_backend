<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = DB::select("SHOW COLUMNS FROM invoices WHERE Field = 'contract_id'");
print_r($columns);

$payment_id = DB::select("SHOW COLUMNS FROM invoices WHERE Field = 'payment_id'");
print_r($payment_id);
