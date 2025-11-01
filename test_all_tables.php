<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Testing all related tables...\n\n";
    
    $tables = ['contracts', 'reservations', 'lots', 'projects', 'users', 'accounts_receivable'];
    
    foreach ($tables as $table) {
        try {
            $count = DB::table($table)->count();
            echo "$table: $count records\n";
        } catch (Exception $e) {
            echo "$table: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nTable test completed.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}