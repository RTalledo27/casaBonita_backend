<?php

namespace Modules\Collections\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;

class AccountingService
{
    /**
     * Crear asiento contable para un pago recibido
     */
    public function createPaymentJournalEntry(CustomerPayment $payment): JournalEntry
    {
        return DB::transaction(function () use ($payment) {
            // Crear el asiento contable
            $journalEntry = JournalEntry::create([
                'entry_number' => $this->generateEntryNumber(),
                'date' => $payment->payment_date,
                'description' => "Pago recibido - {$payment->payment_number}",
                'reference' => $payment->payment_number,
                'posted_by' => $payment->processed_by,
                'status' => 'POSTED'
            ]);

            // Línea de débito: Caja/Banco (según método de pago)
            JournalLine::create([
                'journal_entry_id' => $journalEntry->journal_entry_id,
                'account_id' => $this->getCashAccountId($payment->payment_method),
                'description' => "Pago recibido de cliente",
                'debit' => $payment->amount,
                'credit' => 0
            ]);

            // Línea de crédito: Cuentas por Cobrar
            JournalLine::create([
                'journal_entry_id' => $journalEntry->journal_entry_id,
                'account_id' => $this->getAccountsReceivableAccountId(),
                'description' => "Aplicación de pago a cuenta por cobrar",
                'debit' => 0,
                'credit' => $payment->amount
            ]);

            return $journalEntry;
        });
    }

    /**
     * Crear asiento contable para una nueva cuenta por cobrar
     */
    public function createAccountReceivableJournalEntry(AccountReceivable $accountReceivable): JournalEntry
    {
        return DB::transaction(function () use ($accountReceivable) {
            $journalEntry = JournalEntry::create([
                'entry_number' => $this->generateEntryNumber(),
                'date' => $accountReceivable->issue_date,
                'description' => "Cuenta por cobrar - {$accountReceivable->ar_number}",
                'reference' => $accountReceivable->ar_number,
                'posted_by' => auth()->id(),
                'status' => 'POSTED'
            ]);

            // Débito: Cuentas por Cobrar
            JournalLine::create([
                'journal_entry_id' => $journalEntry->journal_entry_id,
                'account_id' => $this->getAccountsReceivableAccountId(),
                'description' => "Cuenta por cobrar generada",
                'debit' => $accountReceivable->original_amount,
                'credit' => 0
            ]);

            // Crédito: Ingresos por Ventas
            JournalLine::create([
                'journal_entry_id' => $journalEntry->journal_entry_id,
                'account_id' => $this->getSalesRevenueAccountId(),
                'description' => "Ingreso por venta",
                'debit' => 0,
                'credit' => $accountReceivable->original_amount
            ]);

            return $journalEntry;
        });
    }

    /**
     * Obtener ID de cuenta de caja según método de pago
     */
    private function getCashAccountId(string $paymentMethod): int
    {
        $accountCodes = [
            CustomerPayment::METHOD_CASH => '1010000', // Caja Chica
            CustomerPayment::METHOD_TRANSFER => '1010002', // Banco Corriente
            CustomerPayment::METHOD_CHECK => '1010002', // Banco Corriente
            CustomerPayment::METHOD_C_CARD => '1010003', // Banco Tarjetas
            CustomerPayment::METHOD_D_CARD => '1010004', // Banco Tarjetas
            CustomerPayment::METHOD_YAPE => '1010005', // Yape
            CustomerPayment::METHOD_PLIN => '1010006', // Plin
            CustomerPayment::METHOD_OTHER => '1010001', // Caja Chica por defecto
        ];

        $accountCode = $accountCodes[$paymentMethod] ?? '1010001';

        $account = ChartOfAccount::where('account_code', $accountCode)->first();
        return $account ? $account->account_id : 1; // Fallback a cuenta 1
    }

    /**
     * Obtener ID de cuenta de cuentas por cobrar
     */
    private function getAccountsReceivableAccountId(): int
    {
        $account = ChartOfAccount::where('account_code', '1020001')->first(); // Cuentas por Cobrar Comerciales
        return $account ? $account->account_id : 2; // Fallback
    }

    /**
     * Obtener ID de cuenta de ingresos por ventas
     */
    private function getSalesRevenueAccountId(): int
    {
        $account = ChartOfAccount::where('account_code', '4010001')->first(); // Ventas
        return $account ? $account->account_id : 3; // Fallback
    }

    /**
     * Generar número de asiento contable
     */
    private function generateEntryNumber(): string
    {
        $lastEntry = JournalEntry::orderBy('journal_entry_id', 'desc')->first();
        $nextNumber = $lastEntry ? intval(substr($lastEntry->entry_number, 3)) + 1 : 1;
        return 'JE-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
