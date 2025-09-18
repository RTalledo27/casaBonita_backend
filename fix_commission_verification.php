<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

echo "=== Script para corregir comisiones con verificación incorrecta ===\n";
echo "Fecha: " . now()->format('Y-m-d H:i:s') . "\n\n";

try {
    DB::beginTransaction();
    
    // Buscar comisiones que no requieren verificación pero no están marcadas como elegibles
    $commissionsToFix = Commission::where('requires_client_payment_verification', false)
        ->where(function($query) {
            $query->where('is_eligible_for_payment', false)
                  ->orWhere('payment_verification_status', '!=', 'fully_verified')
                  ->orWhereNull('payment_verification_status');
        })
        ->get();
    
    echo "Comisiones encontradas que necesitan corrección: " . $commissionsToFix->count() . "\n\n";
    
    if ($commissionsToFix->count() === 0) {
        echo "No se encontraron comisiones que necesiten corrección.\n";
        DB::rollBack();
        exit(0);
    }
    
    $fixedCount = 0;
    $errorCount = 0;
    
    foreach ($commissionsToFix as $commission) {
        try {
            echo "Procesando comisión ID: {$commission->id}\n";
            echo "  - Contract ID: {$commission->contract_id}\n";
            echo "  - Estado actual: {$commission->payment_verification_status}\n";
            echo "  - Elegible actual: " . ($commission->is_eligible_for_payment ? 'Sí' : 'No') . "\n";
            echo "  - Requiere verificación: " . ($commission->requires_client_payment_verification ? 'Sí' : 'No') . "\n";
            
            // Actualizar la comisión
            $commission->update([
                'payment_verification_status' => 'fully_verified',
                'is_eligible_for_payment' => true,
                'verification_notes' => 'Comisión corregida automáticamente - No requiere verificación de pagos del cliente. Corrección aplicada el ' . now()->format('d/m/Y H:i:s')
            ]);
            
            echo "  ✓ Comisión corregida exitosamente\n\n";
            $fixedCount++;
            
            // Log de la corrección
            Log::info('Comisión corregida por script de reparación', [
                'commission_id' => $commission->id,
                'contract_id' => $commission->contract_id,
                'previous_status' => $commission->getOriginal('payment_verification_status'),
                'previous_eligible' => $commission->getOriginal('is_eligible_for_payment'),
                'new_status' => 'fully_verified',
                'new_eligible' => true,
                'script_execution_time' => now()->toISOString()
            ]);
            
        } catch (Exception $e) {
            echo "  ✗ Error al procesar comisión ID {$commission->id}: " . $e->getMessage() . "\n\n";
            $errorCount++;
            
            Log::error('Error en script de corrección de comisiones', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    echo "=== Resumen de la corrección ===\n";
    echo "Comisiones procesadas: " . $commissionsToFix->count() . "\n";
    echo "Comisiones corregidas exitosamente: {$fixedCount}\n";
    echo "Errores encontrados: {$errorCount}\n\n";
    
    if ($errorCount === 0) {
        DB::commit();
        echo "✓ Todas las correcciones se aplicaron exitosamente.\n";
        echo "Las comisiones ahora están marcadas como 'fully_verified' y elegibles para pago.\n";
    } else {
        echo "⚠ Se encontraron errores. Revise los logs para más detalles.\n";
        echo "¿Desea continuar con las correcciones exitosas? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $response = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
            DB::commit();
            echo "✓ Correcciones aplicadas (con algunos errores).\n";
        } else {
            DB::rollBack();
            echo "✗ Correcciones canceladas. No se realizaron cambios.\n";
        }
    }
    
} catch (Exception $e) {
    DB::rollBack();
    echo "✗ Error crítico en el script: " . $e->getMessage() . "\n";
    Log::error('Error crítico en script de corrección de comisiones', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

echo "\nScript completado.\n";