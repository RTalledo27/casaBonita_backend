<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;

class TestContractsSeeder extends Seeder
{
    public function run()
    {
        // Get first 5 reservations
        $reservations = Reservation::take(5)->get();
        
        if ($reservations->count() === 0) {
            $this->command->info('No reservations found. Creating test reservations first.');
            return;
        }

        $contracts = [
            [
                'contract_number' => 'CT-001',
                'total_price' => 100000,
                'down_payment' => 25000,
                'financing_amount' => 75000,
                'interest_rate' => 0.12,
                'term_months' => 60,
                'monthly_payment' => 1500,
                'currency' => 'USD',
                'status' => 'vigente'
            ],
            [
                'contract_number' => 'CT-002',
                'total_price' => 150000,
                'down_payment' => 30000,
                'financing_amount' => 120000,
                'interest_rate' => 0.10,
                'term_months' => 72,
                'monthly_payment' => 2000,
                'currency' => 'USD',
                'status' => 'vigente'
            ],
            [
                'contract_number' => 'CT-003',
                'total_price' => 80000,
                'down_payment' => 20000,
                'financing_amount' => 60000,
                'interest_rate' => 0.15,
                'term_months' => 48,
                'monthly_payment' => 1800,
                'currency' => 'USD',
                'status' => 'activo'
            ],
            [
                'contract_number' => 'CT-004',
                'total_price' => 200000,
                'down_payment' => 50000,
                'financing_amount' => 150000,
                'interest_rate' => 0.08,
                'term_months' => 84,
                'monthly_payment' => 2500,
                'currency' => 'USD',
                'status' => 'vigente'
            ],
            [
                'contract_number' => 'CT-005',
                'total_price' => 120000,
                'down_payment' => 24000,
                'financing_amount' => 96000,
                'interest_rate' => 0.11,
                'term_months' => 60,
                'monthly_payment' => 2100,
                'currency' => 'USD',
                'status' => 'activo'
            ]
        ];

        foreach ($contracts as $index => $contractData) {
            if (isset($reservations[$index])) {
                $contractData['reservation_id'] = $reservations[$index]->reservation_id;
                $contractData['sign_date'] = now()->subDays(rand(1, 30));
                
                Contract::create($contractData);
                $this->command->info("Created contract: {$contractData['contract_number']}");
            }
        }
        
        $this->command->info('Test contracts seeded successfully!');
    }
}