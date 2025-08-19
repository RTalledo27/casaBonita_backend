<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Sales\Models\Contract;
use App\Models\CommissionPaymentVerification;

// use Modules\HumanResources\Database\Factories\CommissionFactory;

class Commission extends Model
{
    use HasFactory;

    protected $primaryKey = 'commission_id';

    protected $fillable = [
        'employee_id',
        'contract_id',
        'commission_type',
        'sale_amount',
        'installment_plan',
        'commission_percentage',
        'commission_amount',
        'payment_status',
        'payment_date',
        'period_month',
        'period_year',
        'commission_period',
        'payment_period',
        'payment_percentage',
        'status',
        'parent_commission_id',
        'payment_part',
        'notes',
        'payment_type',
        'total_commission_amount',
        'sales_count',
        'requires_client_payment_verification',
        'payment_verification_status',
        'first_payment_verified_at',
        'second_payment_verified_at',
        'is_eligible_for_payment',
        'is_payable',
        'verification_notes',
        // Campos para integración HR-Collections
        'verification_status',
        'customer_id',
        'period_start',
        'period_end',
        'verified_at',
        'verified_amount',
        'eligible_date'
    ];

    protected $casts = [
        'sale_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'total_commission_amount' => 'decimal:2',
        'payment_percentage' => 'decimal:2',
        'payment_date' => 'date',
        'sales_count' => 'integer',
        'payment_part' => 'integer',
        'requires_client_payment_verification' => 'boolean',
        'first_payment_verified_at' => 'datetime',
        'second_payment_verified_at' => 'datetime',
        'is_eligible_for_payment' => 'boolean',
        'is_payable' => 'boolean',
        // Campos para integración HR-Collections
        'period_start' => 'date',
        'period_end' => 'date',
        'verified_at' => 'datetime',
        'verified_amount' => 'decimal:2'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function customer()
    {
        return $this->belongsTo(\Modules\CRM\Models\Client::class, 'customer_id', 'client_id');
    }

    public static function calculateCommissionPercentage($salesCount, $installmentPlan)
    {
        $commissionTable = [
            'short_term' => [10 => 4.20, 8 => 4.00, 6 => 3.00, 'default' => 2.00],
            'long_term' => [10 => 3.00, 8 => 2.50, 6 => 1.50, 'default' => 1.00]
        ];

        $planType = in_array($installmentPlan, [12, 24, 36]) ? 'short_term' : 'long_term';
        $table = $commissionTable[$planType];

        if ($salesCount >= 10) return $table[10];
        if ($salesCount >= 8) return $table[8];
        if ($salesCount >= 6) return $table[6];
        return $table['default'];
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pendiente');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'pagado');
    }

    public function scopeByPeriod($query, $month, $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }

    public function scopeByCommissionPeriod($query, $period)
    {
        return $query->where('commission_period', $period);
    }

    public function scopeByPaymentPeriod($query, $period)
    {
        return $query->where('payment_period', $period);
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopePartiallyPaid($query)
    {
        return $query->where('status', 'partially_paid');
    }

    public function scopeFullyPaid($query)
    {
        return $query->where('status', 'fully_paid');
    }

    // Relación con comisión padre (para pagos divididos)
    public function parentCommission()
    {
        return $this->belongsTo(Commission::class, 'parent_commission_id', 'commission_id');
    }

    // Relación con comisiones hijas (partes del pago)
    public function childCommissions()
    {
        return $this->hasMany(Commission::class, 'parent_commission_id', 'commission_id');
    }

    /**
     * Verifica si esta comisión es un pago dividido
     */
    public function isSplitPayment(): bool
    {
        return !is_null($this->parent_commission_id);
    }

    /**
     * Verifica si esta comisión tiene pagos divididos
     */
    public function hasSplitPayments(): bool
    {
        return $this->childCommissions()->exists();
    }

    /**
     * Obtiene el monto total de todos los pagos relacionados
     */
    public function getTotalSplitAmount(): float
    {
        if ($this->isSplitPayment()) {
            return $this->parentCommission->childCommissions()->sum('commission_amount');
        }
        
        return $this->childCommissions()->sum('commission_amount') + $this->commission_amount;
    }

    /**
     * Genera el período de comisión basado en mes y año
     */
    public static function generateCommissionPeriod(int $month, int $year): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }

    /**
     * Genera el período de pago con sufijo de parte
     */
    public static function generatePaymentPeriod(int $month, int $year, int $part = 1): string
    {
        return sprintf('%04d-%02d-P%d', $year, $month, $part);
    }

    // === MÉTODOS PARA VERIFICACIÓN DE PAGOS ===

    /**
     * Relación con las verificaciones de pago
     */
    public function paymentVerifications()
    {
        return $this->hasMany(CommissionPaymentVerification::class, 'commission_id', 'commission_id');
    }

    /**
     * Verificación de la primera cuota
     */
    public function firstPaymentVerification()
    {
        return $this->hasOne(CommissionPaymentVerification::class, 'commission_id', 'commission_id')
                    ->where('payment_installment', 'first');
    }

    /**
     * Verificación de la segunda cuota
     */
    public function secondPaymentVerification()
    {
        return $this->hasOne(CommissionPaymentVerification::class, 'commission_id', 'commission_id')
                    ->where('payment_installment', 'second');
    }

    /**
     * Scope para comisiones que requieren verificación
     */
    public function scopeRequiresVerification($query)
    {
        return $query->where('requires_client_payment_verification', true);
    }

    /**
     * Scope para comisiones elegibles para pago
     */
    public function scopeEligibleForPayment($query)
    {
        return $query->where('is_eligible_for_payment', true);
    }

    /**
     * Scope por estado de verificación
     */
    public function scopeByVerificationStatus($query, $status)
    {
        return $query->where('payment_verification_status', $status);
    }

    /**
     * Verifica si la comisión requiere verificación de pagos
     */
    public function requiresPaymentVerification(): bool
    {
        return $this->requires_client_payment_verification;
    }

    /**
     * Verifica si la primera cuota está verificada
     */
    public function isFirstPaymentVerified(): bool
    {
        return !is_null($this->first_payment_verified_at) && 
               in_array($this->payment_verification_status, ['first_payment_verified', 'fully_verified']);
    }

    /**
     * Verifica si la segunda cuota está verificada
     */
    public function isSecondPaymentVerified(): bool
    {
        return !is_null($this->second_payment_verified_at) && 
               in_array($this->payment_verification_status, ['second_payment_verified', 'fully_verified']);
    }

    /**
     * Verifica si ambas cuotas están verificadas
     */
    public function isFullyVerified(): bool
    {
        return $this->payment_verification_status === 'fully_verified';
    }

    /**
     * Verifica si la comisión es elegible para pago
     */
    public function isEligibleForPayment(): bool
    {
        // Si no requiere verificación, siempre es elegible
        if (!$this->requiresPaymentVerification()) {
            return true;
        }

        // Si requiere verificación, debe cumplir las condiciones
        return $this->is_eligible_for_payment;
    }

    /**
     * Obtiene el nombre legible del estado de verificación
     */
    public function getVerificationStatusNameAttribute(): string
    {
        if (!$this->requiresPaymentVerification()) {
            return 'No requiere verificación';
        }

        return match($this->payment_verification_status) {
            'pending_verification' => 'Pendiente de verificación',
            'first_payment_verified' => 'Primera cuota verificada',
            'second_payment_verified' => 'Segunda cuota verificada',
            'fully_verified' => 'Completamente verificado',
            'verification_failed' => 'Verificación fallida',
            default => 'Estado desconocido'
        };
    }

    /**
     * Obtiene la clase CSS para el estado de verificación
     */
    public function getVerificationStatusClassAttribute(): string
    {
        if (!$this->requiresPaymentVerification()) {
            return 'text-blue-600 bg-blue-100';
        }

        return match($this->payment_verification_status) {
            'pending_verification' => 'text-yellow-600 bg-yellow-100',
            'first_payment_verified' => 'text-orange-600 bg-orange-100',
            'second_payment_verified' => 'text-orange-600 bg-orange-100',
            'fully_verified' => 'text-green-600 bg-green-100',
            'verification_failed' => 'text-red-600 bg-red-100',
            default => 'text-gray-600 bg-gray-100'
        };
    }

    /**
     * Obtiene el progreso de verificación como porcentaje
     */
    public function getVerificationProgressAttribute(): int
    {
        if (!$this->requiresPaymentVerification()) {
            return 100;
        }

        return match($this->payment_verification_status) {
            'pending_verification' => 0,
            'first_payment_verified' => 50,
            'second_payment_verified' => 50,
            'fully_verified' => 100,
            'verification_failed' => 0,
            default => 0
        };
    }

    // === SCOPES PARA SISTEMA DE PAGABILIDAD ===

    /**
     * Scope para comisiones pagables (divisiones)
     */
    public function scopePayable($query)
    {
        return $query->where('is_payable', true);
    }

    /**
     * Scope para comisiones no pagables (registros de control/padre)
     */
    public function scopeNonPayable($query)
    {
        return $query->where('is_payable', false);
    }

    /**
     * Scope para obtener solo las divisiones pagables de comisiones divididas
     */
    public function scopePayableDivisions($query)
    {
        return $query->where('is_payable', true)
                    ->whereNotNull('parent_commission_id');
    }

    /**
     * Scope para obtener comisiones padre (registros de control)
     */
    public function scopeParentCommissions($query)
    {
        return $query->where('is_payable', false)
                    ->whereNull('parent_commission_id')
                    ->has('childCommissions');
    }
}
