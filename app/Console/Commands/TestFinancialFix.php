<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Sales\app\Services\ContractImportService;
use Modules\Sales\app\Models\Lot;
use Modules\Sales\app\Models\Client;
use Modules\Sales\app\Models\Employee;
use Modules\Sales\app\Models\Contract;

class TestFinancialFix extends Command
{
    protected $signature = 'test:financial-fix';
    protected $description = 'Test financial data fix in contract import';

    public function handle()
    {
        $this->info('=== Testing Financial Data Fix ===');

        // Get a lot with financial template
        $lot = Lot::with('financialTemplate')->first();
        if (!$lot) {
            $this->error('No lots found in database');
            return 1;
        }

        $this->info("Testing with Lot: {$lot->lot_number}");

        if ($lot->financialTemplate) {
            $this->info('Financial Template found:');
            $this->line("- Total Price: {$lot->financialTemplate->total_price}");
            $this->line("- Down Payment: {$lot->financialTemplate->down_payment}");
            $this->line("- Financing Amount: {$lot->financialTemplate->financing_amount}");
            $this->line("- Monthly Payment: {$lot->financialTemplate->monthly_payment}");
            $this->line("- Term Months: {$lot->financialTemplate->term_months}");
            $this->line("- Interest Rate: {$lot->financialTemplate->interest_rate}");
        } else {
            $this->warn('No financial template found for this lot');
        }

        // Get a client and advisor for testing
        $client = Client::first();
        $advisor = Employee::where('position', 'Asesor')->first();

        if (!$client || !$advisor) {
            $this->error('Missing client or advisor for testing');
            return 1;
        }

        // Test data
        $contractData = [
            'client_id' => $client->id,
            'lot_id' => $lot->id,
            'advisor_id' => $advisor->id,
            'sale_date' => now()->format('Y-m-d'),
            'status' => 'active',
            'currency' => 'USD'
        ];

        $this->info('\n=== Testing createDirectContract ===');

        try {
            $importService = new ContractImportService();
            
            // Use reflection to access private method
            $reflection = new \ReflectionClass($importService);
            $method = $reflection->getMethod('createDirectContract');
            $method->setAccessible(true);
            
            $result = $method->invoke($importService, $contractData);
            
            if ($result['success']) {
                $this->info('Contract created successfully!');
                $this->line("Contract ID: {$result['contract_id']}");
                
                // Get the created contract to verify financial data
                $contract = Contract::find($result['contract_id']);
                
                $this->info('\n=== Financial Data Verification ===');
                $this->line("- Total Price: {$contract->total_price}");
                $this->line("- Down Payment: {$contract->down_payment}");
                $this->line("- Financing Amount: {$contract->financing_amount}");
                $this->line("- Monthly Payment: {$contract->monthly_payment}");
                $this->line("- Term Months: {$contract->term_months}");
                $this->line("- Interest Rate: {$contract->interest_rate}");
                
                // Compare with template if exists
                if ($lot->financialTemplate) {
                    $this->info('\n=== Template vs Contract Comparison ===');
                    $template = $lot->financialTemplate;
                    
                    $matches = [];
                    $matches[] = $template->total_price == $contract->total_price ? '✓' : '✗';
                    $matches[] = $template->down_payment == $contract->down_payment ? '✓' : '✗';
                    $matches[] = $template->financing_amount == $contract->financing_amount ? '✓' : '✗';
                    $matches[] = $template->monthly_payment == $contract->monthly_payment ? '✓' : '✗';
                    $matches[] = $template->term_months == $contract->term_months ? '✓' : '✗';
                    $matches[] = $contract->interest_rate == 0 ? '✓' : '✗'; // Should be 0
                    
                    $this->line("Total Price - Template: {$template->total_price}, Contract: {$contract->total_price} {$matches[0]}");
                    $this->line("Down Payment - Template: {$template->down_payment}, Contract: {$contract->down_payment} {$matches[1]}");
                    $this->line("Financing Amount - Template: {$template->financing_amount}, Contract: {$contract->financing_amount} {$matches[2]}");
                    $this->line("Monthly Payment - Template: {$template->monthly_payment}, Contract: {$contract->monthly_payment} {$matches[3]}");
                    $this->line("Term Months - Template: {$template->term_months}, Contract: {$contract->term_months} {$matches[4]}");
                    $this->line("Interest Rate - Expected: 0, Contract: {$contract->interest_rate} {$matches[5]}");
                    
                    $allMatch = !in_array('✗', $matches);
                    if ($allMatch) {
                        $this->info('\n✅ All financial data matches correctly!');
                    } else {
                        $this->error('\n❌ Some financial data does not match');
                    }
                }
                
                // Clean up - delete the test contract
                $contract->delete();
                $this->info('\nTest contract deleted.');
                
            } else {
                $this->error("Contract creation failed: {$result['message']}");
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
        }

        $this->info('\n=== Test Complete ===');
        return 0;
    }
}