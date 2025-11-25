<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Listar todos los advisors con contratos en octubre
$results = DB::select("
    SELECT 
        c.advisor_id,
        CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')) as full_name,
        COUNT(*) as total_contracts,
        SUM(CASE WHEN c.status = 'vigente' AND c.financing_amount > 0 THEN 1 ELSE 0 END) as counted_sales
    FROM contracts c
    LEFT JOIN employees e ON c.advisor_id = e.employee_id
    WHERE MONTH(c.sign_date) = 10
    AND YEAR(c.sign_date) = 2025
    GROUP BY c.advisor_id, e.first_name, e.last_name
    ORDER BY counted_sales DESC
");

$output = "=== ASESORES CON VENTAS EN OCTUBRE 2025 ===\n\n";
$output .= sprintf("%-5s %-45s %10s %12s\n", "ID", "NOMBRE", "Total", "Contadas");
$output .= str_repeat("=", 80) . "\n";

$tavaraId = null;

foreach ($results as $row) {
    $output .= sprintf(
        "%-5s %-45s %10s %12s\n",
        $row->advisor_id,
        substr(trim($row->full_name), 0, 45),
        $row->total_contracts,
        $row->counted_sales
    );
    
    if (stripos($row->full_name, 'tavara') !== false) {
        $tavaraId = $row->advisor_id;
        $output .= "       ^^^ LUIS TAVARA ^^^^\n";
    }
}

echo $output;
file_put_contents(__DIR__ . '/tavara_summary.txt', $output);

if ($tavaraId) {
    echo "\n\n=== DETALLE DE CONTRATOS (ID: $tavaraId) ===\n\n";
    
    $contracts = DB::select("
        SELECT contract_number, sign_date, status, total_price, financing_amount, term_months
        FROM contracts
        WHERE advisor_id = ?
        AND MONTH(sign_date) = 10
        AND YEAR(sign_date) = 2025
        ORDER BY sign_date
    ", [$tavaraId]);
    
    echo sprintf("%-3s %-25s %-12s %-10s %12s %12s %5s\n", "#", "CONTRATO", "FECHA", "ESTADO", "TOTAL", "FINANC", "MESES");
    echo str_repeat("=", 90) . "\n";
    
    $n = 1;
    $counted = 0;
    
    foreach ($contracts as $c) {
        $mark = ($c->status === 'vigente' && $c->financing_amount > 0) ? '✓' : ' ';
        
        if ($c->status === 'vigente' && $c->financing_amount > 0) {
            $counted++;
        }
        
        echo sprintf(
            "%s%-3d %-25s %-12s %-10s %12.2f %12.2f %5d\n",
            $mark,
            $n++,
            $c->contract_number,
            $c->sign_date,
            $c->status,
            $c->total_price,
            $c->financing_amount ?? 0,
            $c->term_months ?? 0
        );
    }
    
    echo "\n";
    echo "Total: " . count($contracts) . " contratos\n";
    echo "Contados (✓): $counted\n";
    echo "Excel: 14\n";
    echo "Diferencia: " . ($counted - 14) . "\n";
}
