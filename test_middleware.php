<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Illuminate\Http\Request;
use Modules\Security\Http\Middleware\CheckPasswordChange;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

echo "Testing CheckPasswordChange middleware...\n";

// Get a user that must change password
$user = User::where('must_change_password', true)->first();

if (!$user) {
    echo "No user found that must change password\n";
    exit(1);
}

echo "Testing with user: {$user->username}\n";
echo "must_change_password: " . ($user->must_change_password ? 'true' : 'false') . "\n";

// Create a mock request
$request = Request::create('/api/v1/security/users', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Test the middleware
$middleware = new CheckPasswordChange();

try {
    $response = $middleware->handle($request, function ($request) {
        return response()->json(['message' => 'Access granted']);
    });
    
    $responseData = json_decode($response->getContent(), true);
    echo "\nMiddleware response:\n";
    echo "Status: {$response->getStatusCode()}\n";
    echo "Content: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
    
    if ($response->getStatusCode() === 403) {
        echo "\n✓ Middleware is working correctly - blocking access\n";
    } else {
        echo "\n✗ Middleware is NOT working - allowing access\n";
    }
    
} catch (Exception $e) {
    echo "Error testing middleware: {$e->getMessage()}\n";
}

echo "\nDone.\n";