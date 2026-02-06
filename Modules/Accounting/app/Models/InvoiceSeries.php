<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InvoiceSeries extends Model
{
    protected $table = 'invoice_series';
    protected $primaryKey = 'series_id';

    protected $fillable = [
        'document_type',
        'series',
        'current_correlative',
        'is_active',
        'environment',
        'description',
    ];

    protected $casts = [
        'current_correlative' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Obtiene el siguiente correlativo para una serie
     * Usa transacción para evitar duplicados
     */
    public static function getNextCorrelative(string $documentType, string $environment = 'beta'): array
    {
        return DB::transaction(function () use ($documentType, $environment) {
            // Determinar prefijo de serie según tipo de documento
            $seriesPrefix = match($documentType) {
                Invoice::TYPE_FACTURA => 'F',
                Invoice::TYPE_BOLETA => 'B',
                Invoice::TYPE_NOTA_CREDITO => $environment === 'beta' ? 'BC' : 'FC', // Asume boleta, ajustar según uso
                Invoice::TYPE_NOTA_DEBITO => $environment === 'beta' ? 'BD' : 'FD',
                default => 'B'
            };

            // Buscar serie activa
            $series = self::where('document_type', $documentType)
                         ->where('environment', $environment)
                         ->where('is_active', true)
                         ->where('series', 'like', $seriesPrefix . '%')
                         ->lockForUpdate()
                         ->first();

            if (!$series) {
                throw new \Exception("No hay serie activa para el tipo de documento {$documentType} en ambiente {$environment}");
            }

            // Incrementar correlativo
            $series->current_correlative++;
            $series->save();

            return [
                'series' => $series->series,
                'correlative' => $series->current_correlative,
            ];
        });
    }

    /**
     * Obtiene la serie para nota de crédito/débito basada en el documento original
     */
    public static function getNextCorrelativeForNote(
        string $noteType, 
        Invoice $originalInvoice, 
        string $environment = 'beta'
    ): array {
        return DB::transaction(function () use ($noteType, $originalInvoice, $environment) {
            // Prefijo según tipo de documento original
            $prefix = $originalInvoice->document_type === Invoice::TYPE_BOLETA ? 'B' : 'F';
            $notePrefix = $noteType === Invoice::TYPE_NOTA_CREDITO ? 'C' : 'D';
            $seriesSearch = $prefix . $notePrefix;

            $series = self::where('document_type', $noteType)
                         ->where('environment', $environment)
                         ->where('is_active', true)
                         ->where('series', 'like', $seriesSearch . '%')
                         ->lockForUpdate()
                         ->first();

            if (!$series) {
                throw new \Exception("No hay serie activa para notas tipo {$noteType}");
            }

            $series->current_correlative++;
            $series->save();

            return [
                'series' => $series->series,
                'correlative' => $series->current_correlative,
            ];
        });
    }

    /**
     * Scope para series activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope por ambiente
     */
    public function scopeEnvironment($query, string $env)
    {
        return $query->where('environment', $env);
    }
}
