<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICANDO ASESORES CON employee_id ===\n\n";

// Probar el query CORRECTO (como está ahora en SalesReportService)
echo "1. QUERY CORRECTO (contracts -> employees -> users):\n\n";

$correctQuery = DB::table('contracts as c')
    ->join('clients as cl', 'c.client_id', '=', 'cl.client_id')
    ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
    ->leftJoin('users as adv', 'e.user_id', '=', 'adv.user_id')
    ->select(
        'c.contract_number',
        'c.advisor_id',
        'e.employee_id',
        'e.user_id as employee_user_id',
        'adv.user_id as advisor_user_id',
        DB::raw('CONCAT(COALESCE(adv.first_name, ""), " ", COALESCE(adv.last_name, "-")) as asesor'),
        DB::raw('CONCAT(cl.first_name, " ", cl.last_name) as cliente')
    )
    ->whereYear('c.sign_date', 2025)
    ->whereMonth('c.sign_date', 1)
    ->orderBy('c.sign_date', 'asc')
    ->limit(20)
    ->get();

echo "Contrato     | c.advisor_id | e.employee_id | e.user_id | adv.user_id | ASESOR                        | CLIENTE\n";
echo str_repeat("-", 140) . "\n";

foreach ($correctQuery as $row) {
    printf(
        "%-12s | %-12s | %-13s | %-9s | %-11s | %-29s | %s\n",
        $row->contract_number,
        $row->advisor_id ?? 'NULL',
        $row->employee_id ?? 'NULL',
        $row->employee_user_id ?? 'NULL',
        $row->advisor_user_id ?? 'NULL',
        substr($row->asesor, 0, 29),
        substr($row->cliente, 0, 30)
    );
}

// Ver cuántos contratos tienen advisor_id pero NO tienen employee
echo "\n\n2. CONTRATOS SIN EMPLOYEE (advisor_id NO existe en employees.employee_id):\n\n";

$orphanedContracts = DB::table('contracts as c')
    ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
    ->whereNull('e.employee_id')
    ->whereNotNull('c.advisor_id')
    ->select('c.contract_number', 'c.advisor_id', 'c.sign_date')
    ->get();

if ($orphanedContracts->isEmpty()) {
    echo "✅ Todos los contratos con advisor_id tienen un employee asociado\n";
} else {
    echo "❌ HAY {$orphanedContracts->count()} CONTRATOS HUÉRFANOS:\n\n";
    foreach ($orphanedContracts->take(10) as $contract) {
        echo "  Contrato {$contract->contract_number} (advisor_id: {$contract->advisor_id}) - Fecha: {$contract->sign_date}\n";
    }
    if ($orphanedContracts->count() > 10) {
        echo "  ... y " . ($orphanedContracts->count() - 10) . " más\n";
    }
}

// Verificar la relación correcta: advisor_id debería ser employee_id
echo "\n\n3. ¿contracts.advisor_id ES employee_id o user_id?\n\n";

// Contar matches cuando advisor_id = employee_id
$matchesEmployeeId = DB::table('contracts as c')
    ->join('employees as e', 'c.advisor_id', '=', 'e.employee_id')
    ->count();

// Contar matches cuando advisor_id = user_id
$matchesUserId = DB::table('contracts as c')
    ->join('users as u', 'c.advisor_id', '=', 'u.user_id')
    ->count();

$totalContracts = DB::table('contracts')->whereNotNull('advisor_id')->count();

echo "Total contratos con advisor_id: {$totalContracts}\n";
echo "Matches cuando advisor_id = employee_id: {$matchesEmployeeId}\n";
echo "Matches cuando advisor_id = user_id: {$matchesUserId}\n\n";

if ($matchesEmployeeId > $matchesUserId) {
    echo "✅ CORRECTO: contracts.advisor_id ES employee_id\n";
} else {
    echo "⚠️  contracts.advisor_id parece ser user_id, NO employee_id\n";
}

echo "\n✅ Verificación completa\n";
