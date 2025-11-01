<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
if ($user) {
    $token = $user->createToken('test-token')->plainTextToken;
    echo "Token: " . $token . PHP_EOL;
} else {
    echo "No users found" . PHP_EOL;
}