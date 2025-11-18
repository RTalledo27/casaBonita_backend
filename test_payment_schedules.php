<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LogicwareApiService;
use Illuminate\Support\Facades\Log;

$correlative = '202511-000000596'; // Ejemplo con descuento

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🧪 TEST: Endpoint de Cronograma de Pagos\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $logicwareService = new LogicwareApiService();
    
    echo "📋 Correlativo: {$correlative}\n\n";
    
    // Generar token
    echo "🔐 Generando Bearer Token...\n";
    $token = $logicwareService->generateToken(true);
    echo "✅ Token obtenido\n\n";
    
    // Llamar al método getPaymentSchedule
    echo "📅 Consultando cronograma de pagos...\n";
    $schedule = $logicwareService->getPaymentSchedule($correlative);
    
    if (empty($schedule)) {
        echo "❌ No se obtuvo cronograma\n";
        exit(1);
    }
    
    echo "✅ Cronograma obtenido\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 ESTRUCTURA COMPLETA:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Analizar estructura
    if (isset($schedule['data']) && is_array($schedule['data'])) {
        $schedules = $schedule['data'];
        $totalCuotas = count($schedules);
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 ANÁLISIS DEL CRONOGRAMA:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "Total de cuotas: {$totalCuotas}\n\n";
        
        $cuotasPagadas = 0;
        $cuotasPendientes = 0;
        $totalPagado = 0;
        $totalPendiente = 0;
        
        // Mostrar estructura del primer elemento para ver qué campos tiene
        if ($totalCuotas > 0) {
            echo "🔍 CAMPOS DISPONIBLES EN CADA CUOTA:\n";
            $primeraCuota = $schedules[0];
            foreach ($primeraCuota as $campo => $valor) {
                $tipo = gettype($valor);
                $valorMuestra = is_array($valor) ? '[array]' : (is_string($valor) ? "\"$valor\"" : $valor);
                echo "  - {$campo}: {$tipo} = {$valorMuestra}\n";
            }
            echo "\n";
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📋 DETALLE DE CUOTAS:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        foreach ($schedules as $index => $sch) {
            $numero = $index + 1;
            
            // Intentar detectar campos comunes
            $monto = $sch['amount'] ?? $sch['monto'] ?? $sch['total'] ?? 0;
            $pagado = $sch['paid'] ?? $sch['pagado'] ?? $sch['amountPaid'] ?? 0;
            $saldo = $sch['balance'] ?? $sch['saldo'] ?? $sch['pending'] ?? ($monto - $pagado);
            $fechaVencimiento = $sch['dueDate'] ?? $sch['vencimiento'] ?? $sch['fecha'] ?? 'N/A';
            $estado = $sch['status'] ?? $sch['estado'] ?? 'N/A';
            $numero_cuota = $sch['installmentNumber'] ?? $sch['numeroCuota'] ?? $numero;
            
            $isPagada = $pagado >= $monto || strtolower($estado) === 'paid' || strtolower($estado) === 'pagado' || $saldo == 0;
            
            if ($isPagada) {
                $cuotasPagadas++;
                $totalPagado += $monto;
                $estadoIcon = '✅ PAGADA';
            } else {
                $cuotasPendientes++;
                $totalPendiente += $saldo;
                $estadoIcon = '⏳ PENDIENTE';
            }
            
            echo "Cuota #{$numero_cuota} {$estadoIcon}\n";
            echo "  Monto: S/ " . number_format($monto, 2) . "\n";
            echo "  Pagado: S/ " . number_format($pagado, 2) . "\n";
            echo "  Saldo: S/ " . number_format($saldo, 2) . "\n";
            echo "  Vencimiento: {$fechaVencimiento}\n";
            echo "  Estado: {$estado}\n";
            echo "\n";
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📈 RESUMEN:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ Cuotas pagadas: {$cuotasPagadas}\n";
        echo "⏳ Cuotas pendientes: {$cuotasPendientes}\n";
        echo "💰 Total pagado: S/ " . number_format($totalPagado, 2) . "\n";
        echo "💸 Total pendiente: S/ " . number_format($totalPendiente, 2) . "\n\n";
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "💡 CAMPOS PARA INTEGRACIÓN:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "Para marcar cuotas como pagadas necesitamos:\n";
        echo "  1. Campo que identifique el monto de la cuota\n";
        echo "  2. Campo que identifique el monto pagado\n";
        echo "  3. Campo que identifique el estado (paid/pending)\n";
        echo "  4. Campo de fecha de vencimiento\n";
        echo "  5. Campo de número de cuota\n\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
