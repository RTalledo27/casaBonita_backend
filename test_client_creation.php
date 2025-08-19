<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\app\Models\Client;
use Illuminate\Support\Facades\Log;

echo "Testing client creation with NULL doc_number...\n";

try {
    // Test 1: Create client with NULL doc_number
    $client1 = Client::create([
        'first_name' => 'TEST',
        'last_name' => 'CLIENT 1',
        'doc_type' => 'DNI',
        'doc_number' => null, // Explicitly NULL
        'primary_phone' => '123456789',
        'type' => 'client'
    ]);
    
    echo "✓ Client 1 created successfully with NULL doc_number (ID: {$client1->client_id})\n";
    
    // Test 2: Create another client with NULL doc_number
    $client2 = Client::create([
        'first_name' => 'TEST',
        'last_name' => 'CLIENT 2',
        'doc_type' => 'DNI',
        'doc_number' => null, // Explicitly NULL
        'primary_phone' => '987654321',
        'type' => 'client'
    ]);
    
    echo "✓ Client 2 created successfully with NULL doc_number (ID: {$client2->client_id})\n";
    
    // Test 3: Try to create client with empty string (should fail)
    try {
        $client3 = Client::create([
            'first_name' => 'TEST',
            'last_name' => 'CLIENT 3',
            'doc_type' => 'DNI',
            'doc_number' => '', // Empty string
            'primary_phone' => '555666777',
            'type' => 'client'
        ]);
        echo "✗ Client 3 created with empty string - THIS SHOULD NOT HAPPEN\n";
    } catch (Exception $e) {
        echo "✓ Client 3 creation failed as expected: " . $e->getMessage() . "\n";
    }
    
    // Clean up
    $client1->delete();
    $client2->delete();
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}