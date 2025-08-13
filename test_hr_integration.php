<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Collections\Http\Controllers\HrIntegrationController;
use Illuminate\Http\Request;

try {
    echo "=== Probando HR Integration ===\n\n";
    
    // Crear instancia del controlador
    $controller = new HrIntegrationController();
    
    // Probar estadísticas
    echo "1. Probando estadísticas...\n";
    $statsResponse = $controller->stats();
    $statsData = $statsResponse->getData(true);
    echo "Estadísticas: " . json_encode($statsData, JSON_PRETTY_PRINT) . "\n\n";
    
    // Probar sincronización
    echo "2. Probando sincronización...\n";
    $request = new Request();
    $syncResponse = $controller->sync($request);
    $syncData = $syncResponse->getData(true);
    echo "Sincronización: " . json_encode($syncData, JSON_PRETTY_PRINT) . "\n\n";
    
    // Verificar estadísticas después de la sincronización
    echo "3. Estadísticas después de la sincronización...\n";
    $statsAfterResponse = $controller->stats();
    $statsAfterData = $statsAfterResponse->getData(true);
    echo "Estadísticas actualizadas: " . json_encode($statsAfterData, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "=== Prueba completada exitosamente ===\n";
    
} catch (Exception $e) {
    echo "Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}