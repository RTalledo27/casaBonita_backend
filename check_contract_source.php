<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Analizando contratos de Logicware sin asesor...\n\n";

// Primero verificar qué campos tiene la tabla contracts
echo "📋 Estructura de la tabla contracts:\n";
$columns = DB::select("SHOW COLUMNS FROM contracts");
foreach ($columns as $col) {
    echo "   - {$col->Field} ({$col->Type})\n";
}
echo "\n";

// Obtener contratos de Logicware sin asesor (sin campos inexistentes)
$contracts = DB::table('contracts')
    ->leftJoin('reservations', 'contracts.reservation_id', '=', 'reservations.reservation_id')
    ->leftJoin('clients', 'contracts.client_id', '=', 'clients.client_id')
    ->where('contracts.source', 'logicware')
    ->whereNull('contracts.advisor_id')
    ->select(
        'contracts.contract_id',
        'contracts.contract_number',
        'contracts.reservation_id',
        'reservations.advisor_id as reservation_advisor_id',
        'clients.client_id',
        'clients.first_name',
        'clients.last_name'
    )
    ->limit(5)
    ->get();

echo "📊 Total contratos Logicware sin asesor: " . $contracts->count() . "\n\n";

foreach ($contracts as $contract) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📄 Contrato ID: {$contract->contract_id}\n";
    echo "   Número: {$contract->contract_number}\n";
    echo "   Cliente: {$contract->first_name} {$contract->last_name}\n";
    echo "   Reservation ID: " . ($contract->reservation_id ?: 'NULL') . "\n";
    echo "   Reservation Advisor ID: " . ($contract->reservation_advisor_id ?: 'NULL') . "\n";
    echo "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💡 PROBLEMA CRÍTICO DESCUBIERTO:\n";
echo "   ❌ La tabla contracts NO tiene campo para guardar datos de Logicware\n";
echo "   ❌ NO existe external_data ni logicware_data\n";
echo "   ❌ Los contratos importados NO guardan la información del vendedor\n";
echo "   ❌ Por eso no podemos re-linkear - la información se perdió\n\n";
echo "🔧 SOLUCIONES POSIBLES:\n";
echo "   1. Agregar columna 'logicware_data' (JSON) a la tabla contracts\n";
echo "   2. Modificar LogicwareContractImporter para guardar los datos completos\n";
echo "   3. Volver a importar desde Logicware (pero ya no hay requests hasta mañana)\n\n";
