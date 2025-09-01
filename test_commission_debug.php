<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;

echo "=== Debug de Creación de Comisiones ===\n\n";

try {
    // Verificar contratos existentes con financing_amount > 0
    echo "🔍 Buscando contratos con financiamiento...\n";
    $contracts = Contract::with('advisor')
        ->where('financing_amount', '>', 0)
        ->whereNotNull('advisor_id')
        ->where('status', 'vigente')
        ->orderBy('sign_date', 'desc')
        ->limit(5)
        ->get();
    
    if ($contracts->isEmpty()) {
        echo "❌ No se encontraron contratos con financiamiento\n";
        exit(1);
    }
    
    echo "✅ Encontrados {$contracts->count()} contratos con financiamiento:\n";
    foreach ($contracts as $contract) {
        echo "   - {$contract->contract_number}: $" . number_format($contract->financing_amount, 2) . " ({$contract->sign_date})\n";
    }
    
    // Tomar el primer contrato para la prueba
    $testContract = $contracts->first();
    $signDate = $testContract->sign_date;
    $month = date('n', strtotime($signDate));
    $year = date('Y', strtotime($signDate));
    
    echo "\n🎯 Usando contrato: {$testContract->contract_number}\n";
    echo "   - Fecha firma: {$signDate} (mes: {$month}, año: {$year})\n";
    echo "   - Asesor ID: {$testContract->advisor_id}\n";
    echo "   - Monto financiamiento: $" . number_format($testContract->financing_amount, 2) . "\n";
    
    // Verificar si ya existen comisiones para este contrato
    $existingCommissions = Commission::where('contract_id', $testContract->contract_id)
        ->where('period_month', $month)
        ->where('period_year', $year)
        ->where('employee_id', $testContract->advisor_id)
        ->get();
    
    echo "\n📊 Comisiones existentes para este contrato/período: {$existingCommissions->count()}\n";
    if ($existingCommissions->count() > 0) {
        foreach ($existingCommissions as $commission) {
            echo "   - ID: {$commission->commission_id}, Tipo: {$commission->commission_type}, Pagable: " . ($commission->is_payable ? 'Sí' : 'No') . "\n";
        }
    }
    
    // Crear instancia del servicio de comisiones
    $commissionService = app(CommissionService::class);
    
    // Intentar procesar comisiones para el período del contrato
    echo "\n🔄 Procesando comisiones para {$month}/{$year}...\n";
    $result = $commissionService->processCommissionsForPeriod($month, $year);
    
    if (!empty($result)) {
        echo "✅ ¡Comisiones procesadas exitosamente!\n";
        echo "   - Total comisiones creadas: " . count($result) . "\n";
        
        foreach ($result as $commission) {
            echo "   - ID: {$commission->commission_id}";
            echo ", Tipo: {$commission->commission_type}";
            echo ", Monto: $" . number_format($commission->commission_amount, 2);
            echo ", Pagable: " . ($commission->is_payable ? 'Sí' : 'No');
            echo ", Padre: " . ($commission->parent_commission_id ?? 'N/A');
            echo "\n";
        }
    } else {
        echo "❌ No se crearon comisiones\n";
        echo "\n🔍 Verificando condiciones...\n";
        
        // Verificar condiciones una por una
        echo "   - Contrato tiene asesor: " . ($testContract->advisor ? 'Sí' : 'No') . "\n";
        echo "   - Financing amount > 0: " . ($testContract->financing_amount > 0 ? 'Sí' : 'No') . "\n";
        echo "   - Status vigente: " . ($testContract->status === 'vigente' ? 'Sí' : 'No') . "\n";
        echo "   - Advisor ID no nulo: " . ($testContract->advisor_id ? 'Sí' : 'No') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "   Error anterior: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n=== Fin del debug ===\n";