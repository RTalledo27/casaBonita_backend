<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Services\CommissionService;
use Illuminate\Support\Facades\DB;

echo "Probando el nuevo sistema de comisiones divididas...\n\n";

try {
    $service = app(CommissionService::class);
    
    echo "Procesando comisiones para julio 2025...\n";
    $result = $service->processCommissionsForPeriod(7, 2025);
    
    echo "Comisiones procesadas: " . count($result) . "\n\n";
    
    // Verificar las últimas comisiones creadas
    echo "Últimas 5 comisiones con nuevos campos:\n";
    $commissions = DB::table('commissions')
        ->select(['commission_id', 'commission_period', 'payment_period', 'payment_percentage', 'status', 'parent_commission_id', 'payment_part', 'payment_status', 'payment_type'])
        ->whereNotNull('commission_period')
        ->latest('commission_id')
        ->take(5)
        ->get();
    
    foreach($commissions as $commission) {
        echo "Commission ID: {$commission->commission_id}\n";
        echo "Commission Period: {$commission->commission_period}\n";
        echo "Payment Period: " . ($commission->payment_period ?? 'NULL') . "\n";
        echo "Payment Percentage: {$commission->payment_percentage}%\n";
        echo "Status: {$commission->status}\n";
        echo "Parent Commission ID: " . ($commission->parent_commission_id ?? 'NULL') . "\n";
        echo "Payment Part: {$commission->payment_part}\n";
        echo "Payment Status: {$commission->payment_status}\n";
        echo "Payment Type: {$commission->payment_type}\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}