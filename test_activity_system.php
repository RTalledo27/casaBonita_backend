<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\UserActivityLog;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Activity Logs System ===\n";
echo "Total logs: " . UserActivityLog::count() . "\n\n";

if (UserActivityLog::count() > 0) {
    echo "Recent activity:\n";
    foreach (UserActivityLog::with('user')->orderBy('created_at', 'desc')->limit(10)->get() as $log) {
        echo "- " . $log->getActionLabel() . "\n";
        echo "  Details: " . $log->details . "\n";
        echo "  Time: " . $log->created_at->diffForHumans() . "\n";
        echo "  User ID: " . $log->user_id . " | IP: " . $log->ip_address . "\n\n";
    }
} else {
    echo "No activity logs yet. Login to create the first one!\n";
}
