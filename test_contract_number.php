<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Sales\Models\Contract;

echo "=== VERIFICACIÓN COMPLETA DE LA TABLA CONTRACTS ===\n";

// 1. Mostrar todas las columnas de la tabla contracts
if (Schema::hasTable('contracts')) {
    echo "✓ Tabla 'contracts' existe\n\n";
    
    echo "=== COLUMNAS EXISTENTES EN LA TABLA ===\n";
    $columns = DB::select("DESCRIBE contracts");
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type}) - Null: {$column->Null} - Default: {$column->Default}\n";
    }
    
    echo "\n=== VERIFICACIÓN DE CAMPOS REQUERIDOS ===\n";
    $requiredFields = [
        'contract_number', 'contract_date', 'total_price', 'down_payment', 
        'financing_amount', 'monthly_payment', 'term_months', 'interest_rate', 'status'
    ];
    
    foreach ($requiredFields as $field) {
        if (Schema::hasColumn('contracts', $field)) {
            echo "✓ {$field} - EXISTE\n";
        } else {
            echo "✗ {$field} - NO EXISTE\n";
        }
    }
    
    // 2. Probar creación con solo campos existentes
    echo "\n=== PRUEBA CON CAMPOS BÁSICOS ===\n";
    try {
        $contractData = [
            'client_id' => 1,
            'lot_id' => 1,
            'contract_number' => 'TEST' . time()
        ];
        
        echo "Intentando crear contrato con datos mínimos:\n";
        foreach ($contractData as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
        
        $contract = Contract::create($contractData);
        
        if ($contract) {
            echo "\n✓ Contrato creado exitosamente con ID: {$contract->contract_id}\n";
            echo "✓ Contract Number: {$contract->contract_number}\n";
            
            // Eliminar el contrato de prueba
            $contract->delete();
            echo "✓ Contrato de prueba eliminado\n";
        }
        
    } catch (Exception $e) {
        echo "\n✗ Error al crear contrato: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "✗ Tabla 'contracts' NO existe\n";
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";