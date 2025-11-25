<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ASESORES CON VENTAS EN OCTUBRE 2025 ===\n\n";

$results = DB::select("
    SELECT 
        e.employee_id,
        e.name,
        COUNT(DISTINCT c.contract_id) as total_contratos,
        COUNT(DISTINCT CASE WHEN c.status = 'vigente' AND c.financing_amount > 0 THEN c.contract_id END) as ventas_contadas
    FROM contracts c
    JOIN employees e ON c.advisor_id = e.employee_id
    WHERE MONTH(c.sign_date) = 10 
    AND YEAR(c.sign_date) = 2025
    GROUP BY e.employee_id, e.name
    ORDER BY ventas_contadas DESC, e.name
");

echo sprintf("%-5s %-40s %10s %15s\n", "ID", "NOMBRE", "Total", "Financ.Vigente");
echo str_repeat("=", 75) . "\n";

$tavaraId = null;
foreach ($results as $row) {
    echo sprintf(
        "%-5s %-40s %10s %15s\n",
        $row->employee_id,
        substr($row->name, 0, 40),
        $row->total_contratos,
        $row->ventas_contadas
    );
    
    if (stripos($row->name, 'tavara') !== false) {
        $tavaraId = $row->employee_id;
        echo "       ^^^ LUIS TAVARA ^^^\n";
    }
}

if ($tavaraId) {
    echo "\n\n=== DETALLE DE CONTRATOS DE LUIS TAVARA (ID: $tavaraId) ===\n\n";
    
    $contracts = DB::select("
        SELECT 
            contract_number,
            sign_date,
            status,
            total_price,
            financing_amount,
            term_months
        FROM contracts
        WHERE advisor_id = ?
        AND MONTH(sign_date) = 10
        AND YEAR(sign_date) = 2025
        ORDER BY sign_date, contract_number
    ", [$tavaraId]);
    
    $n = 1;
    $vigFinanced = 0;
    
    foreach ($contracts as $c) {
        $isVigente = $c->status === 'vigente' ? '✓' : '✗';
        $isFinanced = $c->financing_amount > 0 ? 'F' : 'C';
        
        if ($c->status === 'vigente' && $c->financing_amount > 0) {
            $vigFinanced++;
        }
        
        echo sprintf(
            "%2d. [%s][%s] %-25s %s  S/ %10.2f  S/ %10.2f  %2d m %s\n",
            $n++,
            $isVigente,
            $isFinanced,
            $c->contract_number,
            $c->sign_date,
            $c->total_price,
            $c->financing_amount ?? 0,
            $c->term_months ?? 0,
            $c->status !== 'vigente' ? "({$c->status})" : ""
        );
    }
    
    echo "\n";
    echo "Total contratos: " . count($contracts) . "\n";
    echo "Vigentes + Financiados (lo que cuenta el sistema): $vigFinanced\n";
    echo "Excel dice: 14\n";
    echo "Diferencia: " . ($vigFinanced - 14) . "\n";
}
