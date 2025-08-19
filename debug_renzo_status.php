<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;

echo "=== VERIFICACIÓN DE STATUS DE CONTRATOS DE RENZO ===\n\n";

// Buscar a Renzo
$renzo = Employee::with('user')
                ->whereHas('user', function($query) {
                    $query->where('first_name', 'LIKE', '%RENZO%')
                          ->orWhere('last_name', 'LIKE', '%RENZO%');
                })
                ->first();

if (!$renzo) {
    echo "❌ No se encontró a Renzo\n";
    exit(1);
}

echo "✅ Renzo encontrado: {$renzo->user->first_name} {$renzo->user->last_name} (ID: {$renzo->employee_id})\n\n";

// Período actual
$month = 6; // Junio
$year = 2025;

echo "📅 Período: {$month}/{$year}\n\n";

// Obtener TODOS los contratos de Renzo en el período (sin filtro de status)
$allContracts = Contract::where('advisor_id', $renzo->employee_id)
    ->whereMonth('sign_date', $month)
    ->whereYear('sign_date', $year)
    ->with(['reservation.lot', 'reservation.client'])
    ->orderBy('sign_date')
    ->get();

echo "📋 TODOS los contratos del período: {$allContracts->count()}\n\n";

foreach ($allContracts as $index => $contract) {
    $num = $index + 1;
    echo "--- Contrato #{$num} ---\n";
    echo "ID: {$contract->contract_id}\n";
    echo "Número: {$contract->contract_number}\n";
    echo "Status: {$contract->status}\n";
    echo "Monto financiamiento: S/ " . number_format($contract->financing_amount ?? 0, 2) . "\n";
    echo "Plazo: {$contract->term_months} meses\n";
    echo "Fecha firma: {$contract->sign_date}\n";
    
    // Verificar si cumple criterios para comisión
    $isEligible = $contract->status === 'vigente' && 
                  !is_null($contract->financing_amount) && 
                  $contract->financing_amount > 0;
    
    echo "¿Elegible para comisión?: " . ($isEligible ? "✅ SÍ" : "❌ NO") . "\n";
    
    if (!$isEligible) {
        $reasons = [];
        if ($contract->status !== 'vigente') {
            $reasons[] = "Status no es 'vigente' (actual: '{$contract->status}')";
        }
        if (is_null($contract->financing_amount)) {
            $reasons[] = "financing_amount es NULL";
        }
        if ($contract->financing_amount <= 0) {
            $reasons[] = "financing_amount <= 0";
        }
        echo "Razones: " . implode(', ', $reasons) . "\n";
    }
    
    echo "\n";
}

// Contar contratos elegibles
$eligibleContracts = $allContracts->filter(function($contract) {
    return $contract->status === 'vigente' && 
           !is_null($contract->financing_amount) && 
           $contract->financing_amount > 0;
});

echo "=== RESUMEN ===\n";
echo "Total contratos en el período: {$allContracts->count()}\n";
echo "Contratos elegibles para comisión: {$eligibleContracts->count()}\n";
echo "Contratos NO elegibles: " . ($allContracts->count() - $eligibleContracts->count()) . "\n\n";

// Mostrar distribución por status
echo "=== DISTRIBUCIÓN POR STATUS ===\n";
$statusCounts = $allContracts->groupBy('status')->map->count();
foreach ($statusCounts as $status => $count) {
    echo "- {$status}: {$count}\n";
}

echo "\n=== FIN VERIFICACIÓN ===\n";