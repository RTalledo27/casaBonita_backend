<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;

$user = User::where('email', 'admin@casabonita.com')->first();

if ($user) {
    echo "Usuario encontrado:\n";
    echo "ID: " . $user->id . "\n";
    echo "Username: " . $user->username . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Must change password: " . ($user->must_change_password ? 'true' : 'false') . "\n";
} else {
    echo "Usuario no encontrado\n";
}