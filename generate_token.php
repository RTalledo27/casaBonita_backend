<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = Modules\Security\Models\User::first();

if ($user) {
    $token = $user->createToken('test-token');
    echo "Token: " . $token->plainTextToken . "\n";
    echo "User ID: " . $user->id . "\n";
    echo "User Email: " . $user->email . "\n";
} else {
    echo "No users found in database\n";
}