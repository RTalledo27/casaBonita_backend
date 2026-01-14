<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Accounting\Models\JournalEntry;
use Modules\Collections\Models\CustomerPayment;
use Modules\Collections\Models\AccountReceivable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

// use Modules\Sales\Database\Factories\PaymentFactory;

class Payment extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_id';
    public    $timestamps  = false;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'schedule_id',
        'journal_entry_id',
        'contract_id',
        'payment_date',
        'amount',
        'method',
        'reference',
        'voucher_path'
    ];

    // protected static function newFactory(): PaymentFactory
    // {
    //     // return PaymentFactory::new();
    // }

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'schedule_id', 'schedule_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    /**
     * Boot del modelo para sincronización automática
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se dispara después de crear un pago
        static::created(function ($payment) {
            $payment->syncWithCollections();
        });
    }

    /**
     * Sincroniza el pago con el módulo Collections
     */
    public function syncWithCollections()
    {
        try {
            // Verificar que el contract_id y el contrato existan
            if (!$this->contract_id || !$this->contract) {
                Log::warning('No se puede sincronizar: contract_id o contrato no válido', [
                    'payment_id' => $this->payment_id,
                    'contract_id' => $this->contract_id,
                    'schedule_id' => $this->schedule_id
                ]);
                return;
            }

            // Buscar la cuenta por cobrar correspondiente al schedule
            $accountReceivable = AccountReceivable::where('contract_id', $this->contract_id)
                ->where('installment_number', $this->schedule->installment_number ?? 1)
                ->first();

            if (!$accountReceivable) {
                Log::warning('No se encontró cuenta por cobrar para sincronizar', [
                    'payment_id' => $this->payment_id,
                    'contract_id' => $this->contract_id,
                    'schedule_id' => $this->schedule_id
                ]);
                return;
            }

            // Crear el CustomerPayment correspondiente
            $customerPayment = CustomerPayment::create([
                'client_id' => $this->contract->client_id,
                'ar_id' => $accountReceivable->ar_id,
                'payment_number' => CustomerPayment::generatePaymentNumber(),
                'payment_date' => $this->payment_date,
                'amount' => $this->amount,
                'currency' => 'GTQ',
                'payment_method' => $this->method,
                'reference_number' => $this->reference,
                'notes' => 'Sincronizado desde módulo Sales - ' . $this->reference,
                'processed_by' => auth()->id() ?? 1
            ]);

            Log::info('Pago sincronizado con Collections', [
                'sales_payment_id' => $this->payment_id,
                'customer_payment_id' => $customerPayment->payment_id,
                'contract_id' => $this->contract_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error sincronizando pago con Collections', [
                'payment_id' => $this->payment_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
