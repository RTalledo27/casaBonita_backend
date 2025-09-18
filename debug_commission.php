<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('debug:commission', function () {
    // Buscar comisión por contrato usando DB directamente
    $commission = DB::table('commissions')
        ->where('contract_id', 'CON20257868')
        ->first();
    
    if ($commission) {
        $this->info('=== COMISIÓN ENCONTRADA ===');
        $this->info('ID: ' . $commission->id);
        $this->info('Commission ID: ' . $commission->commission_id);
        $this->info('Contract ID: ' . $commission->contract_id);
        $this->info('Payment Part: ' . $commission->payment_part);
        $this->info('Requires Verification: ' . ($commission->requires_client_payment_verification ? 'YES' : 'NO'));
        $this->info('Payment Verification Status: ' . $commission->payment_verification_status);
        $this->info('First Payment Verified At: ' . $commission->first_payment_verified_at);
        $this->info('Second Payment Verified At: ' . $commission->second_payment_verified_at);
        
        // Buscar cuentas por cobrar del contrato
        $this->info('');
        $this->info('=== CUENTAS POR COBRAR ===');
        $accountsReceivable = DB::table('accounts_receivable')
            ->where('contract_id', 'CON20257868')
            ->orderBy('installment_number')
            ->get();
        
        foreach ($accountsReceivable as $ar) {
            $this->info("AR ID: {$ar->ar_id}, Installment: {$ar->installment_number}, Amount: {$ar->amount}, Due Date: {$ar->due_date}");
            
            // Buscar pagos para esta cuenta por cobrar
            $payments = DB::table('customer_payments')
                ->where('ar_id', $ar->ar_id)
                ->get();
            
            foreach ($payments as $payment) {
                $this->info("  - Payment ID: {$payment->payment_id}, Amount: {$payment->amount}, Date: {$payment->payment_date}, Status: {$payment->status}");
            }
        }
        
    } else {
        $this->error('No se encontró comisión para el contrato CON20257868');
        
        // Buscar todas las comisiones para ver qué contratos existen
        $this->info('');
        $this->info('=== PRIMERAS 10 COMISIONES ===');
        $allCommissions = DB::table('commissions')->take(10)->get();
        foreach ($allCommissions as $comm) {
            $this->info("Contract: {$comm->contract_id}, Commission ID: {$comm->commission_id}");
        }
    }
})->purpose('Debug commission verification issue');