<?php
// Run artisan migrate and capture full output
$output = [];
$returnCode = 0;
exec('php artisan migrate --force 2>&1', $output, $returnCode);
echo "Exit code: $returnCode\n";
echo implode("\n", $output) . "\n";
