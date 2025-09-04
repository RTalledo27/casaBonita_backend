<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\Models\Contract;

try {
    // Simular exactamente lo que hace el método withFinancing
    $query = Contract::with(['reservation.client', 'reservation.lot.manzana'])
        ->where('status', 'active')
        ->where('financing_amount', '>', 0);
    
    $contracts = $query->paginate(15);
    
    // El controlador devuelve los datos sin transformar
    $response = [
        'success' => true,
        'data' => $contracts->items(),
        'meta' => [
            'current_page' => $contracts->currentPage(),
            'per_page' => $contracts->perPage(),
            'total' => $contracts->total(),
            'last_page' => $contracts->lastPage()
        ]
    ];
    
    echo "Respuesta simulada de la API withFinancing:\n";
    echo "Success: " . ($response['success'] ? 'true' : 'false') . "\n";
    echo "Total de contratos devueltos: " . count($response['data']) . "\n";
    
    if (count($response['data']) > 0) {
        echo "\nPrimer contrato de ejemplo:\n";
        $firstContract = $response['data'][0];
        echo "Contract ID: " . $firstContract->contract_id . "\n";
        echo "Contract Number: " . $firstContract->contract_number . "\n";
        echo "Client Name: " . ($firstContract->getClientName() ?? 'N/A') . "\n";
        echo "Lot Name: " . ($firstContract->getLotName() ?? 'N/A') . "\n";
        echo "Financing Amount: " . $firstContract->financing_amount . "\n";
        echo "Term Months: " . $firstContract->term_months . "\n";
        echo "Status: " . $firstContract->status . "\n";
        
        echo "\nReservation ID: " . ($firstContract->reservation_id ?? 'N/A') . "\n";
        echo "Client ID: " . ($firstContract->client_id ?? 'N/A') . "\n";
        echo "Lot ID: " . ($firstContract->lot_id ?? 'N/A') . "\n";
        
        if ($firstContract->reservation) {
            echo "\nReservation data:\n";
            echo "- Reservation ID: " . $firstContract->reservation->reservation_id . "\n";
            if ($firstContract->reservation->client) {
                echo "- Client from reservation: " . $firstContract->reservation->client->first_name . " " . $firstContract->reservation->client->last_name . "\n";
            }
            if ($firstContract->reservation->lot) {
                echo "- Lot from reservation: " . ($firstContract->reservation->lot->lot_number ?? $firstContract->reservation->lot->num_lot ?? 'N/A') . "\n";
            }
        }
        
        echo "\nCampos disponibles en el modelo Contract:\n";
        $attributes = $firstContract->getAttributes();
        foreach (array_keys($attributes) as $key) {
            echo "- $key: " . $attributes[$key] . "\n";
        }
    }
    
    echo "\nMetadata de paginación:\n";
    foreach ($response['meta'] as $key => $value) {
        echo "$key: $value\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}