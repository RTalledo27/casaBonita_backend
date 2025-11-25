<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13;

echo "COMPARACIÓN DE FECHAS PARA CONTAR VENTAS\n";
echo str_repeat("=", 70) . "\n\n";

// sign_date
$bySignDate = DB::table('contracts')
    ->where('advisor_id', $advisorId)
    ->whereMonth('sign_date', 10)
    ->whereYear('sign_date', 2025)
    ->where('status', 'vigente')
    ->where('financing_amount', '>', 0)
    ->count();

// contract_date
$byContractDate = DB::table('contracts')
    ->where('advisor_id', $advisorId)
    ->whereMonth('contract_date', 10)
    ->whereYear('contract_date', 2025)
    ->where('status', 'vigente')
    ->where('financing_amount', '>', 0)
    ->count();

// created_at
$byCreatedAt = DB::table('contracts')
    ->where('advisor_id', $advisorId)
    ->whereMonth('created_at', 10)
    ->whereYear('created_at', 2025)
    ->where('status', 'vigente')
    ->where('financing_amount', '>', 0)
    ->count();

echo "Conteo usando sign_date (octubre 2025):      $bySignDate\n";
echo "Conteo usando contract_date (octubre 2025):  $byContractDate\n";
echo "Conteo usando created_at (octubre 2025):     $byCreatedAt\n";
echo "\nExcel de administración:                      14\n";

echo "\n" . str_repeat("=", 70) . "\n";

if ($byContractDate == 14) {
    echo "\n✅ SOLUCIÓN ENCONTRADA!\n\n";
    echo "El Excel usa CONTRACT_DATE\n";
    echo "El sistema actualmente usa SIGN_DATE\n\n";
    echo "ACCIÓN REQUERIDA:\n";
    echo "Cambiar el sistema para usar contract_date en lugar de sign_date\n";
    echo "en el método que cuenta ventas del asesor.\n";
} else if ($byCreatedAt == 14) {
    echo "\n✅ SOLUCIÓN ENCONTRADA!\n\n";
    echo "El Excel usa CREATED_AT\n";
    echo "El sistema actualmente usa SIGN_DATE\n\n";
    echo "ACCIÓN REQUERIDA:\n";
    echo "Cambiar el sistema para usar created_at en lugar de sign_date\n";
} else {
    echo "\n⚠️  Ninguna fecha da exactamente 14\n";
    echo "Las diferencias son:\n";
    echo "  sign_date:      " . ($bySignDate - 14) . " contratos de diferencia\n";
    echo "  contract_date:  " . ($byContractDate - 14) . " contratos de diferencia\n";
    echo "  created_at:     " . ($byCreatedAt - 14) . " contratos de diferencia\n";
    echo "\nLa más cercana es: ";
    
    $diffs = [
        'sign_date' => abs($bySignDate - 14),
        'contract_date' => abs($byContractDate - 14),
        'created_at' => abs($byCreatedAt - 14)
    ];
    
    $min = min($diffs);
    foreach ($diffs as $field => $diff) {
        if ($diff == $min) {
            echo "$field (diferencia de $diff)\n";
            break;
        }
    }
}
