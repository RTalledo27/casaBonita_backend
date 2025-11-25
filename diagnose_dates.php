<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$advisorId = 13; // Luis Tavara

echo "═══════════════════════════════════════════════════════════════════\n";
echo "  DIAGNÓSTICO: FECHAS Y RESERVAS - LUIS TAVARA\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Obtener todos los contratos con sus fechas
$contracts = DB::select("
    SELECT 
        c.contract_number,
        c.sign_date,
        c.contract_date,
        c.created_at,
        c.status,
        c.financing_amount,
        r.reservation_date,
        r.status as reservation_status
    FROM contracts c
    LEFT JOIN reservations r ON c.reservation_id = r.reservation_id
    WHERE c.advisor_id = ?
    AND (
        MONTH(c.sign_date) = 10 OR 
        MONTH(c.contract_date) = 10 OR 
        MONTH(c.created_at) = 10
    )
    AND YEAR(c.sign_date) = 2025
    ORDER BY c.contract_number
", [$advisorId]);

echo "ANÁLISIS DE FECHAS:\n";
echo str_repeat("=", 120) . "\n";
echo sprintf("%-25s %-12s %-12s %-12s %-12s %-10s\n", 
    "CONTRATO", "SIGN_DATE", "CONTRACT_DT", "CREATED_AT", "RESERV_DT", "STATUS");
echo str_repeat("=", 120) . "\n";

$countBySignDate = 0;
$countByContractDate = 0;
$countByCreatedAt = 0;
$dateMismatches = [];

foreach ($contracts as $c) {
    $signMonth = $c->sign_date ? date('m', strtotime($c->sign_date)) : '??';
    $contractMonth = $c->contract_date ? date('m', strtotime($c->contract_date)) : '??';
    $createdMonth = date('m', strtotime($c->created_at));
    
    $mismatch = '';
    if ($signMonth != $contractMonth && $contractMonth != '??') {
        $mismatch = '⚠️  FECHAS DIFERENTES';
        $dateMismatches[] = $c->contract_number;
    }
    
    echo sprintf("%-25s %-12s %-12s %-12s %-12s %-10s %s\n",
        $c->contract_number,
        $c->sign_date ?? 'NULL',
        $c->contract_date ?? 'NULL',
        substr($c->created_at, 0, 10),
        $c->reservation_date ?? 'NULL',
        $c->status,
        $mismatch
    );
    
    // Contar según cada fecha
    if ($c->status === 'vigente' && $c->financing_amount > 0) {
        if ($signMonth == '10') $countBySignDate++;
        if ($contractMonth == '10') $countByContractDate++;
        if ($createdMonth == '10') $countByCreatedAt++;
    }
}

echo "\n" . str_repeat("=", 120) . "\n\n";

echo "RESUMEN DE CONTEO SEGÚN FECHA USADA:\n";
echo "  Si usamos sign_date (octubre):      $countBySignDate contratos\n";
echo "  Si usamos contract_date (octubre):  $countByContractDate contratos\n";
echo "  Si usamos created_at (octubre):     $countByCreatedAt contratos\n";
echo "  Excel dice:                         14 contratos\n\n";

if ($countByContractDate == 14) {
    echo "✅ ¡ENCONTRADO! El Excel probablemente usa CONTRACT_DATE\n";
    echo "   El sistema actualmente usa SIGN_DATE\n\n";
    echo "RECOMENDACIÓN: Cambiar el sistema para usar contract_date en lugar de sign_date\n";
} else if ($countByCreatedAt == 14) {
    echo "✅ ¡ENCONTRADO! El Excel probablemente usa CREATED_AT\n";
    echo "   El sistema actualmente usa SIGN_DATE\n\n";
    echo "RECOMENDACIÓN: Cambiar el sistema para usar created_at en lugar de sign_date\n";
} else {
    echo "⚠️  Ninguna fecha da exactamente 14\n";
    echo "   Puede haber otros factores involucrados\n";
}

if (!empty($dateMismatches)) {
    echo "\n⚠️  CONTRATOS CON FECHAS DIFERENTES:\n";
    foreach ($dateMismatches as $cn) {
        echo "   - $cn\n";
    }
}

// Verificar reservas sin contrato
echo "\n\n" . str_repeat("=", 120) . "\n";
echo "VERIFICACIÓN DE RESERVAS SIN CONTRATO:\n";
echo str_repeat("=", 120) . "\n";

$reservationsOnly = DB::select("
    SELECT COUNT(*) as count
    FROM reservations r
    LEFT JOIN contracts c ON r.reservation_id = c.reservation_id
    WHERE r.advisor_id = ?
    AND MONTH(r.reservation_date) = 10
    AND YEAR(r.reservation_date) = 2025
    AND c.contract_id IS NULL
", [$advisorId]);

echo "\nReservas en octubre SIN contrato: " . $reservationsOnly[0]->count . "\n";

if ($reservationsOnly[0]->count > 0) {
    echo "⚠️  Hay reservas sin contrato que NO deberían contarse como ventas\n";
} else {
    echo "✓  No hay reservas sin contrato\n";
}
