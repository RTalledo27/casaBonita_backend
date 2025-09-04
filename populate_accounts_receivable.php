<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

echo "=== Populating accounts_receivable table with sample data ===\n";

try {
    // Check current count
    $currentCount = DB::table('accounts_receivable')->count();
    echo "Current accounts receivable count: $currentCount\n";
    
    if ($currentCount > 0) {
        echo "Table already has data. Adding more records...\n";
    }
    
    // Count contracts
    $contractsCount = DB::table('contracts')->count();
    echo "Available contracts: $contractsCount\n";
    
    if ($contractsCount == 0) {
        echo "❌ No contracts found. Cannot create accounts receivable.\n";
        exit(1);
    }
    
    // Get sample contracts
    $contracts = DB::table('contracts')
        ->select('contract_id', 'client_id', 'financing_amount')
        ->whereNotNull('financing_amount')
        ->where('financing_amount', '>', 0)
        ->limit(5)
        ->get();
    
    echo "Found " . count($contracts) . " contracts with financing\n";
    
    if (count($contracts) == 0) {
        echo "❌ No contracts with financing found\n";
        exit(1);
    }
    
    // Create sample accounts receivable
    $accountsData = [];
    $statuses = ['pending', 'overdue', 'paid', 'partial'];
    
    foreach ($contracts as $index => $contract) {
        // Get client name if possible
        $clientName = 'Cliente ' . $contract->client_id;
        if (Schema::hasTable('clients')) {
            $client = DB::table('clients')
                ->where('client_id', $contract->client_id)
                ->first();
            if ($client && isset($client->full_name)) {
                $clientName = $client->full_name;
            }
        }
        
        // Create 2 accounts receivable per contract
        for ($i = 0; $i < 2; $i++) {
            $originalAmount = round($contract->financing_amount / 2, 2);
            $outstandingAmount = $originalAmount;
            $status = $statuses[array_rand($statuses)];
            
            if ($status === 'paid') {
                $outstandingAmount = 0;
            } elseif ($status === 'partial') {
                $outstandingAmount = round($originalAmount * 0.6, 2);
            }
            
            $issueDate = Carbon::now()->subDays(rand(30, 180));
            $dueDate = $issueDate->copy()->addDays(30);
            
            $accountsData[] = [
                'client_id' => $contract->client_id,
                'contract_id' => $contract->contract_id,
                'account_number' => 'AR-' . str_pad(($currentCount + ($index * 2) + $i + 1), 6, '0', STR_PAD_LEFT),
                'invoice_number' => 'INV-' . str_pad(($currentCount + ($index * 2) + $i + 1), 6, '0', STR_PAD_LEFT),
                'description' => 'Cuota de financiamiento - ' . $clientName,
                'original_amount' => $originalAmount,
                'outstanding_amount' => $outstandingAmount,
                'currency' => 'PEN',
                'issue_date' => $issueDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $status,
                'assigned_collector_id' => null,
                'notes' => 'Cuenta generada automáticamente para pruebas',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
    }
    
    // Insert records
    if (!empty($accountsData)) {
        DB::table('accounts_receivable')->insert($accountsData);
        $totalInserted = count($accountsData);
        echo "✓ Successfully inserted $totalInserted accounts receivable records\n";
        
        // Show summary
        $summary = DB::table('accounts_receivable')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(outstanding_amount) as total_amount'))
            ->groupBy('status')
            ->get();
        
        echo "\n=== Summary by status ===\n";
        foreach ($summary as $row) {
            echo "- {$row->status}: {$row->count} records, Total: S/ " . number_format($row->total_amount, 2) . "\n";
        }
        
        $newTotal = DB::table('accounts_receivable')->count();
        echo "\nTotal accounts receivable records: $newTotal\n";
    } else {
        echo "No data to insert\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}