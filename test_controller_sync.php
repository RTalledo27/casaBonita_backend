<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Collections\Http\Controllers\HrIntegrationController;
use Illuminate\Http\Request;

echo "=== Prueba del Controlador HR Integration ===\n\n";

try {
    $controller = new HrIntegrationController();
    $request = new Request();
    
    echo "1. Probando método sync del controlador...\n";
    $response = $controller->sync($request);
    
    echo "Respuesta del sync:\n";
    echo $response->getContent() . "\n\n";
    
    echo "2. Probando estadísticas después del sync...\n";
    $statsResponse = $controller->stats();
    echo "Estadísticas:\n";
    echo $statsResponse->getContent() . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Prueba completada ===\n";