<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ESTRUCTURA DE LA TABLA EMPLOYEES ===\n\n";

$columns = DB::select("DESCRIBE employees");

foreach ($columns as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}

echo "\n\n=== MUESTRA DE DATOS ===\n\n";

$sample = DB::table('employees')->limit(3)->get();

foreach ($sample as $emp) {
    echo "\nEmployee ID: {$emp->employee_id}\n";
    foreach ($emp as $key => $value) {
        echo "  $key: $value\n";
    }
}
