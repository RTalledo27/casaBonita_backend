<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Modules\Security\Models\User;

echo "Creating test token for user with must_change_password = true...\n";

// Find a user that needs to change password
$user = User::where('must_change_password', true)->first();

if (!$user) {
    echo "No user found with must_change_password = true\n";
    exit(1);
}

echo "Found user: {$user->username}\n";
echo "must_change_password: " . ($user->must_change_password ? 'true' : 'false') . "\n";

// Create a token for this user
$token = $user->createToken('test-middleware-token')->plainTextToken;
echo "\nGenerated token: {$token}\n";
echo "\nYou can now use this token to test the API.\n";
echo "\nTo test manually, run:\n";
echo "curl -H \"Authorization: Bearer {$token}\" -H \"Accept: application/json\" http://127.0.0.1:8000/api/v1/security/users\n";
echo "\nExpected result: 403 status with must_change_password message\n";