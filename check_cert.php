<?php

use Illuminate\Support\Facades\Config;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$configPath = config('sunat.cert_path');
$fullPath = storage_path($configPath);

echo "Configured Path: " . $configPath . "\n";
echo "Full Path: " . $fullPath . "\n";
echo "File Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";

// Manual check of the file found by list_dir
$manualPath = base_path('storage/certs/certificado.p12');
echo "Manual Path Check: " . $manualPath . "\n";
echo "Manual Exists: " . (file_exists($manualPath) ? 'YES' : 'NO') . "\n";
