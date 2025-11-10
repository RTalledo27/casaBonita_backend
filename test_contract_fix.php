<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Inventory\Services\ExternalLotImportService;

echo "=== PRUEBA DE IMPORTACIÓN DE VENTAS ===\n\n";

try {
    $service = app(ExternalLotImportService::class);
    
    echo "✅ Servicio inicializado correctamente\n";
    echo "📊 Intentando importar ventas...\n\n";
    
    $result = $service->importSales('2025-01-01', '2025-08-30', false);
    
    echo "\n✅ IMPORTACIÓN EXITOSA!\n";
    echo "📊 Resultados:\n";
    echo "   - Creados: " . ($result['created'] ?? 0) . "\n";
    echo "   - Actualizados: " . ($result['updated'] ?? 0) . "\n";
    echo "   - Errores: " . ($result['errors'] ?? 0) . "\n";
    
    if (!empty($result['errors_details'])) {
        echo "\n⚠️  Errores encontrados:\n";
        foreach ($result['errors_details'] as $error) {
            echo "   - $error\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
