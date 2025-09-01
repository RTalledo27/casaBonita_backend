<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Checking clients table schema ===\n";

try {
    // Check if table exists
    if (Schema::hasTable('clients')) {
        echo "✓ Clients table exists\n";
        
        // Get table structure
        $columns = DB::select('DESCRIBE clients');
        
        echo "\nTable structure:\n";
        foreach ($columns as $column) {
            echo "- {$column->Field}: {$column->Type} | Null: {$column->Null} | Key: {$column->Key} | Default: {$column->Default}\n";
        }
        
        // Check specifically for doc_number column
        $docNumberColumn = collect($columns)->firstWhere('Field', 'doc_number');
        if ($docNumberColumn) {
            echo "\n=== doc_number column details ===\n";
            echo "Type: {$docNumberColumn->Type}\n";
            echo "Allows NULL: {$docNumberColumn->Null}\n";
            echo "Key: {$docNumberColumn->Key}\n";
            echo "Default: {$docNumberColumn->Default}\n";
        }
        
        // Test creating a client with NULL doc_number
        echo "\n=== Testing NULL doc_number insertion ===\n";
        try {
            $client = DB::table('clients')->insert([
                'first_name' => 'Test',
                'last_name' => 'User',
                'doc_type' => 'DNI',
                'doc_number' => null,
                'primary_phone' => '123456789',
                'type' => 'client',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "✓ Successfully inserted client with NULL doc_number\n";
            
            // Clean up test data
            DB::table('clients')->where('first_name', 'Test')->where('last_name', 'User')->delete();
            echo "✓ Test data cleaned up\n";
        } catch (Exception $e) {
            echo "✗ Failed to insert client with NULL doc_number: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "✗ Clients table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Schema verification complete ===\n";