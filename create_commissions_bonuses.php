<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Bonus;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;

echo "=== CREANDO COMISIONES Y BONOS ===\n";

// Buscar el contrato del empleado 1
$contract = Contract::where('advisor_id', 1)->first();

if ($contract) {
    echo "Contrato encontrado: {$contract->contract_number}\n";
    
    // Verificar si ya existe una comisión
    $existingCommission = Commission::where('employee_id', 1)
        ->where('contract_id', $contract->contract_id)
        ->where('period_month', 7)
        ->where('period_year', 2025)
        ->first();
    
    if (!$existingCommission) {
        // Crear comisión
        $commissionAmount = $contract->total_price * 0.05; // 5% de comisión
        
        $commission = Commission::create([
            'employee_id' => 1,
            'contract_id' => $contract->contract_id,
            'commission_type' => 'sale',
            'sale_amount' => $contract->total_price,
            'commission_percentage' => 5.0,
            'commission_amount' => $commissionAmount,
            'period_month' => 7,
            'period_year' => 2025,
            'payment_status' => 'pendiente'
        ]);
        
        echo "Comisión creada: S/ {$commission->commission_amount}\n";
    } else {
        echo "Comisión ya existe: S/ {$existingCommission->commission_amount}\n";
    }
} else {
    echo "No se encontró contrato para el empleado 1\n";
}

// Crear bono por logro de meta individual
$employee = Employee::find(1);
if ($employee) {
    $existingBonus = Bonus::where('employee_id', 1)
        ->where('period_month', 7)
        ->where('period_year', 2025)
        ->first();
    
    if (!$existingBonus) {
        // Calcular logro de meta (75% según el dashboard)
        $achievementPercentage = 75;
        $bonusAmount = 0;
        
        // Bono por logro de meta individual
        if ($achievementPercentage >= 70) {
            $bonusAmount = 500; // Bono base por buen rendimiento
        }
        
        if ($bonusAmount > 0) {
            $bonus = Bonus::create([
                'employee_id' => 1,
                'bonus_type_id' => 1, // Asumiendo que existe un tipo de bono
                'bonus_name' => 'Bono por Meta Individual',
                'bonus_amount' => $bonusAmount,
                'target_amount' => $employee->individual_goal,
                'achieved_amount' => $contract->total_price,
                'achievement_percentage' => $achievementPercentage,
                'period_month' => 7,
                'period_year' => 2025,
                'payment_status' => 'pendiente'
            ]);
            
            echo "Bono creado: S/ {$bonus->bonus_amount}\n";
        }
    } else {
        echo "Bono ya existe: S/ {$existingBonus->bonus_amount}\n";
    }
}

// Verificar totales
$employee = Employee::find(1);
$monthlyCommissions = $employee->calculateMonthlyCommissions(7, 2025);
$monthlyBonuses = $employee->calculateMonthlyBonuses(7, 2025);

echo "\n=== RESUMEN FINAL ===\n";
echo "Empleado: {$employee->full_name}\n";
echo "Comisiones del mes: S/ $monthlyCommissions\n";
echo "Bonos del mes: S/ $monthlyBonuses\n";
echo "Salario base: S/ {$employee->base_salary}\n";
echo "Total estimado: S/ " . ($employee->base_salary + $monthlyCommissions + $monthlyBonuses) . "\n";

echo "\n=== PROCESO COMPLETADO ===\n";