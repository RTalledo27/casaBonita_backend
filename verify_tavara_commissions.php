<?php

use Modules\HumanResources\Models\Commission;
use Modules\Sales\Models\Contract;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13; // Luis Tavara

echo "=== VERIFICACIÓN DE COMISIONES: LUIS TAVARA (OCT 2025) ===\n\n";

// 1. Verificar conteo de ventas usado (indirectamente a través de las comisiones generadas)
// Buscamos una comisión de Octubre para ver qué 'sales_count' se guardó (si se guarda)
// O recalculamos el conteo actual

$salesCount = Contract::where('advisor_id', $advisorId)
    ->whereMonth('contract_date', 10)
    ->whereYear('contract_date', 2025)
    ->where('status', 'vigente')
    ->whereNotNull('financing_amount')
    ->where('financing_amount', '>', 0)
    ->count();

echo "Sales Count (usando contract_date): $salesCount\n\n";

// 2. Listar comisiones generadas
$commissions = Commission::whereHas('contract', function($q) use ($advisorId) {
        $q->where('advisor_id', $advisorId)
          ->whereMonth('contract_date', 10)
          ->whereYear('contract_date', 2025);
    })
    ->with('contract')
    ->get();

echo "Total Comisiones Generadas: " . $commissions->count() . "\n";
echo "Monto Total Comisiones: S/ " . number_format($commissions->sum('commission_amount'), 2) . "\n\n";

echo "DETALLE (Primeros 10):\n";
echo str_repeat("-", 80) . "\n";
echo sprintf("%-20s %-10s %-10s %-10s %-15s\n", "CONTRATO", "FECHA", "VENTAS", "% COM", "MONTO");
echo str_repeat("-", 80) . "\n";

foreach ($commissions->take(10) as $comm) {
    // Intentar obtener sales_count si se guardó en algún lado, o inferirlo
    // En la estructura actual de Commission no veo columna sales_count explícita en el modelo, 
    // pero el servicio lo usa para calcular.
    
    echo sprintf("%-20s %-10s %-10s %-10s S/ %-15s\n", 
        $comm->contract->contract_number,
        $comm->contract->contract_date,
        "?", // No guardamos el sales_count en la tabla commission por defecto
        $comm->commission_percentage . "%",
        number_format($comm->commission_amount, 2)
    );
}

echo "\n";
echo "DISTRIBUCIÓN DE PORCENTAJES:\n";
$distribution = $commissions->groupBy('commission_percentage')->map->count();
foreach ($distribution as $pct => $count) {
    echo "  $pct%: $count contratos\n";
}
