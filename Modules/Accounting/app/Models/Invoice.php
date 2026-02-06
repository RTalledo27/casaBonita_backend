<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Models\Contract;

class Invoice extends Model
{
    protected $primaryKey = 'invoice_id';
    
    // Tipos de documento SUNAT
    const TYPE_FACTURA = '01';
    const TYPE_BOLETA = '03';
    const TYPE_NOTA_CREDITO = '07';
    const TYPE_NOTA_DEBITO = '08';
    
    // Estados SUNAT
    const STATUS_PENDIENTE = 'pendiente';
    const STATUS_ENVIADO = 'enviado';
    const STATUS_ACEPTADO = 'aceptado';
    const STATUS_OBSERVADO = 'observado';
    const STATUS_RECHAZADO = 'rechazado';
    const STATUS_ANULADO = 'anulado';

    protected $fillable = [
        'contract_id',
        'document_type',
        'series',
        'correlative',
        'client_document_type',
        'client_document_number',
        'client_name',
        'client_address',
        'issue_date',
        'amount',
        'subtotal',
        'igv',
        'total',
        'currency',
        'document_number',
        'sunat_status',
        'xml_content',
        'xml_hash',
        'cdr_content',
        'cdr_code',
        'cdr_description',
        'pdf_path',
        'qr_code',
        'sent_at',
        'related_invoice_id',
        'void_reason',
    ];

    protected $hidden = [
        'xml_content',
        'cdr_content',
        'pdf_path',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'amount' => 'decimal:2',
        'correlative' => 'integer',
    ];

    /**
     * Relación con el contrato
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relación con el pago
     */
    public function payment()
    {
        return $this->belongsTo(\Modules\Sales\Models\Payment::class, 'payment_id', 'payment_id');
    }

    /**
     * Ítems del comprobante
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Documento relacionado (para NC/ND)
     */
    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id', 'invoice_id');
    }

    /**
     * Notas que afectan a este documento
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Invoice::class, 'related_invoice_id', 'invoice_id');
    }

    /**
     * Obtiene el número completo del documento (Serie-Correlativo)
     */
    public function getFullNumberAttribute(): string
    {
        return $this->series . '-' . str_pad($this->correlative, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene el nombre del tipo de documento
     */
    public function getDocumentTypeNameAttribute(): string
    {
        return match($this->document_type) {
            self::TYPE_FACTURA => 'Factura Electrónica',
            self::TYPE_BOLETA => 'Boleta de Venta Electrónica',
            self::TYPE_NOTA_CREDITO => 'Nota de Crédito Electrónica',
            self::TYPE_NOTA_DEBITO => 'Nota de Débito Electrónica',
            default => 'Documento Electrónico'
        };
    }

    /**
     * Verifica si el documento fue aceptado por SUNAT
     */
    public function isAccepted(): bool
    {
        return $this->sunat_status === self::STATUS_ACEPTADO;
    }

    /**
     * Verifica si el documento puede ser anulado
     */
    public function canBeVoided(): bool
    {
        if ($this->sunat_status === self::STATUS_ANULADO) {
            return false;
        }
        
        // Solo se pueden anular documentos del mismo día
        return $this->issue_date->isToday() && $this->isAccepted();
    }

    /**
     * Scope para filtrar por tipo de documento
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope para boletas
     */
    public function scopeBoletas($query)
    {
        return $query->where('document_type', self::TYPE_BOLETA);
    }

    /**
     * Scope para facturas
     */
    public function scopeFacturas($query)
    {
        return $query->where('document_type', self::TYPE_FACTURA);
    }

    /**
     * Scope para pendientes de envío
     */
    public function scopePending($query)
    {
        return $query->where('sunat_status', self::STATUS_PENDIENTE);
    }

    /**
     * Scope para aceptados
     */
    public function scopeAccepted($query)
    {
        return $query->where('sunat_status', self::STATUS_ACEPTADO);
    }

    /**
     * Calcular IGV (18%)
     */
    public static function calculateIgv(float $subtotal): float
    {
        return round($subtotal * 0.18, 2);
    }

    /**
     * Calcular subtotal desde total (incluye IGV)
     */
    public static function calculateSubtotalFromTotal(float $total): float
    {
        return round($total / 1.18, 2);
    }
}
