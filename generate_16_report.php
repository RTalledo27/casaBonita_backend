<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13;

$contracts = DB::select("
    SELECT 
        c.contract_number,
        c.contract_date,
        c.status,
        c.financing_amount,
        c.updated_at,
        r.status as reservation_status
    FROM contracts c
    LEFT JOIN reservations r ON c.reservation_id = r.reservation_id
    WHERE c.advisor_id = ?
    AND MONTH(c.contract_date) = 10
    AND YEAR(c.contract_date) = 2025
    AND c.status = 'vigente'
    AND c.financing_amount > 0
    ORDER BY c.contract_date
", [$advisorId]);

$output = "LISTA DE 16 CONTRATOS EN EL SISTEMA (LUIS TAVARA - OCT 2025)\n";
$output .= "============================================================\n\n";
$output .= sprintf("%-3s %-20s %-12s %-10s %-15s\n", "#", "CONTRATO", "FECHA", "ESTADO", "ACTUALIZADO");
$output .= str_repeat("-", 65) . "\n";

$n = 1;
foreach ($contracts as $c) {
    $warning = "";
    if (strtotime($c->updated_at) < strtotime('-2 months')) {
        $warning = "⚠️  (Antiguo)";
    }
    if ($c->reservation_status && $c->reservation_status !== 'approved' && $c->reservation_status !== 'pending') {
        $warning .= " ⚠️  Reserva: {$c->reservation_status}";
    }

    $output .= sprintf("%-3d %-20s %-12s %-10s %-15s %s\n",
        $n++,
        $c->contract_number,
        $c->contract_date,
        $c->status,
        substr($c->updated_at, 0, 10),
        $warning
    );
}

$output .= "\n" . str_repeat("=", 65) . "\n";
$output .= "INSTRUCCIONES:\n";
$output .= "1. Compara esta lista con tu Excel (que tiene 14).\n";
$output .= "2. Identifica los 2 que sobran.\n";
$output .= "3. Fíjate si tienen alertas ⚠️ (no actualizados o reserva cancelada).\n";

file_put_contents('tavara_16_report.txt', $output);
echo $output;
