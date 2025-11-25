<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13; // Luis Tavara

echo "VERIFICACIÓN DESPUÉS DEL CAMBIO\n";
echo str_repeat("=", 70) . "\n\n";

// Usando contract_date (como ahora lo hace el sistema)
$newCount = DB::table('contracts')
    ->where('advisor_id', $advisorId)
    ->whereMonth('contract_date', 10)
    ->whereYear('contract_date', 2025)
    ->where('status', 'vigente')
    ->where('financing_amount', '>', 0)
    ->count();

echo "Conteo nuevo (usando contract_date): $newCount contratos\n";
echo "Excel de administración:              14 contratos\n";
echo "Diferencia:                           " . ($newCount - 14) . " contratos\n\n";

if ($newCount == 14) {
    echo "✅ ¡PERFECTO! El conteo ahora coincide con el Excel\n";
} else {
    echo "⚠️  Aún hay una diferencia de " . abs($newCount - 14) . " contratos\n\n";
    
    if ($newCount > 14) {
        echo "Posibles razones para los " . ($newCount - 14) . " contratos extra:\n";
        echo "  1. El Excel excluye ciertos tipos de ventas que el sistema incluye\n";
        echo "  2. Hay contratos duplicados\n";
        echo "  3. Diferencia en el criterio de 'vigente'\n";
    } else {
        echo "El sistema cuenta menos que el Excel.\n";
        echo "Esto podría significar que el Excel incluye ventas de contado.\n";
    }
}
