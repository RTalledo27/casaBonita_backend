<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 'EMP1822';

echo "═══════════════════════════════════════════════════════════════════\n";
echo "  ANÁLISIS DE CONTRATOS: LUIS TAVARA (EMP1822) - OCTUBRE 2025\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

$contracts = DB::select("
    SELECT 
        contract_number,
        sign_date,
        contract_date,
        status,
        total_price,
        financing_amount,
        term_months,
        created_at
    FROM contracts
    WHERE advisor_id = ?
    AND MONTH(sign_date) = 10
    AND YEAR(sign_date) = 2025
    ORDER BY sign_date, contract_number
", [$advisorId]);

if (empty($contracts)) {
    echo "⚠️  No se encontraron contratos para EMP1822 en octubre 2025\n";
    exit;
}

echo "TOTAL CONTRATOS ENCONTRADOS: " . count($contracts) . "\n\n";

echo "LEYENDA:\n";
echo "  ✓ = Contado (vigente + financing_amount > 0)\n";
echo "  ✗ = No contado\n\n";

echo str_repeat("=", 120) . "\n";
echo sprintf("%-3s %-3s %-25s %-12s %-10s %12s %12s %5s %s\n", 
    "#", "✓", "CONTRATO", "FECHA FIRMA", "ESTADO", "TOTAL", "FINANC", "MESES", "NOTAS");
echo str_repeat("=", 120) . "\n";

$counted = 0;
$notCounted = [];
$n = 1;

foreach ($contracts as $c) {
    $shouldCount = ($c->status === 'vigente' && $c->financing_amount > 0);
    $mark = $shouldCount ? '✓' : '✗';
    
    if ($shouldCount) {
        $counted++;
        $note = '';
    } else {
        $reasons = [];
        if ($c->status !== 'vigente') {
            $reasons[] = "status={$c->status}";
        }
        if (!$c->financing_amount || $c->financing_amount <= 0) {
            $reasons[] = "contado/sin_financ";
        }
        $note = implode(', ', $reasons);
        $notCounted[] = [
            'contract' => $c->contract_number,
            'reason' => $note
        ];
    }
    
    echo sprintf(
        "%-3d %s   %-25s %-12s %-10s %12.2f %12.2f %5d %s\n",
        $n++,
        $mark,
        $c->contract_number,
        $c->sign_date,
        $c->status,
        $c->total_price,
        $c->financing_amount ?? 0,
        $c->term_months ?? 0,
        $note
    );
}

echo str_repeat("=", 120) . "\n\n";

echo "RESUMEN:\n";
echo "  Total contratos en octubre: " . count($contracts) . "\n";
echo "  Contados por sistema (✓): $counted\n";
echo "  No contados (✗): " . count($notCounted) . "\n";
echo "\n";
echo "  Sistema dice: $counted ventas\n";
echo "  Excel dice: 14 ventas\n";
echo "  DIFERENCIA: " . ($counted - 14) . " contratos\n\n";

if ($counted > 14) {
    echo "⚠️  EL SISTEMA CUENTA " . ($counted - 14) . " CONTRATOS MÁS QUE EL EXCEL\n\n";
    echo "POSIBLES CAUSAS:\n";
    echo "  1. Contratos duplicados\n";
    echo "  2. Contratos que no deberían contar según administración\n";
    echo "  3. Diferencia en fecha de corte (sign_date vs contract_date)\n";
    echo "  4. Excel no incluye todos los contratos vigentes\n\n";
    
    echo "RECOMENDACIÓN:\n";
    echo "  Compara los números de contrato del sistema con los del Excel\n";
    echo "  para identificar cuáles están de más.\n";
} else if ($counted < 14) {
    echo "⚠️  EL EXCEL CUENTA " . (14 - $counted) . " CONTRATOS MÁS QUE EL SISTEMA\n\n";
    echo "Esto podría significar que el Excel incluye contratos:\n";
    echo "  - De contado (sin financing_amount)\n";
    echo "  - Con status diferente a 'vigente'\n";
    echo "  - De otro mes\n";
}

// Guardar a archivo
$output = ob_get_clean();
echo $output;
file_put_contents(__DIR__ . '/tavara_detailed_analysis.txt', $output);
echo "\n✅ Reporte guardado en: tavara_detailed_analysis.txt\n";
