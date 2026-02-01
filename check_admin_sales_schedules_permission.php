<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Security\Models\User;

$user = User::where('email', 'admin@casabonita.com')->first();

if (!$user) {
    echo "NOUSER\n";
    exit(1);
}

$permission = 'sales.schedules.update';
echo $user->hasPermissionTo($permission) ? "HAS {$permission}\n" : "MISSING {$permission}\n";

