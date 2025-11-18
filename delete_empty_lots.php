<?php

require_once 'vendor/autoload.php';

use Modules\Inventory\Models\Lot;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🗑️  Eliminando lotes sin lot_number...\n";

$deleted = Lot::whereNull('lot_number')
    ->orWhere('lot_number', '')
    ->delete();

echo "✅ Lotes eliminados: {$deleted}\n";
