<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\Contract;
use Modules\Lots\Models\LotFinancialTemplate;
use Modules\HumanResources\Services\CommissionService;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG: CÁLCULO DE COMISIONES ===\n\n";

// Buscar empleado DANIELA AIRAM usando la relación con User
$daniela = Employee::with('user')
                  ->whereHas('user', function($query) {
                      $query->where('first_name', 'LIKE', '%DANIELA%')
                            ->where('last_name', 'LIKE', '%AIRAM%')
                            ->orWhere('first_name', 'LIKE', '%AIRAM%');
                  })
                  ->first();

if (!$daniela) {
    echo "❌ No se encontró empleado DANIELA AIRAM\n";
    // Buscar empleados similares
    $employees = Employee::with('user')
                        ->whereHas('user', function($query) {
                            $query->where('first_name', 'LIKE', '%DANIEL%')
                                  ->orWhere('last_name', 'LIKE', '%AIRAM%');
                        })
                        ->get();
    echo "Empleados encontrados:\n";
    foreach ($employees as $emp) {
        $userName = $emp->user ? "{$emp->user->first_name} {$emp->user->last_name}" : 'Sin usuario';
        echo "- ID: {$emp->employee_id}, Nombre: {$userName}\n";
    }
    exit;
}

$userName = $daniela->user ? "{$daniela->user->first_name} {$daniela->user->last_name}" : 'Sin usuario';
echo "✅ Empleado encontrado: {$userName} (ID: {$daniela->employee_id})\n\n";

// Obtener contratos de DANIELA en junio 2025
$contracts = Contract::where('advisor_id', $daniela->employee_id)
                    ->whereMonth('sign_date', 6)
                    ->whereYear('sign_date', 2025)
                    ->where('status', 'vigente')
                    ->with(['lot.financialTemplate'])
                    ->get();

echo "📋 Contratos de DANIELA en Junio 2025: {$contracts->count()}\n";

if ($contracts->isEmpty()) {
    echo "❌ No se encontraron contratos para DANIELA en junio 2025\n";
    exit;
}

$totalExpectedCommission = 0;
$commissionService = new CommissionService(
    app(Modules\HumanResources\Repositories\CommissionRepository::class),
    app(Modules\HumanResources\Repositories\EmployeeRepository::class)
);

foreach ($contracts as $index => $contract) {
    echo "\n--- CONTRATO " . ($index + 1) . " ---\n";
    echo "ID Contrato: {$contract->contract_id}\n";
    echo "Fecha Firma: {$contract->sign_date}\n";
    echo "Monto Financiamiento: S/ " . number_format($contract->financing_amount, 2) . "\n";
    echo "Plazo: {$contract->term_months} meses\n";
    
    // Verificar si tiene template financiero (usando lotFinancialTemplate)
    if ($contract->lot && $contract->lot->lotFinancialTemplate) {
        $template = $contract->lot->lotFinancialTemplate;
        echo "Template Financiero: ID {$template->id}\n";
        echo "- Monto Template: S/ " . number_format($template->financing_amount, 2) . "\n";
        echo "- Plazo Template: {$template->term_months} meses\n";
    } else {
        echo "❌ Sin template financiero\n";
    }
    
    // Calcular comisión manualmente usando la misma lógica del servicio
    if ($contract->lot && $contract->lot->financialTemplate) {
        $template = $contract->lot->financialTemplate;
        $financingAmount = $template->financing_amount;
        $termMonths = $template->term_months;
        
        // Contar ventas financiadas del asesor (simulado - en producción sería una consulta)
        $salesCount = 3; // Sabemos que DANIELA tiene 3 contratos en junio
        
        // Aplicar la tabla de rangos por número de ventas
        $isShortTerm = in_array($termMonths, [12, 24, 36]);
        
        if ($salesCount >= 10) {
            $commissionRate = $isShortTerm ? 4.20 : 3.00;
        } elseif ($salesCount >= 8) {
            $commissionRate = $isShortTerm ? 4.00 : 2.50;
        } elseif ($salesCount >= 6) {
            $commissionRate = $isShortTerm ? 3.00 : 1.50;
        } else {
            $commissionRate = $isShortTerm ? 2.00 : 1.00;
        }
        
        $individualCommission = $financingAmount * ($commissionRate / 100);
        
        echo "✅ Template encontrado - Monto: S/ " . number_format($financingAmount, 2) . "\n";
        echo "📊 Tasa aplicada: {$commissionRate}% (" . ($isShortTerm ? 'corto' : 'largo') . " plazo)\n";
        echo "📈 Ventas del asesor: {$salesCount}\n";
        echo "💰 Comisión individual: S/ " . number_format($individualCommission, 2) . "\n";
    } else {
        // Fallback: cálculo manual básico al 1%
        $individualCommission = $contract->financing_amount * 0.01;
        echo "🔄 Sin template - Usando cálculo manual (1%): S/ " . number_format($individualCommission, 2) . "\n";
    }
    
    // Aplicar división según número de ventas
    if ($salesCount >= 10) {
        $firstPayment = $individualCommission * 0.70;
        $secondPayment = $individualCommission * 0.30;
        echo "División 70/30:\n";
    } else {
        $firstPayment = $individualCommission * 0.50;
        $secondPayment = $individualCommission * 0.50;
        echo "División 50/50:\n";
    }
    
    echo "- Primer pago (mes siguiente): S/ " . number_format($firstPayment, 2) . "\n";
    echo "- Segundo pago (dos meses después): S/ " . number_format($secondPayment, 2) . "\n";
    
    $totalExpectedCommission += $individualCommission;
}

echo "\n=== RESUMEN CALCULADO ===\n";
echo "Total contratos: {$contracts->count()}\n";
echo "Total comisiones esperadas: S/ " . number_format($totalExpectedCommission, 2) . "\n";
echo "Comisión promedio por contrato: S/ " . number_format($totalExpectedCommission / $contracts->count(), 2) . "\n";

// Verificar comisiones existentes en la base de datos
echo "\n=== COMISIONES EN BASE DE DATOS ===\n";
$existingCommissions = Commission::where('employee_id', $daniela->employee_id)
                                 ->where('period_month', 6)
                                 ->where('period_year', 2025)
                                 ->get();

echo "Comisiones registradas: {$existingCommissions->count()}\n";

$totalPayable = 0;
$totalParent = 0;

foreach ($existingCommissions as $commission) {
    $type = $commission->parent_commission_id ? 'HIJA' : 'PADRE';
    $payable = $commission->is_payable ? 'PAGABLE' : 'NO PAGABLE';
    
    echo "- ID: {$commission->commission_id}, Tipo: {$type}, {$payable}, Monto: S/ " . number_format($commission->commission_amount, 2) . "\n";
    
    if ($commission->is_payable) {
        $totalPayable += $commission->commission_amount;
    }
    
    if (!$commission->parent_commission_id) {
        $totalParent += $commission->commission_amount;
    }
}

echo "\nTotal comisiones PADRE: S/ " . number_format($totalParent, 2) . "\n";
echo "Total comisiones PAGABLES: S/ " . number_format($totalPayable, 2) . "\n";

echo "\n=== COMPARACIÓN CON EXCEL ===\n";
echo "Excel del usuario: S/ 3,971.26\n";
echo "Sistema calculado: S/ " . number_format($totalExpectedCommission, 2) . "\n";
echo "Sistema BD (padre): S/ " . number_format($totalParent, 2) . "\n";
echo "Sistema BD (pagable): S/ " . number_format($totalPayable, 2) . "\n";

$difference = abs(3971.26 - $totalExpectedCommission);
echo "Diferencia con Excel: S/ " . number_format($difference, 2) . "\n";

if ($difference > 10) {
    echo "❌ DIFERENCIA SIGNIFICATIVA - Revisar cálculos\n";
} else {
    echo "✅ Cálculos coinciden aproximadamente\n";
}