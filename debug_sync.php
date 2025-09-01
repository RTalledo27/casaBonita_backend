<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Commission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "=== Depuración de Sincronización HR ===\n\n";

try {
    echo "1. Verificando comisiones pendientes...\n";
    $pendingCommissions = Commission::where('verification_status', 'pending')->get();
    echo "Comisiones pendientes encontradas: " . $pendingCommissions->count() . "\n\n";
    
    if ($pendingCommissions->count() > 0) {
        $firstCommission = $pendingCommissions->first();
        echo "Primera comisión pendiente:\n";
        echo "ID: " . $firstCommission->commission_id . "\n";
        echo "Customer ID: " . $firstCommission->customer_id . "\n";
        echo "Period Start: " . $firstCommission->period_start . "\n";
        echo "Period End: " . $firstCommission->period_end . "\n\n";
        
        echo "2. Buscando pagos de cliente relacionados...\n";
        try {
            $customerPayments = CustomerPayment::where('client_id', $firstCommission->customer_id)
                                              ->where('payment_date', '>=', $firstCommission->period_start)
                                              ->where('payment_date', '<=', $firstCommission->period_end)
                                              ->get();
            echo "Pagos encontrados: " . $customerPayments->count() . "\n";
            
            if ($customerPayments->count() > 0) {
                echo "Primer pago:\n";
                $firstPayment = $customerPayments->first();
                echo "Payment ID: " . $firstPayment->payment_id . "\n";
                echo "Client ID: " . $firstPayment->client_id . "\n";
                echo "Amount: " . $firstPayment->amount . "\n";
                echo "Payment Date: " . $firstPayment->payment_date . "\n\n";
                
                echo "3. Intentando actualizar comisión...\n";
                $firstCommission->update([
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verified_amount' => $customerPayments->sum('amount')
                ]);
                echo "Comisión actualizada exitosamente.\n";
            } else {
                echo "No se encontraron pagos para este cliente en el período especificado.\n";
            }
        } catch (\Exception $e) {
            echo "Error en la consulta de pagos: " . $e->getMessage() . "\n";
            echo "Línea: " . $e->getLine() . "\n";
            echo "Archivo: " . $e->getFile() . "\n";
        }
    } else {
        echo "No hay comisiones pendientes para procesar.\n";
    }
    
} catch (\Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
}

echo "\n=== Depuración completada ===\n";