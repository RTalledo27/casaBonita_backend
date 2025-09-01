<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;

echo "=== Prueba del Sistema de Verificación de Comisiones Divididas ===\n\n";

// Obtener comisiones divididas que requieren verificación
$commissions = Commission::where('requires_client_payment_verification', true)
    ->whereNotNull('payment_part')
    ->take(3)
    ->get();

echo "Comisiones divididas encontradas: " . $commissions->count() . "\n\n";

if ($commissions->count() > 0) {
    $verificationService = new CommissionPaymentVerificationService();
    
    foreach ($commissions as $commission) {
        echo "--- Comisión ID: {$commission->commission_id} ---\n";
        echo "Payment Part: {$commission->payment_part}\n";
        echo "Contract ID: {$commission->contract_id}\n";
        echo "Estado actual: {$commission->payment_verification_status}\n";
        echo "Elegible para pago: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
        
        try {
            // Ejecutar verificación
            $result = $verificationService->verifyClientPayments($commission);
            
            echo "Resultado de verificación:\n";
            echo "- Primera cuota verificada: " . ($result['first_payment'] ? 'Sí' : 'No') . "\n";
            echo "- Segunda cuota verificada: " . ($result['second_payment'] ? 'Sí' : 'No') . "\n";
            echo "- Payment part considerado: {$result['payment_part']}\n";
            echo "- Mensaje: {$result['message']}\n";
            
            // Recargar comisión para ver cambios
            $commission->refresh();
            echo "Estado después de verificación: {$commission->payment_verification_status}\n";
            echo "Elegible para pago después: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
            
        } catch (Exception $e) {
            echo "Error en verificación: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
} else {
    echo "No se encontraron comisiones divididas para probar.\n";
}

echo "=== Fin de la prueba ===\n";