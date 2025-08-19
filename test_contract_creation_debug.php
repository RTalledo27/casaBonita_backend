<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Log;
use Modules\Sales\app\Services\ContractImportService;
use Modules\Inventory\Models\Lot;
use Modules\Sales\Models\Client;
use Modules\Sales\Models\Employee;

// Configurar logging para mostrar en consola
Log::info('=== INICIANDO DEBUG DE CREACIÓN DE CONTRATOS ===');

// Datos exactos del usuario
$testData = [
    'ASESOR_NOMBRE' => 'ALISSON TORRES',
    'ASESOR_CODIGO' => '-',
    'ASESOR_EMAIL' => '-',
    'CLIENTE_NOMBRE_COMPLETO' => 'LUZ AURORA ARMIJOS ROBLEDO',
    'CLIENTE_TIPO_DOC' => 'DNI',
    'CLIENTE_NUM_DOC' => '-',
    'CLIENTE_TELEFONO_1' => '950285502',
    'CLIENTE_EMAIL' => '-',
    'LOTE_NUMERO' => '5',
    'LOTE_MANZANA' => 'H',
    'FECHA_VENTA' => '02/06/2025',
    'TIPO_OPERACION' => 'contrato',
    'OBSERVACIONES' => 'LEAD KOMMO',
    'ESTADO_CONTRATO' => 'ACTIVO'
];

echo "\n=== DATOS DE PRUEBA ===\n";
foreach ($testData as $key => $value) {
    echo "$key: '$value'\n";
}

// Crear instancia del servicio
$importService = new ContractImportService();

// Usar reflection para acceder a métodos privados
$reflection = new ReflectionClass($importService);

echo "\n=== PASO 1: MAPEO DE HEADERS ===\n";
$mapHeadersMethod = $reflection->getMethod('mapSimplifiedHeaders');
$mapHeadersMethod->setAccessible(true);
$mappedHeaders = $mapHeadersMethod->invoke($importService, array_keys($testData));
echo "Headers mapeados:\n";
print_r($mappedHeaders);

echo "\n=== PASO 2: MAPEO DE DATOS DE FILA ===\n";
$mapRowMethod = $reflection->getMethod('mapRowDataSimplified');
$mapRowMethod->setAccessible(true);
$mappedData = $mapRowMethod->invoke($importService, $testData, $mappedHeaders);
echo "Datos mapeados:\n";
print_r($mappedData);

echo "\n=== PASO 3: VERIFICAR SI DEBE CREAR CONTRATO ===\n";
$shouldCreateMethod = $reflection->getMethod('shouldCreateContractSimplified');
$shouldCreateMethod->setAccessible(true);
$shouldCreate = $shouldCreateMethod->invoke($importService, $mappedData);

echo "¿Debe crear contrato? " . ($shouldCreate ? 'SÍ' : 'NO') . "\n";
echo "operation_type en datos mapeados: '" . ($mappedData['operation_type'] ?? 'NO_DEFINIDO') . "'\n";
echo "contract_status en datos mapeados: '" . ($mappedData['contract_status'] ?? 'NO_DEFINIDO') . "'\n";

// Verificar valores específicos
echo "\n=== ANÁLISIS DETALLADO ===\n";
echo "Valor original TIPO_OPERACION: '" . $testData['TIPO_OPERACION'] . "'\n";
echo "Valor mapeado operation_type: '" . ($mappedData['operation_type'] ?? 'NO_DEFINIDO') . "'\n";
echo "Valor original ESTADO_CONTRATO: '" . $testData['ESTADO_CONTRATO'] . "'\n";
echo "Valor mapeado contract_status: '" . ($mappedData['contract_status'] ?? 'NO_DEFINIDO') . "'\n";

// Verificar condiciones específicas
$tipoOperacion = strtolower($mappedData['operation_type'] ?? '');
$estadoContrato = strtolower($mappedData['contract_status'] ?? '');

echo "\n=== EVALUACIÓN DE CONDICIONES ===\n";
echo "operation_type en minúsculas: '$tipoOperacion'\n";
echo "contract_status en minúsculas: '$estadoContrato'\n";
echo "¿operation_type es 'venta' o 'contrato'? " . (in_array($tipoOperacion, ['venta', 'contrato']) ? 'SÍ' : 'NO') . "\n";
echo "¿contract_status es 'vigente', 'activo' o 'firmado'? " . (in_array($estadoContrato, ['vigente', 'activo', 'firmado']) ? 'SÍ' : 'NO') . "\n";

// Verificar si existe el lote
echo "\n=== VERIFICACIÓN DE LOTE ===\n";
$lot = Lot::where('num_lot', $mappedData['lot_number'] ?? '')
           ->where('manzana_id', $mappedData['lot_manzana'] ?? '')
           ->first();

if ($lot) {
    echo "Lote encontrado: ID {$lot->lot_id}, Número {$lot->num_lot}, Manzana {$lot->manzana}\n";
    echo "¿Tiene template financiero? " . ($lot->financialTemplate ? 'SÍ' : 'NO') . "\n";
    if ($lot->financialTemplate) {
        echo "Template ID: {$lot->financialTemplate->template_id}\n";
    }
} else {
    echo "LOTE NO ENCONTRADO con número: '" . ($mappedData['lot_number'] ?? 'NO_DEFINIDO') . "' y manzana: '" . ($mappedData['lot_manzana'] ?? 'NO_DEFINIDO') . "'\n";
}

// Verificar si existe el cliente
echo "\n=== VERIFICACIÓN DE CLIENTE ===\n";
$clientName = $mappedData['cliente_nombres'] ?? '';
if ($clientName) {
    $client = Client::where('full_name', 'LIKE', "%$clientName%")->first();
    if ($client) {
        echo "Cliente encontrado: ID {$client->client_id}, Nombre: {$client->full_name}\n";
    } else {
        echo "Cliente no encontrado con nombre: '$clientName'\n";
    }
} else {
    echo "Nombre de cliente no definido en datos mapeados\n";
}

echo "\n=== FIN DEL DEBUG ===\n";