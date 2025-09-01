<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Inventory\Models\Manzana;

echo "Manzanas disponibles en la base de datos:\n";
echo "==========================================\n";

$manzanas = Manzana::select('manzana_id', 'name')->get();

foreach ($manzanas as $manzana) {
    echo "ID: {$manzana->manzana_id}, Name: '{$manzana->name}'\n";
}

echo "\nTotal manzanas: " . $manzanas->count() . "\n";