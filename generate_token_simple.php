<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;

// Get first user
$user = User::first();

if ($user) {
    $token = $user->createToken('test-pagination-token');
    echo "Token generated successfully:\n";
    echo $token->plainTextToken . "\n";
    echo "\nYou can use this token in the Authorization header as: Bearer " . $token->plainTextToken . "\n";
} else {
    echo "No users found in the database.\n";
    echo "Creating a test user...\n";
    
    $user = App\Models\User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now()
    ]);
    
    $token = $user->createToken('test-pagination-token');
    echo "Test user created and token generated:\n";
    echo $token->plainTextToken . "\n";
    echo "\nYou can use this token in the Authorization header as: Bearer " . $token->plainTextToken . "\n";
}