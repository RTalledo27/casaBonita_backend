<?php

// Inicializar Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Usar DB directamente para evitar problemas de autoloading
    $commissions = DB::table('commissions')
        ->whereIn('status', ['generated', 'partially_paid'])
        ->limit(5)
        ->get([
            'commission_id',
            'contract_id', 
            'status',
            'payment_part',
            'is_eligible_for_payment',
            'payment_verification_status',
            'requires_client_payment_verification'
        ]);
    
    echo "=== COMISIONES PARA TESTING ===\n\n";
    
    if ($commissions->count() > 0) {
        echo "ENCONTRADAS {$commissions->count()} COMISIONES EN ESTADO TESTEABLE:\n\n";
        
        foreach ($commissions as $commission) {
            echo "ID: {$commission->commission_id}\n";
            echo "  Contrato: {$commission->contract_id}\n";
            echo "  Estado: {$commission->status}\n";
            echo "  Parte de pago: {$commission->payment_part}\n";
            echo "  Elegible para pago: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
            echo "  Estado verificación: {$commission->payment_verification_status}\n";
            echo "  Requiere verificación: " . ($commission->requires_client_payment_verification ? 'Sí' : 'No') . "\n";
            echo "  ---\n";
        }
        
        $firstCommission = $commissions->first();
        echo "\n=== RECOMENDACIÓN PARA TESTING ===\n";
        echo "Usar comisión ID: {$firstCommission->commission_id}\n";
        echo "Endpoint: http://localhost:8000/api/debug-commission/payment-verification/{$firstCommission->commission_id}\n";
        
    } else {
        echo "NO SE ENCONTRARON COMISIONES EN ESTADO 'generated' O 'partially_paid'\n\n";
        
        // Mostrar algunas comisiones de referencia
        $allCommissions = DB::table('commissions')
            ->limit(5)
            ->get(['commission_id', 'status', 'payment_part', 'is_eligible_for_payment']);
            
        echo "COMISIONES DE REFERENCIA (primeras 5):\n\n";
        foreach ($allCommissions as $commission) {
            echo "ID: {$commission->commission_id} | Estado: {$commission->status} | Parte: {$commission->payment_part}\n";
        }
        
        if ($allCommissions->count() > 0) {
            $firstRef = $allCommissions->first();
            echo "\n=== SUGERENCIA ===\n";
            echo "Para crear una comisión de prueba, ejecutar:\n";
            echo "UPDATE commissions SET status = 'generated' WHERE commission_id = {$firstRef->commission_id};\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}