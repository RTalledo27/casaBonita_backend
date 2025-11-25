<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13; // Luis Tavara

echo "ANÁLISIS DE LOS 16 CONTRATOS CONTADOS (Sistema)\n";
echo "Criterio: Advisor 13, Octubre 2025 (contract_date), Vigente, Financiado\n";
echo str_repeat("=", 100) . "\n\n";

$contracts = DB::select("
    SELECT 
        c.contract_id,
        c.contract_number,
        c.contract_date,
        c.sign_date,
        c.status as contract_status,
        c.financing_amount,
        c.total_price,
        c.created_at,
        c.updated_at,
        r.status as reservation_status,
        r.reservation_date
    FROM contracts c
    LEFT JOIN reservations r ON c.reservation_id = r.reservation_id
    WHERE c.advisor_id = ?
    AND MONTH(c.contract_date) = 10
    AND YEAR(c.contract_date) = 2025
    AND c.status = 'vigente'
    AND c.financing_amount > 0
    ORDER BY c.contract_date, c.contract_number
", [$advisorId]);

echo sprintf("%-3s %-20s %-12s %-10s %-10s %-15s %s\n", 
    "#", "CONTRATO", "FECHA", "ESTADO", "RESERVA", "FINANCIAMIENTO", "ACTUALIZADO");
echo str_repeat("-", 100) . "\n";

$n = 1;
foreach ($contracts as $c) {
    echo sprintf("%-3d %-20s %-12s %-10s %-10s S/ %-12.2f %s\n",
        $n++,
        $c->contract_number,
        $c->contract_date,
        $c->contract_status,
        $c->reservation_status ?? 'N/A',
        $c->financing_amount,
        substr($c->updated_at, 0, 10)
    );
}

echo "\n" . str_repeat("=", 100) . "\n";
echo "Total encontrados: " . count($contracts) . "\n";
echo "Excel dice: 14\n";
echo "Sobrantes: " . (count($contracts) - 14) . "\n\n";

echo "POSIBLES CANDIDATOS A DESCARTAR:\n";
foreach ($contracts as $c) {
    // Chequear si la reserva está cancelada pero el contrato sigue vigente
    if ($c->reservation_status === 'cancelled' || $c->reservation_status === 'expired') {
        echo "⚠️  Contrato {$c->contract_number}: Estado 'vigente' pero reserva '{$c->reservation_status}'\n";
    }
    
    // Chequear si no se ha actualizado recientemente (podría ser un contrato abandonado)
    $lastUpdate = strtotime($c->updated_at);
    $twoMonthsAgo = strtotime('-2 months');
    if ($lastUpdate < $twoMonthsAgo) {
        echo "⚠️  Contrato {$c->contract_number}: No actualizado desde " . substr($c->updated_at, 0, 10) . "\n";
    }
}
