<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LIMPIANDO CONTRATOS Y CRONOGRAMAS ===\n";

Schema::disableForeignKeyConstraints();

echo "Vaciando payment_schedules...\n";
DB::table('payment_schedules')->truncate();

echo "Vaciando contracts...\n";
DB::table('contracts')->truncate();

Schema::enableForeignKeyConstraints();

echo "✅ Tablas vaciadas correctamente.\n";
