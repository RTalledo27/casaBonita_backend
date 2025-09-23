<?php

namespace Modules\Collections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Models\Client;
use Modules\Security\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\CommissionPaymentVerification;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\app\Services\CommissionPaymentVerificationService;

class CustomerPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'client_id',
        'ar_id',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'notes',
        'processed_by',
        'journal_entry_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2'
    ];

    protected $dates = ['deleted_at'];

    // Métodos de pago disponibles
    const METHOD_CASH = 'CASH';
    const METHOD_TRANSFER = 'TRANSFER';
    const METHOD_CHECK = 'CHECK';
    const METHOD_C_CARD = 'CREDIT CARD';
    const METHOD_D_CARD = 'DEBIT CARD';
    const METHOD_YAPE = 'YAPE';
    const METHOD_PLIN = 'PLIN';
    const METHOD_OTHER = 'OTHER';

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function accountReceivable()
    {
        return $this->belongsTo(AccountReceivable::class, 'ar_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by', 'user_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    // Scopes
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByProcessor($query, $userId)
    {
        return $query->where('processed_by', $userId);
    }

    // Métodos de negocio
    public static function generatePaymentNumber()
    {
        $lastPayment = self::orderBy('payment_id', 'desc')->first();
        $nextNumber = $lastPayment ? intval(substr($lastPayment->payment_number, 4)) + 1 : 1;
        return 'PAY-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function requiresReference()
    {
        return in_array($this->payment_method, [self::METHOD_TRANSFER, self::METHOD_CHECK]);
    }

    // === INTEGRACIÓN CON SISTEMA DE COMISIONES ===

    /**
     * Relación con las verificaciones de comisión
     */
    public function commissionVerifications()
    {
        return $this->hasMany(CommissionPaymentVerification::class, 'customer_payment_id', 'payment_id');
    }

    /**
     * Boot del modelo para eventos automáticos
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se dispara después de crear un pago
        static::created(function ($payment) {
            $payment->triggerCommissionVerification();
        });

        // Evento que se dispara después de actualizar un pago
        static::updated(function ($payment) {
            $payment->triggerCommissionVerification();
        });
    }

    /**
     * Dispara la verificación automática de comisiones
     */
    public function triggerCommissionVerification()
    {
        try {
            // Solo procesar si el pago tiene una cuenta por cobrar asociada
            if (!$this->ar_id || !$this->accountReceivable) {
                return;
            }

            $accountReceivable = $this->accountReceivable;
            
            // Verificar si existe un contrato asociado
            if (!$accountReceivable->contract_id) {
                return;
            }

            // Buscar comisiones que requieren verificación para este contrato
            $commissions = Commission::where('contract_id', $accountReceivable->contract_id)
                ->where('requires_client_payment_verification', true)
                ->where('payment_verification_status', '!=', 'fully_verified')
                ->get();

            if ($commissions->isEmpty()) {
                return;
            }

            // Procesar verificación para cada comisión
            $verificationService = new CommissionPaymentVerificationService();
            
            foreach ($commissions as $commission) {
                try {
                    $verificationService->verifyClientPayments($commission);
                    
                    Log::info('Verificación automática de comisión procesada', [
                        'payment_id' => $this->payment_id,
                        'commission_id' => $commission->commission_id,
                        'contract_id' => $accountReceivable->contract_id,
                        'ar_id' => $this->ar_id
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Error en verificación automática de comisión', [
                        'payment_id' => $this->payment_id,
                        'commission_id' => $commission->commission_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error general en triggerCommissionVerification', [
                'payment_id' => $this->payment_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica si este pago está asociado a verificaciones de comisión
     */
    public function hasCommissionVerifications(): bool
    {
        return $this->commissionVerifications()->exists();
    }

    /**
     * Obtiene las comisiones afectadas por este pago
     */
    public function getAffectedCommissions()
    {
        if (!$this->accountReceivable || !$this->accountReceivable->contract_id) {
            return collect();
        }

        return Commission::where('contract_id', $this->accountReceivable->contract_id)
            ->where('requires_client_payment_verification', true)
            ->get();
    }

    /**
     * Obtiene el número de cuota basado en la fecha de vencimiento
     */
    public function getInstallmentNumber()
    {
        if (!$this->accountReceivable || !$this->accountReceivable->contract_id) {
            return null;
        }

        // Obtener todas las cuentas por cobrar del contrato ordenadas por fecha
        $accountsReceivable = AccountReceivable::where('contract_id', $this->accountReceivable->contract_id)
            ->orderBy('due_date', 'asc')
            ->get();

        // Encontrar la posición de esta cuenta por cobrar
        $position = $accountsReceivable->search(function ($ar) {
            return $ar->ar_id === $this->ar_id;
        });

        return $position !== false ? $position + 1 : null;
    }

    /**
     * Verifica si este pago corresponde a la primera o segunda cuota
     */
    public function getInstallmentType()
    {
        $installmentNumber = $this->getInstallmentNumber();
        
        if ($installmentNumber === 1) {
            return 'first';
        } elseif ($installmentNumber === 2) {
            return 'second';
        }
        
        return null;
    }
}
