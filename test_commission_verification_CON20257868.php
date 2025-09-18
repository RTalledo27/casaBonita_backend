<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Modules\HumanResources\Services\CommissionPaymentVerificationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== Prueba de verificación para contrato CON20257868 ===\n";
echo "Fecha: " . now()->format('Y-m-d H:i:s') . "\n\n";

try {
    // Buscar el contrato por contract_number
    $contract = Contract::where('contract_number', 'CON20257868')->first();
    
    if (!$contract) {
        echo "✗ No se encontró el contrato CON20257868\n";
        exit(1);
    }
    
    echo "✓ Contrato encontrado:\n";
    echo "  - ID: {$contract->id}\n";
    echo "  - Número: {$contract->contract_number}\n\n";
    
    // Buscar comisiones relacionadas con este contrato
    $commissions = Commission::where('contract_id', $contract->id)->get();
    
    echo "Comisiones encontradas: " . $commissions->count() . "\n\n";
    
    if ($commissions->count() === 0) {
        echo "✗ No se encontraron comisiones para este contrato\n";
        exit(1);
    }
    
    $verificationService = new CommissionPaymentVerificationService();
    
    foreach ($commissions as $commission) {
        echo "--- Comisión ID: {$commission->id} ---\n";
        echo "Contract ID: {$commission->contract_id}\n";
        echo "Payment Part: " . ($commission->payment_part ?? 'N/A') . "\n";
        echo "Requiere verificación: " . ($commission->requires_client_payment_verification ? 'Sí' : 'No') . "\n";
        echo "Estado actual: {$commission->payment_verification_status}\n";
        echo "Elegible para pago: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
        
        // Si no requiere verificación, debería estar marcada como fully_verified y elegible
        if (!$commission->requires_client_payment_verification) {
            if ($commission->payment_verification_status === 'fully_verified' && $commission->is_eligible_for_payment) {
                echo "✓ Estado correcto: No requiere verificación y está marcada como verificada y elegible\n";
            } else {
                echo "⚠ Estado incorrecto: No requiere verificación pero no está correctamente marcada\n";
                echo "  Ejecutando verificación para corregir...\n";
                
                try {
                    $result = $verificationService->verifyClientPayments($commission);
                    echo "  ✓ Verificación ejecutada: " . $result['message'] . "\n";
                    
                    // Recargar la comisión para ver los cambios
                    $commission->refresh();
                    echo "  Nuevo estado: {$commission->payment_verification_status}\n";
                    echo "  Nuevo elegible: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
                } catch (Exception $e) {
                    echo "  ✗ Error en verificación: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "ℹ Esta comisión requiere verificación de pagos del cliente\n";
            
            try {
                $result = $verificationService->verifyClientPayments($commission);
                echo "Resultado de verificación: " . $result['message'] . "\n";
                echo "Primera cuota: " . ($result['first_payment'] ? 'Verificada' : 'Pendiente') . "\n";
                echo "Segunda cuota: " . ($result['second_payment'] ? 'Verificada' : 'Pendiente') . "\n";
                
                // Recargar la comisión para ver los cambios
                $commission->refresh();
                echo "Estado final: {$commission->payment_verification_status}\n";
                echo "Elegible final: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
            } catch (Exception $e) {
                echo "✗ Error en verificación: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    echo "=== Resumen de la prueba ===\n";
    echo "Contrato: CON20257868 (ID: {$contract->id})\n";
    echo "Total de comisiones: " . $commissions->count() . "\n";
    
    $commissionsNoVerification = $commissions->where('requires_client_payment_verification', false);
    $commissionsWithVerification = $commissions->where('requires_client_payment_verification', true);
    
    echo "Comisiones sin verificación requerida: " . $commissionsNoVerification->count() . "\n";
    echo "Comisiones con verificación requerida: " . $commissionsWithVerification->count() . "\n";
    
    // Verificar que todas las comisiones sin verificación estén correctamente marcadas
    $correctlyMarked = $commissionsNoVerification->where('payment_verification_status', 'fully_verified')
                                                 ->where('is_eligible_for_payment', true)
                                                 ->count();
    
    echo "Comisiones sin verificación correctamente marcadas: {$correctlyMarked}/{$commissionsNoVerification->count()}\n";
    
    if ($correctlyMarked === $commissionsNoVerification->count()) {
        echo "✓ Todas las comisiones sin verificación están correctamente marcadas\n";
    } else {
        echo "⚠ Algunas comisiones sin verificación no están correctamente marcadas\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error en la prueba: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nPrueba completada.\n";