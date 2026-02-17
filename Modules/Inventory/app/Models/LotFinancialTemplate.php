<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotFinancialTemplate extends Model
{
    protected $fillable = [
        'lot_id',
        'precio_lista',
        'descuento', 
        'precio_venta',
        'precio_contado',
        'cuota_balon',
        'bono_bpp',
        'bono_techo_propio',
        'precio_total_real',
        'cuota_inicial',
        'ci_fraccionamiento',
        'installments_24',
        'installments_40',
        'installments_44',
        'installments_55'
    ];

    protected $casts = [
        'precio_lista' => 'decimal:2',
        'descuento' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio_contado' => 'decimal:2',
        'cuota_balon' => 'decimal:2',
        'bono_bpp' => 'decimal:2',
        'bono_techo_propio' => 'decimal:2',
        'precio_total_real' => 'decimal:2',
        'cuota_inicial' => 'decimal:2',
        'ci_fraccionamiento' => 'decimal:2',
        'installments_24' => 'decimal:2',
        'installments_40' => 'decimal:2',
        'installments_44' => 'decimal:2',
        'installments_55' => 'decimal:2'
    ];

    /**
     * Relación con el lote
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lot_id', 'lot_id');
    }

    /**
     * Obtiene el monto de cuota para un número específico de meses
     */
    public function getInstallmentAmount(int $months): float
    {
        switch ($months) {
            case 24: return (float) $this->installments_24;
            case 40: return (float) $this->installments_40;
            case 44: return (float) $this->installments_44;
            case 55: return (float) $this->installments_55;
            default: return 0;
        }
    }

    /**
     * Obtiene todas las opciones de financiamiento disponibles para este lote
     */
    public function getAvailableInstallmentOptions(): array
    {
        $options = [];
        
        if ($this->installments_24 > 0) {
            $options[24] = $this->installments_24;
        }
        if ($this->installments_40 > 0) {
            $options[40] = $this->installments_40;
        }
        if ($this->installments_44 > 0) {
            $options[44] = $this->installments_44;
        }
        if ($this->installments_55 > 0) {
            $options[55] = $this->installments_55;
        }
        
        return $options;
    }

    /**
     * Verifica si el lote tiene precio de contado disponible
     */
    public function hasCashPrice(): bool
    {
        return $this->precio_contado > 0;
    }

    /**
     * Verifica si el lote tiene opciones de financiamiento
     */
    public function hasInstallmentOptions(): bool
    {
        return !empty($this->getAvailableInstallmentOptions());
    }

    /**
     * Calcula el total a financiar (precio venta - cuota inicial)
     */
    public function getFinancingAmount(): float
    {
        return (float) ($this->precio_venta - $this->cuota_inicial);
    }

    /**
     * Calcula el porcentaje de descuento
     */
    public function getDiscountPercentage(): float
    {
        if ($this->precio_lista <= 0) {
            return 0;
        }
        
        return (float) (($this->descuento / $this->precio_lista) * 100);
    }

    /**
     * Obtiene el precio efectivo según el tipo de pago
     */
    public function getEffectivePrice(string $paymentType = 'installments'): float
    {
        if ($paymentType === 'cash' && $this->hasCashPrice()) {
            return (float) $this->precio_contado;
        }
        
        return (float) $this->precio_venta;
    }

    /**
     * Calcula el total a pagar para un plan de cuotas específico
     */
    public function getTotalPaymentForInstallments(int $months): float
    {
        $monthlyPayment = $this->getInstallmentAmount($months);
        
        if ($monthlyPayment <= 0) {
            return 0;
        }
        
        $totalFromInstallments = $monthlyPayment * $months;
        $downPayment = (float) $this->cuota_inicial;
        $balloonPayment = (float) $this->cuota_balon;
        
        return $totalFromInstallments + $downPayment + $balloonPayment;
    }

    /**
     * Scope para filtrar por lotes con precio de contado
     */
    public function scopeWithCashPrice($query)
    {
        return $query->where('precio_contado', '>', 0);
    }

    /**
     * Scope para filtrar por lotes con opciones de financiamiento
     */
    public function scopeWithInstallments($query)
    {
        return $query->where(function($q) {
            $q->where('installments_24', '>', 0)
              ->orWhere('installments_40', '>', 0)
              ->orWhere('installments_44', '>', 0)
              ->orWhere('installments_55', '>', 0);
        });
    }

    /**
     * Scope para filtrar por rango de precios
     */
    public function scopePriceRange($query, float $minPrice, float $maxPrice)
    {
        return $query->whereBetween('precio_venta', [$minPrice, $maxPrice]);
    }

    /**
     * Obtiene un resumen de las opciones de pago disponibles
     */
    public function getPaymentOptionsSummary(): array
    {
        $summary = [
            'cash_available' => $this->hasCashPrice(),
            'installments_available' => $this->hasInstallmentOptions(),
            'cash_price' => $this->precio_contado,
            'installment_options' => $this->getAvailableInstallmentOptions(),
            'down_payment' => $this->cuota_inicial,
            'balloon_payment' => $this->cuota_balon,
            'bpp_bonus' => $this->bono_bpp,
            'bono_techo_propio' => $this->bono_techo_propio,
            'precio_total_real' => $this->precio_total_real,
        ];
        
        return $summary;
    }

    /**
     * Calcula el precio total real (precio venta + bono techo propio)
     */
    public function calculatePrecioTotalReal(): float
    {
        return (float) $this->precio_venta + (float) $this->bono_techo_propio;
    }

    /**
     * Calcula el porcentaje cobrado respecto al precio total real
     */
    public function getCollectionPercentage(float $totalCollected): float
    {
        $totalReal = $this->calculatePrecioTotalReal();
        if ($totalReal <= 0) return 0;
        return round(($totalCollected / $totalReal) * 100, 2);
    }

    /**
     * Valor por defecto del bono Techo Propio
     */
    public const BONO_TECHO_PROPIO_DEFAULT = 52250.00;
}