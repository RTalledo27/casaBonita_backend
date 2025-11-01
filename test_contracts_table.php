<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Testing contracts table...\n";
    
    // Test basic count
    $count = DB::table('contracts')->count();
    echo "Contracts count: $count\n";
    
    // Test if table has data
    if ($count > 0) {
        $sample = DB::table('contracts')->first();
        echo "Sample contract columns: " . implode(', ', array_keys((array)$sample)) . "\n";
    }
    
    echo "Contracts table test completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}