<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Verificando tabla de roles:\n\n";

$roles = DB::table('roles')->get();

foreach ($roles as $role) {
    echo "ID: " . ($role->id ?? 'NULL') . "\n";
    echo "Name: {$role->name}\n";
    echo "Guard: {$role->guard_name}\n";
    echo "---\n";
}
