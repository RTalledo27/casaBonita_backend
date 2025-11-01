<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICANDO RELACIÓN CONTRATOS <-> ASESORES ===\n\n";

// 1. Ver contratos con sus advisor_id
echo "1. CONTRATOS Y SUS ADVISOR_ID:\n";
$contracts = DB::table('contracts')
    ->select('contract_id', 'contract_number', 'advisor_id', 'client_id')
    ->limit(10)
    ->get();

foreach ($contracts as $contract) {
    echo "  Contrato #{$contract->contract_number} -> advisor_id: " . ($contract->advisor_id ?? 'NULL') . "\n";
}

echo "\n2. USUARIOS (ASESORES):\n";
$users = DB::table('users')
    ->select('user_id', 'first_name', 'last_name', 'position', 'department')
    ->limit(10)
    ->get();

foreach ($users as $user) {
    echo "  user_id: {$user->user_id} -> {$user->first_name} {$user->last_name} ({$user->position}) - Dept: {$user->department}\n";
}

echo "\n3. JOIN CONTRATOS CON ASESORES (como en el reporte):\n";
$salesWithAdvisors = DB::table('contracts as c')
    ->leftJoin('users as adv', 'c.advisor_id', '=', 'adv.user_id')
    ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
    ->select(
        'c.contract_number',
        'c.advisor_id',
        'adv.user_id',
        DB::raw('CONCAT(COALESCE(adv.first_name, ""), " ", COALESCE(adv.last_name, "Sin asesor")) as asesor'),
        DB::raw('COALESCE(adv.department, "Sin oficina") as oficina'),
        DB::raw('CONCAT(cl.first_name, " ", cl.last_name) as cliente')
    )
    ->limit(15)
    ->get();

echo "\nContrato | advisor_id | user_id | Asesor | Oficina | Cliente\n";
echo str_repeat("-", 100) . "\n";
foreach ($salesWithAdvisors as $sale) {
    printf(
        "%-10s | %-10s | %-8s | %-20s | %-15s | %s\n",
        $sale->contract_number ?? 'N/A',
        $sale->advisor_id ?? 'NULL',
        $sale->user_id ?? 'NULL',
        $sale->asesor,
        $sale->oficina,
        $sale->cliente
    );
}

echo "\n4. ESTADÍSTICAS:\n";
$stats = DB::table('contracts')
    ->select(
        DB::raw('COUNT(*) as total_contratos'),
        DB::raw('COUNT(advisor_id) as con_advisor'),
        DB::raw('COUNT(*) - COUNT(advisor_id) as sin_advisor')
    )
    ->first();

echo "  Total contratos: {$stats->total_contratos}\n";
echo "  Con advisor_id: {$stats->con_advisor}\n";
echo "  Sin advisor_id: {$stats->sin_advisor}\n";

// 5. Ver si hay advisor_id que no existen en users
echo "\n5. ADVISOR_ID QUE NO EXISTEN EN USERS:\n";
$orphanedAdvisors = DB::table('contracts as c')
    ->leftJoin('users as u', 'c.advisor_id', '=', 'u.user_id')
    ->whereNotNull('c.advisor_id')
    ->whereNull('u.user_id')
    ->select('c.advisor_id', DB::raw('COUNT(*) as count'))
    ->groupBy('c.advisor_id')
    ->get();

if ($orphanedAdvisors->isEmpty()) {
    echo "  ✅ Todos los advisor_id existen en la tabla users\n";
} else {
    echo "  ❌ PROBLEMA: Hay advisor_id que no existen:\n";
    foreach ($orphanedAdvisors as $orphan) {
        echo "    advisor_id: {$orphan->advisor_id} -> {$orphan->count} contratos huérfanos\n";
    }
}

echo "\n✅ Verificación completa\n";
