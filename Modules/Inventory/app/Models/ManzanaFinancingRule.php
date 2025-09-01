<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManzanaFinancingRule extends Model
{
    protected $fillable = [
        'manzana_id',
        'financing_type',
        'max_installments',
        'min_down_payment_percentage',
        'allows_balloon_payment',
        'allows_bpp_bonus'
    ];

    protected $casts = [
        'max_installments' => 'integer',
        'min_down_payment_percentage' => 'decimal:2',
        'allows_balloon_payment' => 'boolean',
        'allows_bpp_bonus' => 'boolean'
    ];

    /**
     * Relación con la manzana
     */
    public function manzana(): BelongsTo
    {
        return $this->belongsTo(Manzana::class, 'manzana_id', 'manzana_id');
    }

    /**
     * Verifica si la manzana solo acepta pagos al contado
     */
    public function isCashOnly(): bool
    {
        return $this->financing_type === 'cash_only';
    }

    /**
     * Verifica si la manzana permite financiamiento a plazos
     */
    public function allowsInstallments(): bool
    {
        return in_array($this->financing_type, ['installments', 'mixed']);
    }

    /**
     * Obtiene las opciones de cuotas disponibles para esta manzana
     */
    public function getAvailableInstallmentOptions(): array
    {
        if (!$this->allowsInstallments()) {
            return [];
        }

        $options = [];
        if ($this->max_installments >= 24) $options[] = 24;
        if ($this->max_installments >= 40) $options[] = 40;
        if ($this->max_installments >= 44) $options[] = 44;
        if ($this->max_installments >= 55) $options[] = 55;

        return $options;
    }

    /**
     * Valida si un número de cuotas es válido para esta manzana
     */
    public function isValidInstallmentOption(int $installments): bool
    {
        if (!$this->allowsInstallments()) {
            return false;
        }

        return in_array($installments, $this->getAvailableInstallmentOptions());
    }

    /**
     * Scope para obtener reglas por tipo de financiamiento
     */
    public function scopeByFinancingType($query, string $type)
    {
        return $query->where('financing_type', $type);
    }

    /**
     * Scope para obtener manzanas que permiten financiamiento
     */
    public function scopeAllowingInstallments($query)
    {
        return $query->whereIn('financing_type', ['installments', 'mixed']);
    }

    /**
     * Scope para obtener manzanas solo de contado
     */
    public function scopeCashOnly($query)
    {
        return $query->where('financing_type', 'cash_only');
    }
}