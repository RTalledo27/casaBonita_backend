<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Checking users table structure...\n";

// Check if must_change_password column exists
if (Schema::hasColumn('users', 'must_change_password')) {
    echo "✓ must_change_password column exists\n";
} else {
    echo "✗ must_change_password column does NOT exist\n";
    exit(1);
}

// Count total users
$totalUsers = User::count();
echo "\nTotal users in database: {$totalUsers}\n";

if ($totalUsers > 0) {
    // Get users that must change password
    $usersNeedingChange = User::where('must_change_password', true)->count();
    echo "Users that must change password: {$usersNeedingChange}\n";
    
    // Get a sample user that needs to change password
    $userNeedingChange = User::where('must_change_password', true)->first();
    if ($userNeedingChange) {
        echo "\nSample user that must change password:\n";
        echo "- ID: {$userNeedingChange->id}\n";
        echo "- Username: {$userNeedingChange->username}\n";
        echo "- Email: {$userNeedingChange->email}\n";
        echo "- must_change_password: " . ($userNeedingChange->must_change_password ? 'true' : 'false') . "\n";
        echo "- password_changed_at: {$userNeedingChange->password_changed_at}\n";
    }
    
    // Get a sample user that doesn't need to change password
    $userNotNeedingChange = User::where('must_change_password', false)->first();
    if ($userNotNeedingChange) {
        echo "\nSample user that doesn't need to change password:\n";
        echo "- ID: {$userNotNeedingChange->id}\n";
        echo "- Username: {$userNotNeedingChange->username}\n";
        echo "- Email: {$userNotNeedingChange->email}\n";
        echo "- must_change_password: " . ($userNotNeedingChange->must_change_password ? 'true' : 'false') . "\n";
        echo "- password_changed_at: {$userNotNeedingChange->password_changed_at}\n";
    }
} else {
    echo "\nNo users found in database\n";
}

echo "\nDone.\n";