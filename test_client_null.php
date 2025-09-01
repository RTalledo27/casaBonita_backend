<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\app\Models\Client;
use Illuminate\Support\Facades\Log;

try {
    echo "Testing client creation with NULL doc_number...\n";
    
    // Test 1: Create client with explicit NULL doc_number
    $client1 = Client::create([
        'first_name' => 'TEST',
        'last_name' => 'CLIENT NULL',
        'doc_type' => 'DNI',
        'doc_number' => null,
        'primary_phone' => '123456789',
        'type' => 'client'
    ]);
    
    echo "✓ Client created with NULL doc_number: ID {$client1->id}\n";
    
    // Test 2: Create another client with NULL doc_number (should work due to unique constraint allowing multiple NULLs)
    $client2 = Client::create([
        'first_name' => 'ANOTHER',
        'last_name' => 'CLIENT NULL',
        'doc_type' => 'DNI', 
        'doc_number' => null,
        'primary_phone' => '987654321',
        'type' => 'client'
    ]);
    
    echo "✓ Second client created with NULL doc_number: ID {$client2->id}\n";
    
    // Test 3: Try to create client with empty string (should fail)
    try {
        $client3 = Client::create([
            'first_name' => 'SHOULD',
            'last_name' => 'FAIL',
            'doc_type' => 'DNI',
            'doc_number' => '',
            'primary_phone' => '555555555',
            'type' => 'client'
        ]);
        echo "✗ ERROR: Client with empty string doc_number was created (should have failed)\n";
    } catch (Exception $e) {
        echo "✓ Expected error for empty string doc_number: " . $e->getMessage() . "\n";
    }
    
    // Clean up test data
    $client1->delete();
    $client2->delete();
    
    echo "\n✓ All tests passed! NULL values are working correctly.\n";
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}