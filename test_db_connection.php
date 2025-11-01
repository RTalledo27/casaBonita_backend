<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    DB::connection()->getPdo();
    echo "Database connected successfully\n";
    
    // Test a simple query
    $result = DB::select('SELECT 1 as test');
    echo "Simple query test passed\n";
    
    // Test if contracts table exists
    $tables = DB::select("SHOW TABLES LIKE 'contracts'");
    if (count($tables) > 0) {
        echo "Contracts table exists\n";
    } else {
        echo "Contracts table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}