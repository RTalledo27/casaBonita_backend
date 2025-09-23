<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;

echo "📋 VERIFICANDO COMISIONES PARA CON20257868\n";
echo "==========================================\n\n";

$contractId = 'CON20257868';
$commissions = Commission::where('contract_id', $contractId)->get();

echo "Comisiones encontradas: {$commissions->count()}\n\n";

if ($commissions->count() > 0) {
    foreach ($commissions as $commission) {
        echo "ID: {$commission->commission_id}\n";
        echo "Parte: {$commission->payment_part}\n";
        echo "Estado: {$commission->status}\n";
        echo "Estado de pago: {$commission->payment_status}\n";
        echo "Monto: {$commission->commission_amount}\n";
        echo "Requiere verificación: " . ($commission->requires_client_payment_verification ? 'SÍ' : 'NO') . "\n";
        echo "Estado de verificación: {$commission->payment_verification_status}\n";
        echo "Elegible para pago: " . ($commission->is_eligible_for_payment ? 'SÍ' : 'NO') . "\n";
        echo "---\n";
    }
} else {
    echo "❌ No se encontraron comisiones para el contrato $contractId\n";
    echo "Creando comisión de prueba...\n\n";
    
    // Crear una comisión de prueba
    $commission = Commission::create([
        'employee_id' => 1, // Asumiendo que existe un empleado con ID 1
        'contract_id' => $contractId,
        'commission_type' => 'venta',
        'sale_amount' => 100000,
        'commission_percentage' => 3.0,
        'commission_amount' => 3000,
        'payment_status' => 'pendiente',
        'status' => 'generated',
        'payment_part' => 1,
        'requires_client_payment_verification' => true,
        'payment_verification_status' => 'pending_verification',
        'is_eligible_for_payment' => false,
        'period_month' => date('n'),
        'period_year' => date('Y')
    ]);
    
    echo "✅ Comisión creada: ID {$commission->commission_id}\n";
}