<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Repositories\EmployeeRepository;

echo "=== CORRECCIÓN DE COMISIONES DE RENZO ===\n\n";

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

// Obtener todas las comisiones de Renzo con porcentaje incorrecto (0.02%)
$incorrectCommissions = Commission::where('employee_id', $renzo->employee_id)
                                  ->where('commission_percentage', 0.02)
                                  ->get();

echo "📊 Comisiones con porcentaje incorrecto (0.02%): " . $incorrectCommissions->count() . "\n\n";

if ($incorrectCommissions->isEmpty()) {
    echo "✅ No hay comisiones que corregir\n";
    exit(0);
}

$commissionService = new CommissionService(
    new \Modules\HumanResources\Repositories\CommissionRepository(new Commission()),
    new EmployeeRepository(new Employee())
);

foreach ($incorrectCommissions as $commission) {
    echo "--- Corrigiendo Comisión ID: {$commission->commission_id} ---\n";
    
    // Obtener el contrato asociado
    $contract = Contract::find($commission->contract_id);
    if (!$contract) {
        echo "❌ No se encontró el contrato {$commission->contract_id}\n";
        continue;
    }
    
    echo "Contrato: {$contract->contract_number}\n";
    echo "Plazo: {$contract->term_months} meses\n";
    echo "Ventas count actual: {$commission->sales_count}\n";
    
    // Determinar el porcentaje correcto basado en las ventas y plazo
    $salesCount = $commission->sales_count;
    $termMonths = $contract->term_months;
    
    // Usar la misma lógica de getCommissionRate
    $isShortTerm = in_array($termMonths, [12, 24, 36]);
    
    if ($salesCount >= 10) {
        $correctRate = $isShortTerm ? 4.20 : 3.00;
    } elseif ($salesCount >= 8) {
        $correctRate = $isShortTerm ? 4.00 : 2.50;
    } elseif ($salesCount >= 6) {
        $correctRate = $isShortTerm ? 3.00 : 1.50;
    } else {
        $correctRate = $isShortTerm ? 2.00 : 1.00;
    }
    
    echo "Porcentaje actual: {$commission->commission_percentage}%\n";
    echo "Porcentaje correcto: {$correctRate}%\n";
    
    // Calcular el nuevo monto de comisión
    $baseAmount = $commission->sale_amount;
    $newCommissionAmount = $baseAmount * ($correctRate / 100);
    
    echo "Monto actual: S/ " . number_format($commission->commission_amount, 2) . "\n";
    echo "Monto correcto: S/ " . number_format($newCommissionAmount, 2) . "\n";
    
    // Actualizar la comisión
    $commission->commission_percentage = $correctRate;
    $commission->commission_amount = round($newCommissionAmount, 2);
    
    // Si es una comisión dividida, ajustar el monto proporcionalmente
    if ($commission->payment_percentage && $commission->payment_percentage < 100) {
        $proportionalAmount = ($newCommissionAmount * $commission->payment_percentage) / 100;
        $commission->commission_amount = round($proportionalAmount, 2);
        echo "Monto ajustado por división ({$commission->payment_percentage}%): S/ " . number_format($commission->commission_amount, 2) . "\n";
    }
    
    // Actualizar también el total_commission_amount si existe
    if ($commission->total_commission_amount) {
        $commission->total_commission_amount = round($newCommissionAmount, 2);
    }
    
    $commission->save();
    
    echo "✅ Comisión corregida\n\n";
}

echo "=== CORRECCIÓN COMPLETADA ===\n";
echo "Total comisiones corregidas: " . $incorrectCommissions->count() . "\n";

// Verificar el resultado
echo "\n=== VERIFICACIÓN POST-CORRECCIÓN ===\n";
$updatedCommissions = Commission::where('employee_id', $renzo->employee_id)
                                ->orderBy('created_at', 'desc')
                                ->take(5)
                                ->get();

foreach ($updatedCommissions as $comm) {
    echo "ID: {$comm->commission_id} - Porcentaje: {$comm->commission_percentage}% - Monto: S/ " . number_format($comm->commission_amount, 2) . "\n";
}

echo "\n=== FIN CORRECCIÓN ===\n";