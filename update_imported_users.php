<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Illuminate\Support\Facades\Hash;

echo "Updating imported users to require password change...\n";

// Find users with default password (123456) and mark them to change password
$users = User::all();
$updatedCount = 0;

foreach ($users as $user) {
    // Check if user has the default password
    if (Hash::check('123456', $user->password_hash)) {
        $user->must_change_password = true;
        $user->password_changed_at = null;
        $user->save();
        
        echo "Updated user: {$user->username} ({$user->email})\n";
        $updatedCount++;
    }
}

echo "\nTotal users updated: {$updatedCount}\n";
echo "Done.\n";