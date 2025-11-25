<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13; // Luis Tavara

$output = "═══════════════════════════════════════════════════════════════════\n";
$output .= "  ANÁLISIS: LUIS TAVARA (ID: 13) - OCTUBRE 2025\n";
$output .= "═══════════════════════════════════════════════════════════════════\n\n";

$contracts = DB::select("
    SELECT 
        contract_number,
        sign_date,
        contract_date,
        status,
        total_price,
        financing_amount,
        term_months
    FROM contracts
    WHERE advisor_id = ?
    AND MONTH(sign_date) = 10
    AND YEAR(sign_date) = 2025
    ORDER BY sign_date, contract_number
", [$advisorId]);
$output .= "TOTAL CONTRATOS: " . count($contracts) . "\n\n";

$output .= "LEYENDA: ✓ = Contado por sistema | ✗ = No contado\n\n";
$output .= str_repeat("=", 110) . "\n";
$output .= sprintf("%-3s %-3s %-25s %-12s %-10s %12s %12s %5s\n", 
    "#", "✓", "CONTRATO", "FECHA", "ESTADO", "TOTAL", "FINANC", "MESES");
$output .= str_repeat("=", 110) . "\n";

$counted = 0;
$n = 1;

foreach ($contracts as $c) {
    $shouldCount = ($c->status === 'vigente' && $c->financing_amount > 0);
    $mark = $shouldCount ? '✓' : '✗';
    
    if ($shouldCount) $counted++;
    
    $output .= sprintf(
        "%-3d %s   %-25s %-12s %-10s %12.2f %12.2f %5d\n",
        $n++,
        $mark,
        $c->contract_number,
        $c->sign_date,
        $c->status,
        $c->total_price,
        $c->financing_amount ?? 0,
        $c->term_months ?? 0
    );
}

$output .= str_repeat("=", 110) . "\n\n";

$output .= "RESUMEN:\n";
$output .= "  Sistema cuenta: $counted contratos\n";
$output .= "  Excel dice: 14 contratos\n";
$output .= "  DIFERENCIA: " . ($counted - 14) . " contratos\n\n";

if ($counted > 14) {
    $output .= "⚠️  HAY " . ($counted - 14) . " CONTRATOS EXTRA\n\n";
    $output .= "INSTRUCCIONES:\n";
    $output .= "  1. Compara los números de contrato de arriba con los del Excel\n";
    $output .= "  2. Identifica cuáles contratos están de más\n";
    $output .= "  3. Verifica si son duplicados, cancelados, o no deberían contar\n";
}

echo $output;
file_put_contents(__DIR__ . '/tavara_luis_report.txt', $output);
echo "\n✅ Reporte guardado en: tavara_luis_report.txt\n";
