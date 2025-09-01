<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Collections\Models\CustomerPayment;
use Modules\HumanResources\Models\Commission;
use Modules\Security\Models\User;

class CommissionPaymentVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_id',
        'customer_payment_id',
        'payment_installment',
        'verification_status',
        'verified_at',
        'verified_by',
        'payment_amount',
        'payment_date',
        'verification_notes',
        'verification_metadata'
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'verification_metadata' => 'array'
    ];

    /**
     * Estados de verificación disponibles
     */
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_FAILED = 'failed';
    const STATUS_REVERSED = 'reversed';

    /**
     * Tipos de cuotas
     */
    const INSTALLMENT_FIRST = 'first';
    const INSTALLMENT_SECOND = 'second';

    /**
     * Relación con la comisión
     */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /**
     * Relación con el pago del cliente
     */
    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    /**
     * Relación con el usuario que verificó
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope para verificaciones pendientes
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', self::STATUS_PENDING);
    }

    /**
     * Scope para verificaciones completadas
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::STATUS_VERIFIED);
    }

    /**
     * Scope para primera cuota
     */
    public function scopeFirstInstallment($query)
    {
        return $query->where('payment_installment', self::INSTALLMENT_FIRST);
    }

    /**
     * Scope para segunda cuota
     */
    public function scopeSecondInstallment($query)
    {
        return $query->where('payment_installment', self::INSTALLMENT_SECOND);
    }

    /**
     * Verifica si la verificación está completada
     */
    public function isVerified(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    /**
     * Verifica si la verificación está pendiente
     */
    public function isPending(): bool
    {
        return $this->verification_status === self::STATUS_PENDING;
    }

    /**
     * Verifica si la verificación falló
     */
    public function isFailed(): bool
    {
        return $this->verification_status === self::STATUS_FAILED;
    }

    /**
     * Verifica si la verificación fue revertida
     */
    public function isReversed(): bool
    {
        return $this->verification_status === self::STATUS_REVERSED;
    }

    /**
     * Obtiene el nombre legible del tipo de cuota
     */
    public function getInstallmentNameAttribute(): string
    {
        return match($this->payment_installment) {
            self::INSTALLMENT_FIRST => 'Primera cuota',
            self::INSTALLMENT_SECOND => 'Segunda cuota',
            default => 'Cuota desconocida'
        };
    }

    /**
     * Obtiene el nombre legible del estado de verificación
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->verification_status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_VERIFIED => 'Verificado',
            self::STATUS_FAILED => 'Fallido',
            self::STATUS_REVERSED => 'Revertido',
            default => 'Estado desconocido'
        };
    }

    /**
     * Obtiene la clase CSS para el estado
     */
    public function getStatusClassAttribute(): string
    {
        return match($this->verification_status) {
            self::STATUS_PENDING => 'text-yellow-600 bg-yellow-100',
            self::STATUS_VERIFIED => 'text-green-600 bg-green-100',
            self::STATUS_FAILED => 'text-red-600 bg-red-100',
            self::STATUS_REVERSED => 'text-gray-600 bg-gray-100',
            default => 'text-gray-600 bg-gray-100'
        };
    }
}