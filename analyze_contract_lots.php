<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Contract;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 ANALIZANDO CONTRATOS Y LOTES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Obtener primeros 5 contratos
$contracts = Contract::with(['client', 'lot.manzana', 'advisor.user'])
    ->limit(5)
    ->get();

foreach ($contracts as $contract) {
    echo "📄 Contrato: {$contract->contract_number}\n";
    echo "   Cliente: {$contract->client->first_name} {$contract->client->last_name}\n";
    echo "   DOC: {$contract->client->doc_number}\n";
    echo "   Email: {$contract->client->email}\n";
    echo "   Teléfono: {$contract->client->primary_phone}\n";
    
    if ($contract->advisor) {
        $advisorName = ($contract->advisor->user->first_name ?? '') . ' ' . ($contract->advisor->user->last_name ?? '');
        echo "   ✅ Asesor: {$advisorName}\n";
    } else {
        echo "   ❌ Sin asesor\n";
    }
    
    if ($contract->lot_id) {
        echo "   Lote ID: {$contract->lot_id}\n";
        if ($contract->lot) {
            echo "   Lote Número: {$contract->lot->lot_number}\n";
            if ($contract->lot->manzana) {
                echo "   Manzana: {$contract->lot->manzana->name}\n";
            }
        }
    } else {
        echo "   ❌ Sin lote asignado\n";
    }
    
    if ($contract->logicware_data) {
        $data = json_decode($contract->logicware_data, true);
        
        if (isset($data['units'][0]['unitNumber'])) {
            echo "   📦 Unit en Logicware: {$data['units'][0]['unitNumber']}\n";
        }
        
        // Mostrar todos los datos del cliente de Logicware
        echo "\n   📋 Datos completos del cliente en Logicware:\n";
        $clientFields = [
            'documentNumber', 'fullName', 'firstName', 'paternalSurname', 'maternalSurname',
            'email', 'phone', 'birthDate', 'gender', 'address', 'district', 'province', 'department'
        ];
        
        foreach ($clientFields as $field) {
            if (isset($data[$field])) {
                $value = is_string($data[$field]) ? substr($data[$field], 0, 50) : $data[$field];
                echo "      • {$field}: {$value}\n";
            }
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
}

// Estadísticas
$total = Contract::count();
$withLot = Contract::whereNotNull('lot_id')->count();
$withoutLot = Contract::whereNull('lot_id')->count();

echo "📊 ESTADÍSTICAS:\n";
echo "   Total contratos: {$total}\n";
echo "   ✅ Con lote: {$withLot} (" . round($withLot/$total*100, 1) . "%)\n";
echo "   ❌ Sin lote: {$withoutLot} (" . round($withoutLot/$total*100, 1) . "%)\n\n";
