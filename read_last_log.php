<?php

$logFile = storage_path('logs/laravel.log');
if (!file_exists($logFile)) {
    echo "No log file found.\n";
    exit;
}

$content = file_get_contents($logFile);
$lines = explode("\n", $content);
$lines = array_slice($lines, -50); // Get last 50 lines

foreach ($lines as $line) {
    echo $line . "\n";
}
