<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Ver estado actual
$columns = DB::select("SHOW COLUMNS FROM invoices WHERE Field = 'contract_id'");
print_r($columns);

// 2. Forzar cambio
try {
    DB::statement("ALTER TABLE invoices MODIFY contract_id BIGINT UNSIGNED NULL");
    echo "ALTER TABLE executed successfully.\n";
} catch (\Exception $e) {
    echo "Error modifying table: " . $e->getMessage() . "\n";
}

// 3. Ver estado final
$columnsEnd = DB::select("SHOW COLUMNS FROM invoices WHERE Field = 'contract_id'");
print_r($columnsEnd);
