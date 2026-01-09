<?php

// Test KPIs endpoint
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = new \Modules\Collections\app\Http\Controllers\KpisController();
$response = $controller->index();

echo json_encode($response->getData(), JSON_PRETTY_PRINT);
