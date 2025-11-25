<?php

use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== COMPARACIÓN: LOGICWARE vs BASE DE DATOS ===\n\n";

try {
    // Obtener ventas de Logicware para octubre 2025
    $logicwareService = app(LogicwareApiService::class);
    $salesData = $logicwareService->getSales('2025-10-01', '2025-10-31', false);
    
    if (!isset($salesData['data']) || !is_array($salesData['data'])) {
        echo "Error: Formato de respuesta inválido de Logicware\n";
        var_dump($salesData);
        exit;
    }
    
    $allSales = $salesData['data'];
    echo "Total ventas en Logicware (Octubre 2025): " . count($allSales) . "\n\n";
    
    // Contar por asesor
    $advisorCounts = [];
    $tavaraTotal = 0;
    $tavaraCode = null;
    
    foreach ($allSales as $sale) {
        // Buscar el advisor code en diferentes posibles nombres de campo
        $advisorCode = $sale['adviserCode'] ?? $sale['advisorCode'] ?? $sale['sellerCode'] ?? null;
        $advisorName = $sale['adviserName'] ?? $sale['advisorName'] ?? $sale['sellerName'] ?? 'Desconocido';
        
        if ($advisorCode) {
            if (!isset($advisorCounts[$advisorCode])) {
                $advisorCounts[$advisorCode] = [
                    'name' => $advisorName,
                    'count' => 0
                ];
            }
            $advisorCounts[$advisorCode]['count']++;
            
            // Detectar Luis Tavara
            if (stripos($advisorName, 'tavara') !== false) {
                $tavaraTotal++;
                $tavaraCode = $advisorCode;
            }
        }
    }
    
    // Mostrar top asesores
    arsort($advisorCounts);
    echo "TOP 10 ASESORES EN LOGICWARE:\n";
    echo str_repeat("=", 70) . "\n";
    $i = 1;
    foreach (array_slice($advisorCounts, 0, 10, true) as $code => $info) {
        $marker = stripos($info['name'], 'tavara') !== false ? " <-- LUIS TAVARA" : "";
        echo sprintf("%2d. %-15s %-35s %3d ventas%s\n", 
            $i++, $code, substr($info['name'], 0, 35), $info['count'], $marker);
    }
    
    if ($tavaraCode) {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "LUIS TAVARA:\n";
        echo "  Código en Logicware: $tavaraCode\n";
        echo "  Ventas en Logicware: $tavaraTotal\n\n";
        
        // Buscar en nuestra BD usando el código del empleado
        $employee = DB::table('employees')
            ->where('employee_code', $tavaraCode)
            ->first();
        
        if ($employee) {
            $dbCount = DB::table('contracts')
                ->where('advisor_id', $employee->employee_id)
                ->whereMonth('contract_date', 10)
                ->whereYear('contract_date', 2025)
                ->where('status', 'vigente')
                ->where('financing_amount', '>', 0)
                ->count();
            
            echo "  ID en nuestra BD: {$employee->employee_id}\n";
            echo "  Nombre en BD: {$employee->first_name} {$employee->last_name}\n";
            echo "  Ventas en BD: $dbCount\n\n";
            
            echo "DIFERENCIA: " . ($dbCount - $tavaraTotal) . " contratos\n";
            
            if ($dbCount > $tavaraTotal) {
                echo "\n⚠️  LA BASE DE DATOS TIENE " . ($dbCount - $tavaraTotal) . " VENTAS MÁS QUE LOGICWARE\n";
            } elseif ($dbCount < $tavaraTotal) {
                echo "\n⚠️  LOGICWARE TIENE " . ($tavaraTotal - $dbCount) . " VENTAS MÁS QUE LA BASE DE DATOS\n";
            } else {
                echo "\n✅ COINCIDEN PERFECTAMENTE\n";
            }
        } else {
            echo "  ⚠️  No se encontró empleado con código $tavaraCode en la BD\n";
        }
    } else {
        echo "\n⚠️  No se encontró a Luis Tavara en los datos de Logicware\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
