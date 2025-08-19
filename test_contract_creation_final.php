<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Sales\app\Services\ContractImportService;
use Modules\Sales\app\Models\Lot;
use Modules\Sales\app\Models\LotFinancialTemplate;
use Modules\Sales\app\Models\Contract;
use Modules\Sales\app\Models\Reservation;
use Modules\Collections\app\Models\Client;

echo "=== TEST CREAZIONE CONTRATTO FINALE ===\n\n";

// Dati di test esatti dell'utente
$testData = [
    'ASESOR_NOMBRE' => 'ALISSON TORRES',
    'CLIENTE_NOMBRE_COMPLETO' => 'LUZ AURORA ARMIJOS ROBLEDO',
    'LOTE_NUMERO' => '5',
    'LOTE_MANZANA' => 'H',
    'TIPO_OPERACION' => 'contrato',
    'ESTADO_CONTRATO' => 'ACTIVO'
];

echo "Dati di test:\n";
foreach ($testData as $key => $value) {
    echo "- {$key}: {$value}\n";
}
echo "\n";

try {
    // Inizializza il servizio
    $contractImportService = new ContractImportService();
    
    echo "1. RICERCA DEL LOTE...\n";
    
    // Cerca il lote
    $lot = Lot::with(['manzana', 'financialTemplate'])
        ->whereHas('manzana', function($query) use ($testData) {
            $query->where('name', $testData['LOTE_MANZANA']);
        })
        ->where('number', $testData['LOTE_NUMERO'])
        ->first();
    
    if (!$lot) {
        echo "❌ ERRORE: Lote non trovato (Numero: {$testData['LOTE_NUMERO']}, Manzana: {$testData['LOTE_MANZANA']})\n";
        
        // Mostra lotes disponibili
        echo "\nLotes disponibili:\n";
        $availableLots = Lot::with('manzana')->get();
        foreach ($availableLots as $availableLot) {
            echo "- Lote {$availableLot->number}, Manzana: {$availableLot->manzana->name ?? 'N/A'}\n";
        }
        exit(1);
    }
    
    echo "✅ Lote trovato: ID {$lot->id}, Numero {$lot->number}, Manzana: {$lot->manzana->name}\n";
    
    // Verifica LotFinancialTemplate
    if (!$lot->financialTemplate) {
        echo "❌ ERRORE: LotFinancialTemplate non trovato per il lote {$lot->id}\n";
        exit(1);
    }
    
    $template = $lot->financialTemplate;
    echo "✅ LotFinancialTemplate trovato: ID {$template->id}\n";
    echo "   - Precio Lista: {$template->precio_lista}\n";
    echo "   - Precio Venta: {$template->precio_venta}\n";
    echo "   - Cuota Inicial: {$template->cuota_inicial}\n";
    echo "   - Cuota Balon: {$template->cuota_balon}\n";
    echo "   - Financing Amount: {$template->getFinancingAmount()}\n";
    
    echo "\n2. RICERCA/CREAZIONE CLIENT...\n";
    
    // Cerca o crea il cliente
    $clientName = $testData['CLIENTE_NOMBRE_COMPLETO'];
    $client = Client::where('name', 'LIKE', "%{$clientName}%")->first();
    
    if (!$client) {
        echo "Cliente non trovato, creazione nuovo cliente...\n";
        $client = Client::create([
            'name' => $clientName,
            'email' => strtolower(str_replace(' ', '.', $clientName)) . '@test.com',
            'phone' => '0999999999',
            'address' => 'Test Address'
        ]);
        echo "✅ Cliente creato: ID {$client->id}\n";
    } else {
        echo "✅ Cliente trovato: ID {$client->id}, Nome: {$client->name}\n";
    }
    
    echo "\n3. PROCESSAMENTO EXCEL SIMPLIFIED...\n";
    
    // Simula i dati Excel
    $excelData = [
        [
            'ASESOR_NOMBRE' => $testData['ASESOR_NOMBRE'],
            'CLIENTE_NOMBRE_COMPLETO' => $testData['CLIENTE_NOMBRE_COMPLETO'],
            'LOTE_NUMERO' => $testData['LOTE_NUMERO'],
            'LOTE_MANZANA' => $testData['LOTE_MANZANA'],
            'TIPO_OPERACION' => $testData['TIPO_OPERACION'],
            'ESTADO_CONTRATO' => $testData['ESTADO_CONTRATO'],
            'FECHA_FIRMA' => '2024-01-15',
            'MONEDA' => 'USD',
            'CUOTAS' => '24',
            'TASA_INTERES' => '12.5',
            'OBSERVACIONES' => 'Test contract creation',
            'FECHA_VENCIMIENTO' => '2026-01-15',
            'ESTADO_PAGO' => 'PENDIENTE',
            'MET