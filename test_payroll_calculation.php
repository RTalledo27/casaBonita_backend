<?php

/**
 * Script de prueba del PayrollCalculationService
 * 
 * Verifica que el cálculo de planillas funcione correctamente
 * usando los parámetros tributarios dinámicos.
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\TaxParameter;
use Modules\HumanResources\Services\PayrollCalculationService;

// Cargar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "🧮 PRUEBA DE CÁLCULO DE PLANILLAS\n";
echo "==================================\n\n";

try {
    // 1. Verificar parámetros tributarios
    echo "📋 1. Verificando parámetros tributarios 2025...\n";
    $taxParams = TaxParameter::getActiveForYear(2025);
    
    if (!$taxParams) {
        echo "❌ ERROR: No existen parámetros tributarios para 2025\n";
        echo "   Ejecuta primero: php update_tax_parameters_2025.php\n";
        exit(1);
    }
    
    echo "✅ Parámetros encontrados:\n";
    echo "   • UIT 2025: S/ " . number_format($taxParams->uit_amount, 2) . "\n";
    echo "   • RMV 2025: S/ " . number_format($taxParams->minimum_wage, 2) . "\n";
    echo "   • Asignación Familiar: S/ " . number_format($taxParams->family_allowance, 2) . "\n";
    echo "\n";

    // 2. Obtener empleados de prueba
    echo "👥 2. Buscando empleados para prueba...\n";
    $employees = Employee::active()->take(3)->get();
    
    if ($employees->isEmpty()) {
        echo "⚠️  No hay empleados activos en el sistema\n";
        exit(0);
    }
    
    echo "✅ Encontrados {$employees->count()} empleados\n\n";

    // 3. Calcular planillas
    $calculationService = new PayrollCalculationService();
    
    echo "💰 3. Calculando planillas...\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($employees as $employee) {
        echo "📊 EMPLEADO: {$employee->first_name} {$employee->last_name}\n";
        echo "   Código: {$employee->employee_code}\n";
        echo "   Sistema: " . ($employee->pension_system ?? 'No definido') . "\n";
        if ($employee->pension_system === 'afp') {
            echo "   AFP: " . ($employee->afp_provider ?? 'No definido') . "\n";
        }
        echo "\n";

        try {
            // Calcular planilla
            $calculation = $calculationService->calculatePayroll(
                employee: $employee,
                baseSalary: $employee->base_salary,
                commissionsAmount: 500, // Ejemplo
                bonusesAmount: 200,      // Ejemplo
                overtimeAmount: 150,     // Ejemplo
                year: 2025
            );

            // Mostrar resumen
            $summary = $calculationService->getCalculationSummary($calculation, $taxParams);
            
            echo "   💵 INGRESOS:\n";
            echo "   ├─ Salario Base:        S/ " . number_format($summary['ingresos']['salario_base'], 2) . "\n";
            echo "   ├─ Comisiones:          S/ " . number_format($summary['ingresos']['comisiones'], 2) . "\n";
            echo "   ├─ Bonos:               S/ " . number_format($summary['ingresos']['bonos'], 2) . "\n";
            echo "   ├─ Horas Extras:        S/ " . number_format($summary['ingresos']['horas_extras'], 2) . "\n";
            echo "   ├─ Asignación Familiar: S/ " . number_format($summary['ingresos']['asignacion_familiar'], 2) . "\n";
            echo "   └─ 📈 TOTAL BRUTO:      S/ " . number_format($summary['ingresos']['total_bruto'], 2) . "\n";
            echo "\n";

            echo "   📉 DESCUENTOS:\n";
            $pension = $summary['descuentos']['sistema_pensiones'];
            echo "   ├─ Sistema de Pensiones ({$pension['tipo']}):\n";
            if ($pension['tipo'] === 'afp') {
                echo "   │  ├─ AFP: {$pension['proveedor']}\n";
                echo "   │  ├─ Aporte (10%):     S/ " . number_format($pension['aporte'], 2) . "\n";
                echo "   │  ├─ Comisión:         S/ " . number_format($pension['comision'], 2) . "\n";
                echo "   │  ├─ Seguro (0.99%):   S/ " . number_format($pension['seguro'], 2) . "\n";
            } else {
                echo "   │  └─ Aporte (13%):     S/ " . number_format($pension['aporte'], 2) . "\n";
            }
            echo "   │  └─ Subtotal:         S/ " . number_format($pension['total'], 2) . "\n";
            echo "   ├─ Impuesto Renta 5ta:  S/ " . number_format($summary['descuentos']['impuesto_renta_5ta'], 2) . "\n";
            echo "   └─ 📊 TOTAL DESCUENTOS: S/ " . number_format($summary['descuentos']['total_descuentos'], 2) . "\n";
            echo "\n";

            echo "   💎 RESULTADO:\n";
            echo "   ├─ Salario Bruto:       S/ " . number_format($summary['totales']['salario_bruto'], 2) . "\n";
            echo "   ├─ (-) Descuentos:      S/ " . number_format($summary['totales']['total_descuentos'], 2) . "\n";
            echo "   └─ 🎯 SALARIO NETO:     S/ " . number_format($summary['totales']['salario_neto'], 2) . "\n";
            echo "\n";

            echo "   👔 COSTO EMPLEADOR:\n";
            echo "   ├─ EsSalud (9%):        S/ " . number_format($summary['empleador']['essalud'], 2) . "\n";
            echo "   └─ 💼 COSTO TOTAL:      S/ " . number_format($summary['empleador']['costo_total'], 2) . "\n";
            echo "\n";

            echo "✅ Cálculo exitoso\n";

        } catch (\Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
        
        echo str_repeat("-", 80) . "\n\n";
    }

    echo "✨ PRUEBA COMPLETADA EXITOSAMENTE\n\n";
    
    echo "📝 NOTAS:\n";
    echo "   • Los cálculos usan parámetros tributarios de 2025\n";
    echo "   • AFP/ONP se calcula según sistema del empleado\n";
    echo "   • Impuesto a la renta usa 5 tramos progresivos\n";
    echo "   • EsSalud (9%) lo paga el empleador\n";
    echo "   • Asignación familiar solo si tiene hijos\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR GENERAL: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
