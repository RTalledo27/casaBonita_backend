<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Http\Controllers\HrIntegrationController;

try {
    $controller = new HrIntegrationController();
    $response = $controller->sync();
    
    echo "Resultado de la sincronización:\n";
    echo json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "Error durante la sincronización: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}