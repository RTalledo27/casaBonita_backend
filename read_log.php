<?php
$file = 'storage/logs/laravel.log';
if (!file_exists($file)) {
    echo "Log file not found.";
    exit;
}
$lines = file($file);
$last = array_slice($lines, -50);
foreach ($last as $line) {
    echo $line;
}
