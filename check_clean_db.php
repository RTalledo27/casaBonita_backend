<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contracts = DB::table('contracts')->count();
$schedules = DB::table('payment_schedules')->count();

echo "📊 ESTADO ACTUAL:\n";
echo "   • Contratos: {$contracts}\n";
echo "   • Cronogramas: {$schedules}\n\n";

if ($contracts === 0 && $schedules === 0) {
    echo "✅ Base de datos limpia - lista para importar\n";
} else {
    echo "⚠️  Aún hay datos en la base de datos\n";
}
