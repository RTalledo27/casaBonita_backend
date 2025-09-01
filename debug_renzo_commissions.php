<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionService;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG COMISIONES DE RENZO ===\n\n";

// Buscar al asesor Renzo
$renzo = Employee::whereHas('user', function($q) {
    $q->where('first_name', 'LIKE', '%Renzo%')
      ->orWhere('last_name', 'LIKE', '%Renzo%')
      ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%Renzo%']);
})->with('user')->first();

if (!$renzo) {
    echo "❌ No se encontró al asesor Renzo\n";
    exit;
}

echo "✅ Asesor encontrado: {$renzo->user->first_name} {$renzo->user->last_name}\n";
echo "   ID: {$renzo->employee_id}\n";
echo "   Email: {$renzo->user->email}\n\n";

// Buscar contratos en diferentes períodos
echo "=== BÚSQUEDA DE CONTRATOS EN DIFERENTES PERÍODOS ===\n";

// Verificar contratos en los últimos 6 meses
$periods = [
    ['month' => 1, 'year' => 2025],
    ['month' => 12, 'year' => 2024],
    ['month' => 11, 'year' => 2024],
    ['month' => 10, 'year' => 2024],
    ['month' => 9, 'year' => 2024],
    ['month' => 8, 'year' => 2024]
];

$foundPeriod = null;
foreach ($periods as $period) {
    $count = Contract::where('advisor_id', $renzo->employee_id)
        ->whereMonth('sign_date', $period['month'])
        ->whereYear('sign_date', $period['year'])
        ->where('status', 'vigente')
        ->whereNotNull('financing_amount')
        ->where('financing_amount', '>', 0)
        ->count();
    
    echo "Período {$period['month']}/{$period['year']}: {$count} contratos\n";
    
    if ($count > 0 && !$foundPeriod) {
        $foundPeriod = $period;
    }
}

if (!$foundPeriod) {
    echo "\n⚠️  No se encontraron contratos en los últimos 6 meses\n";
    echo "Buscando contratos en todo el historial...\n\n";
    
    // Buscar todos los contratos de Renzo sin filtro de fecha
    $allContracts = Contract::where('advisor_id', $renzo->employee_id)
        ->where('status', 'vigente')
        ->whereNotNull('financing_amount')
        ->where('financing_amount', '>', 0)
        ->orderBy('sign_date', 'desc')
        ->get();
    
    if ($allContracts->count() == 0) {
        echo "❌ No se encontraron contratos financiados para Renzo en todo el historial\n";
        
        // Verificar si hay contratos sin financiamiento
        $allContractsNoFinancing = Contract::where('advisor_id', $renzo->employee_id)
            ->where('status', 'vigente')
            ->get();
        
        echo "Contratos totales (incluyendo sin financiamiento): {$allContractsNoFinancing->count()}\n";
        
        if ($allContractsNoFinancing->count() > 0) {
            echo "\n=== CONTRATOS SIN FINANCIAMIENTO ===\n";
            foreach ($allContractsNoFinancing->take(5) as $contract) {
                echo "- ID: {$contract->contract_id}, Fecha: {$contract->sign_date}, Financiamiento: " . ($contract->financing_amount ?? 'NULL') . "\n";
            }
        }
        
        exit;
    }
    
    echo "✅ Encontrados {$allContracts->count()} contratos en todo el historial\n";
    echo "Contratos por período:\n";
    
    $contractsByPeriod = $allContracts->groupBy(function($contract) {
        $date = \Carbon\Carbon::parse($contract->sign_date);
        return $date->format('Y-m');
    });
    
    foreach ($contractsByPeriod as $period => $contracts) {
        echo "- {$period}: {$contracts->count()} contratos\n";
    }
    
    // Usar el período más reciente
    $latestContract = $allContracts->first();
    $latestDate = \Carbon\Carbon::parse($latestContract->sign_date);
    $month = $latestDate->month;
    $year = $latestDate->year;
    
    echo "\nUsando período más reciente: {$month}/{$year}\n";
} else {
    $month = $foundPeriod['month'];
    $year = $foundPeriod['year'];
}
 
 echo "\n=== ANÁLISIS PERÍODO SELECCIONADO: {$month}/{$year} ===\n\n";

// Contar ventas financiadas usando la misma lógica del servicio
$salesCount = Contract::where('advisor_id', $renzo->employee_id)
    ->whereMonth('sign_date', $month)
    ->whereYear('sign_date', $year)
    ->where('status', 'vigente')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->count();

echo "📊 Total ventas financiadas del período: {$salesCount}\n\n";

// Obtener todos los contratos del período
$contracts = Contract::where('advisor_id', $renzo->employee_id)
    ->whereMonth('sign_date', $month)
    ->whereYear('sign_date', $year)
    ->where('status', 'vigente')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->with(['reservation.lot', 'reservation.client'])
    ->orderBy('sign_date')
    ->get();

echo "=== DETALLE DE CONTRATOS ===\n";

$commissionService = app(CommissionService::class);
$totalExpectedCommission = 0;

foreach ($contracts as $index => $contract) {
    echo "\n--- Contrato #" . ($index + 1) . " ---\n";
    echo "ID: {$contract->contract_id}\n";
    echo "Número: {$contract->contract_number}\n";
    echo "Fecha firma: {$contract->sign_date}\n";
    echo "Monto financiamiento: S/ " . number_format($contract->financing_amount, 2) . "\n";
    echo "Plazo: {$contract->term_months} meses\n";
    
    // Contar ventas hasta la fecha de este contrato (lógica incremental)
    $salesCountAtDate = Contract::where('advisor_id', $renzo->employee_id)
        ->whereMonth('sign_date', $month)
        ->whereYear('sign_date', $year)
        ->where('status', 'vigente')
        ->whereNotNull('financing_amount')
        ->where('financing_amount', '>', 0)
        ->where('sign_date', '<=', $contract->sign_date)
        ->count();
    
    echo "Ventas acumuladas hasta esta fecha: {$salesCountAtDate}\n";
    
    // Determinar si es plazo corto o largo
    $isShortTerm = in_array($contract->term_months, [12, 24, 36]);
    echo "Tipo plazo: " . ($isShortTerm ? 'CORTO (12/24/36)' : 'LARGO (48+)') . "\n";
    
    // Calcular tasa según la tabla de rangos
    if ($salesCountAtDate >= 10) {
        $expectedRate = $isShortTerm ? 4.20 : 3.00;
    } elseif ($salesCountAtDate >= 8) {
        $expectedRate = $isShortTerm ? 4.00 : 2.50;
    } elseif ($salesCountAtDate >= 6) {
        $expectedRate = $isShortTerm ? 3.00 : 1.50;
    } else {
        $expectedRate = $isShortTerm ? 2.00 : 1.00;
    }
    
    echo "Tasa esperada: {$expectedRate}%\n";
    
    $expectedCommission = $contract->financing_amount * ($expectedRate / 100);
    echo "Comisión esperada: S/ " . number_format($expectedCommission, 2) . "\n";
    
    $totalExpectedCommission += $expectedCommission;
    
    // Buscar comisiones existentes para este contrato
    $existingCommissions = Commission::where('contract_id', $contract->contract_id)
        ->where('employee_id', $renzo->employee_id)
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->get();
    
    if ($existingCommissions->count() > 0) {
        echo "\n📋 Comisiones registradas: {$existingCommissions->count()}\n";
        foreach ($existingCommissions as $comm) {
            echo "   - Tipo: {$comm->commission_type}\n";
            echo "   - Porcentaje: {$comm->commission_percentage}%\n";
            echo "   - Monto: S/ " . number_format($comm->commission_amount, 2) . "\n";
            echo "   - Ventas count: {$comm->sales_count}\n";
            echo "   - Es pagable: " . ($comm->is_payable ? 'SÍ' : 'NO') . "\n";
            
            // Verificar si la tasa aplicada es incorrecta
            if ($comm->commission_percentage != $expectedRate) {
                echo "   ⚠️  PROBLEMA: Se aplicó {$comm->commission_percentage}% pero debería ser {$expectedRate}%\n";
            }
        }
    } else {
        echo "\n❌ No hay comisiones registradas para este contrato\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "Total contratos analizados: {$contracts->count()}\n";
echo "Ventas totales del período: {$salesCount}\n";
echo "Comisión total esperada: S/ " . number_format($totalExpectedCommission, 2) . "\n";

// Verificar comisiones totales registradas
$totalRegisteredCommissions = Commission::where('employee_id', $renzo->employee_id)
    ->where('period_month', $month)
    ->where('period_year', $year)
    ->sum('commission_amount');

echo "Comisión total registrada: S/ " . number_format($totalRegisteredCommissions, 2) . "\n";
echo "Diferencia: S/ " . number_format($totalExpectedCommission - $totalRegisteredCommissions, 2) . "\n";

// Verificar lógica del método getCommissionRate
echo "\n=== VERIFICACIÓN LÓGICA getCommissionRate ===\n";
echo "Con {$salesCount} ventas:\n";
echo "- Plazo corto (12/24/36): " . (($salesCount >= 8) ? '4.00%' : (($salesCount >= 6) ? '3.00%' : '2.00%')) . "\n";
echo "- Plazo largo (48+): " . (($salesCount >= 8) ? '2.50%' : (($salesCount >= 6) ? '1.50%' : '1.00%')) . "\n";

echo "\n=== FIN DEBUG ===\n";