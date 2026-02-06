<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $primaryKey = 'item_id';

    // Códigos de unidad SUNAT
    const UNIT_UNIDAD = 'NIU';      // Unidad
    const UNIT_SERVICIO = 'ZZ';    // Servicio
    const UNIT_KILOGRAMO = 'KGM';  // Kilogramo
    const UNIT_METRO = 'MTR';      // Metro
    const UNIT_METRO2 = 'MTK';     // Metro cuadrado

    // Tipos de IGV
    const IGV_GRAVADO = '10';      // Gravado - Operación Onerosa
    const IGV_EXONERADO = '20';    // Exonerado
    const IGV_INAFECTO = '30';     // Inafecto

    protected $fillable = [
        'invoice_id',
        'description',
        'product_code',
        'quantity',
        'unit_code',
        'unit_price',
        'unit_price_with_igv',
        'igv_amount',
        'igv_percentage',
        'igv_type',
        'subtotal',
        'total',
        'order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'unit_price_with_igv' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'igv_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Relación con el comprobante
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Calcular todos los montos del ítem
     */
    public function calculateAmounts(): void
    {
        $this->subtotal = round($this->quantity * $this->unit_price, 2);
        
        if ($this->igv_type === self::IGV_GRAVADO) {
            $this->igv_amount = round($this->subtotal * ($this->igv_percentage / 100), 2);
        } else {
            $this->igv_amount = 0;
        }
        
        $this->total = $this->subtotal + $this->igv_amount;
        $this->unit_price_with_igv = round($this->unit_price * (1 + ($this->igv_percentage / 100)), 2);
    }

    /**
     * Crear ítem desde precio con IGV incluido
     */
    public static function fromPriceWithIgv(
        string $description,
        float $quantity,
        float $priceWithIgv,
        string $unitCode = self::UNIT_UNIDAD,
        string $igvType = self::IGV_GRAVADO
    ): self {
        $item = new self();
        $item->description = $description;
        $item->quantity = $quantity;
        $item->unit_code = $unitCode;
        $item->igv_type = $igvType;
        $item->igv_percentage = 18.00;
        
        // Calcular precio sin IGV
        $item->unit_price = round($priceWithIgv / 1.18, 2);
        $item->unit_price_with_igv = $priceWithIgv;
        
        $item->calculateAmounts();
        
        return $item;
    }

    /**
     * Obtener nombre de unidad
     */
    public function getUnitNameAttribute(): string
    {
        return match($this->unit_code) {
            self::UNIT_UNIDAD => 'Unidad',
            self::UNIT_SERVICIO => 'Servicio',
            self::UNIT_KILOGRAMO => 'Kilogramo',
            self::UNIT_METRO => 'Metro',
            self::UNIT_METRO2 => 'Metro cuadrado',
            default => 'Unidad'
        };
    }
}
