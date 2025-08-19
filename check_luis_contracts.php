<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Sales\app\Models\Contract;
use Modules\Sales\app\Models\Reservation;
use Modules\Sales\app\Models\Client;

try {
    echo "=== ANÁLISIS DE CONTRATOS DE LUIS TAVARA ===\n\n";
    
    // Buscar contratos por cliente con nombre similar a Luis Tavara
    $contracts = Contract::whereHas('reservation.client', function($q) {
        $q->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%luis%tavara%'])
          ->orWhereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%tavara%luis%']);
    })->with(['reservation.client', 'reservation.lot'])->get();
    
    echo "Contratos encontrados para LUIS TAVARA: " . $contracts->count() . "\n\n";
    
    if ($contracts->count() > 0) {
        echo "DETALLE DE CONTRATOS:\n";
        echo "=====================\n";
        
        foreach($contracts as $contract) {
            $client = $contract->reservation->client;
            $lot = $contract->reservation->lot;
            
            echo "ID Contrato: " . $contract->contract_id . "\n";
            echo "Número: " . ($contract->contract_number ?? 'N/A') . "\n";
            echo "Cliente: " . $client->first_name . " " . $client->last_name . "\n";
            echo "Email: " . ($client->email ?? 'N/A') . "\n";
            echo "Lote: " . ($lot->num_lot ?? 'N/A') . "\n";
            echo "Fecha firma: " . ($contract->sign_date ?? 'N/A') . "\n";
            echo "Precio total: " . ($contract->total_price ?? 'N/A') . "\n";
            echo "---\n";
        }
    }
    
    // También buscar por asesor
    echo "\n=== BÚSQUEDA POR ASESOR ===\n";
    
    $contractsByAdvisor = Contract::whereHas('reservation.advisor.user', function($q) {
        $q->whereRaw('LOWER(CONCAT(first_name, " ", last_name)) LIKE ?', ['%luis%tavara%'])
          ->orWhere('email', 'LIKE', '%luis.tavara%');
    })->with(['reservation.client', 'reservation.lot', 'reservation.advisor.user'])->get();
    
    echo "Contratos donde LUIS TAVARA es el asesor: " . $contractsByAdvisor->count() . "\n\n";
    
    if ($contractsByAdvisor->count() > 0) {
        echo "DETALLE DE CONTRATOS COMO ASESOR:\n";
        echo "=================================\n";
        
        foreach($contractsByAdvisor as $contract) {
            $client = $contract->reservation->client;
            $lot = $contract->reservation->lot;
            $advisor = $contract->reservation->advisor;
            
            echo "ID Contrato: " . $contract->contract_id . "\n";
            echo "Número: " . ($contract->contract_number ?? 'N/A') . "\n";
            echo "Cliente: " . $client->first_name . " " . $client->last_name . "\n";
            echo "Asesor: " . ($advisor->user ? $advisor->user->first_name . " " . $advisor->user->last_name : 'N/A') . "\n";
            echo "Email Asesor: " . ($advisor->user->email ?? 'N/A') . "\n";
            echo "Lote: " . ($lot->num_lot ?? 'N/A') . "\n";
            echo "Fecha firma: " . ($contract->sign_date ?? 'N/A') . "\n";
            echo "---\n";
        }
    }
    
    // Resumen final
    echo "\n=== RESUMEN ===\n";
    echo "Total contratos como cliente: " . $contracts->count() . "\n";
    echo "Total contratos como asesor: " . $contractsByAdvisor->count() . "\n";
    echo "Total general: " . ($contracts->count() + $contractsByAdvisor->count()) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}