<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('username', 'admin')->first();

if ($user) {
    echo "Usuario encontrado:\n";
    echo "Username: " . $user->username . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Password hash: " . $user->password_hash . "\n";
    
    // Test password
    $testPassword = 'password';
    $isValid = Hash::check($testPassword, $user->password_hash);
    echo "Password '$testPassword' is valid: " . ($isValid ? 'YES' : 'NO') . "\n";
    
    // Test with different passwords
    $passwords = ['password', 'admin', 'Secret@123', '123456'];
    foreach ($passwords as $pwd) {
        $valid = Hash::check($pwd, $user->password_hash);
        echo "Password '$pwd': " . ($valid ? 'VALID' : 'INVALID') . "\n";
    }
} else {
    echo "Usuario no encontrado\n";
}