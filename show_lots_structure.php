<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "📋 Estructura de la tabla lots:\n\n";

$columns = DB::select('SHOW COLUMNS FROM lots');

foreach ($columns as $col) {
    echo "   • {$col->Field} ({$col->Type})\n";
}
