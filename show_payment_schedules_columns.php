<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = DB::select('SHOW COLUMNS FROM payment_schedules');
echo "Columnas de payment_schedules:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($columns as $col) {
    echo "• {$col->Field} ({$col->Type})\n";
}
