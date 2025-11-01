<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;

// Get first user
$user = User::first();

if ($user) {
    $token = $user->createToken('test-reports-token');
    echo "Token generated successfully:\n";
    echo $token->plainTextToken . "\n";
    echo "\nYou can use this token in the Authorization header as: Bearer " . $token->plainTextToken . "\n";
} else {
    echo "No users found in the database.\n";
}