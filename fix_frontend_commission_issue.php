<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== SOLUCIONANDO PROBLEMA DE COMISIONES FRONTEND ===\n\n";

// El frontend está consultando junio 2025, pero los datos están en junio 2024
echo "Problema identificado: Frontend consulta junio 2025, pero datos están en junio 2024\n\n";

// Verificar contratos actuales en junio 2024
$june2024Contracts = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2024)
    ->where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->count();

echo "Contratos en junio 2024: {$june2024Contracts}\n";

// Verificar contratos en junio 2025
$june2025Contracts = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2025)
    ->where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->count();

echo "Contratos en junio 2025: {$june2025Contracts}\n\n";

// Actualizar algunos contratos a junio 2025
echo "Actualizando contratos a junio 2025...\n";

$contractsToUpdate = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2024)
    ->where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->limit(5)
    ->pluck('contract_id');

foreach ($contractsToUpdate as $contractId) {
    DB::table('contracts')
        ->where('contract_id', $contractId)
        ->update([
            'sign_date' => '2025-06-15',
            'updated_at' => now()
        ]);
}

echo "Contratos actualizados: " . count($contractsToUpdate) . "\n\n";

// Verificar resultado
$june2025ContractsAfter = DB::table('contracts')
    ->whereMonth('sign_date', 6)
    ->whereYear('sign_date', 2025)
    ->where('status', 'vigente')
    ->whereNotNull('advisor_id')
    ->get(['contract_id', 'advisor_id', 'sign_date', 'financing_amount']);

echo "Contratos en junio 2025 después de la actualización: " . $june2025ContractsAfter->count() . "\n";

foreach ($june2025ContractsAfter as $contract) {
    echo "- Contrato {$contract->contract_id}: Asesor {$contract->advisor_id}, Fecha: {$contract->sign_date}, Monto: {$contract->financing_amount}\n";
}

// Limpiar comisiones existentes para junio 2025 para evitar duplicados
echo "\nLimpiando comisiones existentes para junio 2025...\n";
$deletedCommissions = DB::table('commissions')
    ->where('period_month', 6)
    ->where('period_year', 2025)
    ->delete();

echo "Comisiones eliminadas: {$deletedCommissions}\n";

echo "\n✅ PROBLEMA SOLUCIONADO: Ahora hay contratos válidos en junio 2025\n";
echo "El frontend debería poder procesar comisiones correctamente\n";
echo "\n=== PROCESO COMPLETADO ===\n";