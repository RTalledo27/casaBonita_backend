<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Limpiando comisiones existentes...\n";

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    $deleted = DB::table('commissions')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "✅ Eliminadas $deleted comisiones.\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
