<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Checking clients table schema...\n";
    
    // Check if doc_number column allows NULL
    $columns = DB::select('SHOW COLUMNS FROM clients WHERE Field = "doc_number"');
    
    if (!empty($columns)) {
        $column = $columns[0];
        echo "Column: {$column->Field}\n";
        echo "Type: {$column->Type}\n";
        echo "Null: {$column->Null}\n";
        echo "Key: {$column->Key}\n";
        echo "Default: {$column->Default}\n";
        echo "Extra: {$column->Extra}\n";
        
        if ($column->Null === 'YES') {
            echo "✓ doc_number column allows NULL values\n";
        } else {
            echo "✗ doc_number column does NOT allow NULL values\n";
        }
    } else {
        echo "✗ doc_number column not found\n";
    }
    
    // Also check unique constraints
    echo "\nChecking unique constraints...\n";
    $indexes = DB::select('SHOW INDEX FROM clients WHERE Column_name = "doc_number"');
    
    foreach ($indexes as $index) {
        echo "Index: {$index->Key_name}, Unique: " . ($index->Non_unique ? 'NO' : 'YES') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}