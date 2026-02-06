<?php
// Simple log reader without Laravel dependencies
$logFile = 'storage/logs/laravel.log';
if (!file_exists($logFile)) {
    echo "No log file found.\n";
    exit;
}

$content = file_get_contents($logFile);
$lines = explode("\n", $content);
$lines = array_slice($lines, -50); 

foreach ($lines as $line) {
    echo $line . "\n";
}
