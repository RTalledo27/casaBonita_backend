<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\HumanResources\Models\Commission;
use Modules\Collections\Models\AccountReceivable;
use Illuminate\Support\Facades\DB;

echo "=== BÚSQUEDA DE FORMATO DE CONTRATO ===\n\n";

// Buscar contratos que contengan "20257868"
echo "🔍 BUSCANDO CONTRATOS QUE CONTENGAN '20257868':\n";

// Buscar en comisiones
echo "\n📋 EN TABLA COMISIONES:\n";
$commissionsWithContract = Commission::where('contract_id', 'LIKE', '%20257868%')
    ->orWhere('contract_id', 'LIKE', '%CON20257868%')
    ->get();

echo "Comisiones encontradas: " . $commissionsWithContract->count() . "\n";
foreach ($commissionsWithContract as $commission) {
    echo "- Commission ID: {$commission->commission_id}, Contract ID: '{$commission->contract_id}'\n";
}

// Buscar en cuentas por cobrar
echo "\n💰 EN TABLA ACCOUNTS_RECEIVABLE:\n";
$arWithContract = AccountReceivable::where('contract_id', 'LIKE', '%20257868%')
    ->orWhere('contract_id', 'LIKE', '%CON20257868%')
    ->get();

echo "Cuentas por cobrar encontradas: " . $arWithContract->count() . "\n";
foreach ($arWithContract as $ar) {
    echo "- AR ID: {$ar->ar_id}, Contract ID: '{$ar->contract_id}', Monto: $" . number_format($ar->original_amount, 2) . "\n";
}

// Buscar contratos similares
echo "\n🔍 BUSCANDO CONTRATOS SIMILARES (últimos 20):\n";
$recentContracts = Commission::select('contract_id')
    ->distinct()
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

echo "Contratos recientes en comisiones:\n";
foreach ($recentContracts as $contract) {
    echo "- '{$contract->contract_id}'\n";
}

// Buscar por employee_id EMP6303 si existe
echo "\n👤 BUSCANDO POR EMPLOYEE EMP6303:\n";
$commissionsByEmployee = Commission::where('employee_id', 'EMP6303')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

echo "Comisiones del empleado EMP6303: " . $commissionsByEmployee->count() . "\n";
foreach ($commissionsByEmployee as $commission) {
    echo "- Commission ID: {$commission->commission_id}, Contract: '{$commission->contract_id}', Monto: $" . number_format($commission->commission_amount, 2) . "\n";
}

// Buscar en raw SQL para mayor flexibilidad
echo "\n🔍 BÚSQUEDA RAW EN BASE DE DATOS:\n";
try {
    $rawCommissions = DB::select("
        SELECT commission_id, contract_id, employee_id, commission_amount, payment_part, is_payable, payment_verification_status
        FROM commissions 
        WHERE contract_id LIKE '%20257868%' 
           OR contract_id LIKE '%CON20257868%'
           OR employee_id = 'EMP6303'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    echo "Resultados raw de comisiones: " . count($rawCommissions) . "\n";
    foreach ($rawCommissions as $commission) {
        echo "- ID: {$commission->commission_id}, Contract: '{$commission->contract_id}', Employee: {$commission->employee_id}, Part: " . ($commission->payment_part ?? 'NULL') . "\n";
    }
    
    $rawAR = DB::select("
        SELECT ar_id, contract_id, original_amount, due_date, status
        FROM accounts_receivable 
        WHERE contract_id LIKE '%20257868%' 
           OR contract_id LIKE '%CON20257868%'
        ORDER BY due_date ASC
        LIMIT 10
    ");
    
    echo "\nResultados raw de cuentas por cobrar: " . count($rawAR) . "\n";
    foreach ($rawAR as $ar) {
        echo "- AR ID: {$ar->ar_id}, Contract: '{$ar->contract_id}', Monto: $" . number_format($ar->original_amount, 2) . ", Vence: {$ar->due_date}\n";
    }
    
} catch (Exception $e) {
    echo "Error en búsqueda raw: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE LA BÚSQUEDA ===\n";