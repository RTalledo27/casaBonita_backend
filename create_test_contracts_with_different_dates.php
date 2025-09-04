<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Sales\Models\Contract;
use Carbon\Carbon;

echo "=== Creando contratos de prueba con fechas diferentes ===\n\n";

// Crear contratos con diferentes fechas de venta
$testContracts = [
    [
        'contract_number' => 'TEST-MARZO-001',
        'client_id' => 1,
        'lot_id' => 1,
        'sign_date' => '2024-03-15',
        'total_price' => 150000,
        'down_payment' => 30000,
        'financing_amount' => 120000,
        'interest_rate' => 0.085,
        'term_months' => 60,
        'monthly_payment' => 2500,
        'status' => 'vigente',
        'currency' => 'PEN'
    ],
    [
        'contract_number' => 'TEST-JUNIO-001',
        'client_id' => 2,
        'lot_id' => 2,
        'sign_date' => '2024-06-20',
        'total_price' => 200000,
        'down_payment' => 40000,
        'financing_amount' => 160000,
        'interest_rate' => 0.085,
        'term_months' => 60,
        'monthly_payment' => 3200,
        'status' => 'vigente',
        'currency' => 'PEN'
    ],
    [
        'contract_number' => 'TEST-SEPTIEMBRE-001',
        'client_id' => 3,
        'lot_id' => 3,
        'sign_date' => '2024-09-10',
        'total_price' => 180000,
        'down_payment' => 36000,
        'financing_amount' => 144000,
        'interest_rate' => 0.085,
        'term_months' => 60,
        'monthly_payment' => 2900,
        'status' => 'vigente',
        'currency' => 'PEN'
    ],
    [
        'contract_number' => 'TEST-DICIEMBRE-001',
        'client_id' => 4,
        'lot_id' => 4,
        'sign_date' => '2024-12-05',
        'total_price' => 220000,
        'down_payment' => 44000,
        'financing_amount' => 176000,
        'interest_rate' => 0.085,
        'term_months' => 60,
        'monthly_payment' => 3500,
        'status' => 'vigente',
        'currency' => 'PEN'
    ]
];

foreach($testContracts as $contractData) {
    // Verificar si ya existe
    $existing = Contract::where('contract_number', $contractData['contract_number'])->first();
    if (!$existing) {
        $contract = Contract::create($contractData);
        echo "Creado: {$contract->contract_number} - Fecha: {$contract->sign_date}\n";
        
        // Mostrar fecha calculada
        $calculatedStart = Carbon::parse($contract->sign_date)->addMonth()->startOfMonth()->format('Y-m-d');
        echo "  -> Fecha de inicio calculada: {$calculatedStart}\n\n";
    } else {
        echo "Ya existe: {$contractData['contract_number']}\n\n";
    }
}

echo "=== Contratos de prueba creados ===\n";