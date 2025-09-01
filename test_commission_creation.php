<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Models\Employee;
use Illuminate\Support\Facades\DB;

echo "=== Prueba de Creación de Comisiones ===\n\n";

try {
    // Obtener un contrato existente
    $contract = Contract::where('financing_amount', '>', 0)
        ->first();
    
    if (!$contract) {
        echo "❌ No se encontró ningún contrato con Financial Template\n";
        exit(1);
    }
    
    echo "✅ Contrato encontrado: {$contract->contract_number}\n";
    echo "   - Financing Amount: {$contract->financing_amount}\n";
    
    // Obtener un empleado existente
    $employee = Employee::first();
    
    if (!$employee) {
        echo "❌ No se encontró ningún empleado\n";
        exit(1);
    }
    
    echo "✅ Empleado encontrado: {$employee->first_name} {$employee->last_name}\n";
    
    // Crear instancia del servicio de comisiones usando el contenedor de Laravel
    $commissionService = app(CommissionService::class);
    
    // Intentar crear comisiones para el período
    echo "\n🔄 Intentando procesar comisiones para junio 2025...\n";

    $result = $commissionService->processCommissionsForPeriod(6, 2025);
    
    if ($result) {
        echo "✅ ¡Comisión creada exitosamente!\n";
        echo "   - Commission ID: {$result->commission_id}\n";
        echo "   - Amount: {$result->commission_amount}\n";
        echo "   - Sale Amount: {$result->sale_amount}\n";
        echo "   - Commission Type: {$result->commission_type}\n";
    } else {
        echo "❌ No se pudo crear la comisión\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "   Error anterior: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n=== Fin de la prueba ===\n";