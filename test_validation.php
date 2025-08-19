<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\App\Services\ContractImportService;
use Illuminate\Support\Facades\Log;

echo "=== Test de Validación de Campos de Lote ===\n\n";

// Crear instancia del servicio
$service = new ContractImportService();

// Usar reflexión para acceder al método privado
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('validateRequiredLotFields');
$method->setAccessible(true);

// Test 1: Lote vacío
echo "Test 1 - Lote vacío:\n";
$testData1 = ['lot_number' => '', 'lot_manzana' => 'A'];
$result1 = $method->invoke($service, $testData1, 1);
echo "Resultado: " . ($result1 ? 'VÁLIDO (ERROR)' : 'OMITIDO (CORRECTO)') . "\n\n";

// Test 2: Manzana vacía
echo "Test 2 - Manzana vacía:\n";
$testData2 = ['lot_number' => '123', 'lot_manzana' => ''];
$result2 = $method->invoke($service, $testData2, 2);
echo "Resultado: " . ($result2 ? 'VÁLIDO (ERROR)' : 'OMITIDO (CORRECTO)') . "\n\n";

// Test 3: Ambos campos vacíos
echo "Test 3 - Ambos campos vacíos:\n";
$testData3 = ['lot_number' => '', 'lot_manzana' => ''];
$result3 = $method->invoke($service, $testData3, 3);
echo "Resultado: " . ($result3 ? 'VÁLIDO (ERROR)' : 'OMITIDO (CORRECTO)') . "\n\n";

// Test 4: Datos completos
echo "Test 4 - Datos completos:\n";
$testData4 = ['lot_number' => '123', 'lot_manzana' => 'A'];
$result4 = $method->invoke($service, $testData4, 4);
echo "Resultado: " . ($result4 ? 'VÁLIDO (CORRECTO)' : 'OMITIDO (ERROR)') . "\n\n";

// Test 5: Solo espacios en blanco
echo "Test 5 - Solo espacios en blanco:\n";
$testData5 = ['lot_number' => '   ', 'lot_manzana' => '\t\n'];
$result5 = $method->invoke($service, $testData5, 5);
echo "Resultado: " . ($result5 ? 'VÁLIDO (ERROR)' : 'OMITIDO (CORRECTO)') . "\n\n";

echo "=== Fin de las pruebas ===\n";