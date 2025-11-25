<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LIMPIANDO COMISIONES ===\n";

Schema::disableForeignKeyConstraints();
DB::table('commissions')->truncate();
Schema::enableForeignKeyConstraints();

echo "Tabla 'commissions' vaciada correctamente.\n";
