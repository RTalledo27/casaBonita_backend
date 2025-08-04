<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;

echo "Actualizando comisiones existentes con los nuevos campos...\n\n";

$month = 7;
$year = 2025;

// Obtener comisiones de julio 2025 que no tienen commission_period
$commissions = Commission::where('period_month', $month)
                        ->where('period_year', $year)
                        ->whereNull('commission_period')
                        ->get();

echo "Comisiones a actualizar: " . $commissions->count() . "\n\n";

DB::transaction(function () use ($commissions, $month, $year) {
    foreach ($commissions as $commission) {
        echo "Actualizando comisión ID: {$commission->commission_id}\n";
        
        // Generar commission_period (formato YYYY-MM)
        $commissionPeriod = sprintf('%04d-%02d', $year, $month);
        
        // Para payment_period, usar el mes siguiente (formato YYYY-MM-P donde P es el número de pago)
        $paymentMonth = $month + 1;
        $paymentYear = $year;
        
        // Ajustar año si es diciembre
        if ($paymentMonth > 12) {
            $paymentMonth = 1;
            $paymentYear++;
        }
        
        $paymentPeriod = sprintf('%04d-%02d-P%d', $paymentYear, $paymentMonth, $commission->payment_part);
        
        // Actualizar la comisión
        $commission->update([
            'commission_period' => $commissionPeriod,
            'payment_period' => $paymentPeriod
        ]);
        
        echo "- Commission Period: {$commissionPeriod}\n";
        echo "- Payment Period: {$paymentPeriod}\n";
        echo "\n";
    }
});

echo "Actualización completada.\n\n";

// Verificar los resultados
echo "Verificando resultados...\n";
$updatedCommissions = Commission::where('period_month', $month)
                                ->where('period_year', $year)
                                ->get();

echo "Comisiones con commission_period: " . $updatedCommissions->whereNotNull('commission_period')->count() . "\n";
echo "Comisiones con payment_period: " . $updatedCommissions->whereNotNull('payment_period')->count() . "\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "Muestra de comisiones actualizadas:\n";
foreach ($updatedCommissions->take(3) as $commission) {
    echo "ID: {$commission->commission_id} | Commission Period: {$commission->commission_period} | Payment Period: {$commission->payment_period}\n";
}