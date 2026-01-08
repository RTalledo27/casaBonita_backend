<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;

echo "Buscando contratos con reservationAmount > 0...\n\n";

$contracts = Contract::where('source', 'logicware')
    ->whereNotNull('logicware_data')
    ->limit(100)
    ->get();

$found = 0;
$withReservation = [];

foreach ($contracts as $contract) {
    $data = json_decode($contract->logicware_data, true);
    
    if (isset($data['financing']['reservationAmount'])) {
        $reservationAmount = $data['financing']['reservationAmount'];
        
        if ($reservationAmount > 0) {
            $found++;
            $withReservation[] = [
                'contract_id' => $contract->contract_id,
                'contract_number' => $contract->contract_number,
                'reservationAmount' => $reservationAmount,
                'separationStartDate' => $data['separationStartDate'] ?? null,
                'separationEndDate' => $data['separationEndDate'] ?? null,
                'proformaStartDate' => $data['proformaStartDate'] ?? null,
                'saleStartDate' => $data['saleStartDate'] ?? null,
            ];
            
            if ($found === 1) {
                echo "✅ PRIMER CONTRATO CON RESERVA ENCONTRADO:\n";
                echo "=====================================\n";
                echo json_encode($data, JSON_PRETTY_PRINT);
                echo "\n\n";
            }
        }
    }
}

echo "\n📊 RESUMEN:\n";
echo "=========\n";
echo "Total contratos revisados: " . count($contracts) . "\n";
echo "Contratos con reservationAmount > 0: " . $found . "\n\n";

if ($found > 0) {
    echo "📋 LISTA DE CONTRATOS CON RESERVA:\n";
    echo "==================================\n";
    foreach ($withReservation as $item) {
        echo sprintf(
            "ID: %d | Contrato: %s | Reserva: %.2f | Separación: %s → %s\n",
            $item['contract_id'],
            $item['contract_number'],
            $item['reservationAmount'],
            $item['separationStartDate'] ?? 'N/A',
            $item['separationEndDate'] ?? 'N/A'
        );
    }
}

echo "\n";
