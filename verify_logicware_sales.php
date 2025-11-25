<?php

use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== VERIFICACIÓN DE VENTAS DE LUIS TAVARA EN LOGICWARE ===\n\n";

// Get Logicware service
$logicwareService = app(LogicwareApiService::class);

// Fetch October 2025 sales
$salesData = $logicwareService->getSales('2025-10-01', '2025-10-31', false);

if (!isset($salesData['data'])) {
    echo "Error: No se pudieron obtener ventas de Logicware\n";
    var_dump($salesData);
    exit;
}

$allSales = $salesData['data'];
echo "Total de ventas en Logicware (Octubre 2025): " . count($allSales) . "\n\n";

// Filter by Luis Tavara
// Need to find his employee code first
$tavaraSales = [];
$advisorCodes = [];

foreach ($allSales as $sale) {
    $advisorCode = $sale['adviserCode'] ?? $sale['advisorCode'] ?? $sale['asesor'] ?? null;
    $advisorName = $sale['adviserName'] ?? $sale['advisorName'] ?? $sale['asesorNombre'] ?? '';
    
    if ($advisorCode) {
        if (!isset($advisorCodes[$advisorCode])) {
            $advisorCodes[$advisorCode] = [
                'name' => $advisorName,
                'count' => 0
            ];
        }
        $advisorCodes[$advisorCode]['count']++;
    }
    
    // Check if it's Tavara
    if (stripos($advisorName, 'tavara') !== false || stripos($advisorName, 'tavar') !== false) {
        $tavaraSales[] = $sale;
    }
}

echo "VENTAS POR ASESOR EN LOGICWARE:\n";
echo str_repeat("=", 70) . "\n";
arsort($advisorCodes);
foreach (array_slice($advisorCodes, 0, 10, true) as $code => $info) {
    echo sprintf("%-15s %-40s %3d ventas\n", $code, substr($info['name'], 0, 40), $info['count']);
    if (stripos($info['name'], 'tavara') !== false) {
        echo "       ^^^ LUIS TAVARA ^^^\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "VENTAS DE LUIS TAVARA EN LOGICWARE: " . count($tavaraSales) . "\n\n";

if (!empty($tavaraSales)) {
    echo "DETALLE DE VENTAS:\n";
    foreach ($tavaraSales as $i => $sale) {
        echo sprintf("%2d. Doc: %s | Monto: %s | Fecha: %s | Estado: %s\n",
            $i + 1,
            $sale['documentNumber'] ?? 'N/A',
            $sale['contractValue'] ?? $sale['totalValue'] ?? 'N/A',
            $sale['contractDate'] ?? $sale['saleDate'] ?? 'N/A',
            $sale['status'] ?? 'N/A'
        );
    }
}

// Now check our database
echo "\n" . str_repeat("=", 70) . "\n";
echo "VENTAS EN NUESTRA BASE DE DATOS:\n";

$dbCount = DB::table('contracts')
    ->where('advisor_id', 13) // Luis Tavara ID
    ->whereMonth('contract_date', 10)
    ->whereYear('contract_date', 2025)
    ->where('status', 'vigente')
    ->where('financing_amount', '>', 0)
    ->count();

echo "Luis Tavara (ID 13): $dbCount contratos contados\n\n";

echo "COMPARACIÓN:\n";
echo "  Logicware: " . count($tavaraSales) . " ventas\n";
echo "  Base de Datos: $dbCount ventas\n";
echo "  Diferencia: " . ($dbCount - count($tavaraSales)) . " ventas\n";

if ($dbCount > count($tavaraSales)) {
    echo "\n⚠️  La base de datos tiene MÁS ventas que Logicware\n";
    echo "Posibles causas:\n";
    echo "  1. Contratos duplicados en la importación\n";
    echo "  2. Ventas canceladas en Logicware pero vigentes en BD\n";
    echo "  3. Filtros diferentes (status, financing_amount, etc.)\n";
}
