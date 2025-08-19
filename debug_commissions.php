<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Carbon\Carbon;

echo "=== DEBUG COMISIONES ===\n";

// Período actual
$currentMonth = Carbon::now()->format('Y-m');
echo "Período actual: {$currentMonth}\n";

// Contar comisiones por período
echo "\nComisiones por período:\n";
$periods = Commission::select('commission_period')
    ->distinct()
    ->orderBy('commission_period', 'desc')
    ->limit(5)
    ->get();

foreach ($periods as $period) {
    $total = Commission::where('commission_period', $period->commission_period)->count();
    $payable = Commission::where('commission_period', $period->commission_period)
        ->where('is_payable', true)
        ->count();
    echo "  {$period->commission_period}: {$total} total, {$payable} payables\n";
}

// Verificar comisiones del período actual
echo "\nComisiones del período actual ({$currentMonth}):\n";
$currentCommissions = Commission::where('commission_period', $currentMonth)
    ->where('is_payable', true)
    ->get();

echo "  Total payables: " . $currentCommissions->count() . "\n";
echo "  Pendientes: " . $currentCommissions->where('payment_status', 'pendiente')->count() . "\n";
echo "  Pagadas: " . $currentCommissions->where('payment_status', 'pagado')->count() . "\n";

if ($currentCommissions->count() > 0) {
    echo "\nPrimeras 3 comisiones del período actual:\n";
    foreach ($currentCommissions->take(3) as $commission) {
        echo "  ID: {$commission->commission_id}, Monto: {$commission->commission_amount}, Status: {$commission->payment_status}, Payable: " . ($commission->is_payable ? 'true' : 'false') . "\n";
    }
}

echo "\n=== FIN DEBUG ===\n";