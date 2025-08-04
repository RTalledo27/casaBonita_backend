<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\HumanResources\Models\Commission;
use Illuminate\Support\Facades\DB;

echo "Verificando los nuevos campos en las comisiones existentes...\n\n";

$month = 7;
$year = 2025;

// Obtener comisiones de julio 2025
$commissions = Commission::where('period_month', $month)
                        ->where('period_year', $year)
                        ->orderBy('commission_id')
                        ->get();

echo "Comisiones encontradas para julio 2025: " . $commissions->count() . "\n\n";

foreach ($commissions as $commission) {
    echo "=== Comisión ID: {$commission->commission_id} ===\n";
    echo "Contract ID: {$commission->contract_id}\n";
    echo "Employee ID: {$commission->employee_id}\n";
    echo "Commission Amount: {$commission->commission_amount}\n";
    echo "Payment Type: {$commission->payment_type}\n";
    echo "Total Commission Amount: " . ($commission->total_commission_amount ?? 'NULL') . "\n";
    echo "Sales Count: " . ($commission->sales_count ?? 'NULL') . "\n";
    echo "Commission Period: " . ($commission->commission_period ?? 'NULL') . "\n";
    echo "Payment Period: " . ($commission->payment_period ?? 'NULL') . "\n";
    echo "Payment Percentage: {$commission->payment_percentage}\n";
    echo "Status: {$commission->status}\n";
    echo "Payment Status: {$commission->payment_status}\n";
    echo "Parent Commission ID: " . ($commission->parent_commission_id ?? 'NULL') . "\n";
    echo "Payment Part: {$commission->payment_part}\n";
    echo "Notes: " . ($commission->notes ?? 'NULL') . "\n";
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "Resumen de campos nuevos:\n";
echo "- Comisiones con commission_period: " . $commissions->whereNotNull('commission_period')->count() . "\n";
echo "- Comisiones con payment_period: " . $commissions->whereNotNull('payment_period')->count() . "\n";
echo "- Comisiones con total_commission_amount: " . $commissions->whereNotNull('total_commission_amount')->count() . "\n";
echo "- Comisiones con sales_count: " . $commissions->whereNotNull('sales_count')->count() . "\n";
echo "- Comisiones con parent_commission_id: " . $commissions->whereNotNull('parent_commission_id')->count() . "\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "Verificando si hay comisiones divididas (split payments):\n";
$parentCommissions = $commissions->whereNull('parent_commission_id');
$childCommissions = $commissions->whereNotNull('parent_commission_id');

echo "- Comisiones padre (parent): " . $parentCommissions->count() . "\n";
echo "- Comisiones hijas (split payments): " . $childCommissions->count() . "\n";

if ($childCommissions->count() > 0) {
    echo "\nDetalle de comisiones divididas:\n";
    foreach ($childCommissions as $child) {
        echo "- Child ID: {$child->commission_id}, Parent ID: {$child->parent_commission_id}, Payment Part: {$child->payment_part}, Amount: {$child->commission_amount}\n";
    }
}