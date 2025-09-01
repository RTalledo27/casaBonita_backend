<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Collections\Models\CustomerPayment;
use Modules\Sales\Models\Contract;
use App\Models\User;

class PaymentEvent extends Model
{
    use HasUuids;

    protected $table = 'payment_events';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_type',
        'payment_id',
        'contract_id',
        'installment_type',
        'event_data',
        'processed',
        'processed_at',
        'retry_count',
        'last_retry_at',
        'error_message',
        'triggered_by',
        'created_at'
    ];

    protected $casts = [
        'event_data' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'retry_count' => 'integer'
    ];

    protected $dates = [
        'processed_at',
        'last_retry_at',
        'created_at'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    /**
     * Relación con el pago del cliente.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'payment_id', 'payment_id');
    }

    /**
     * Relación con el contrato.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    /**
     * Relación con el usuario que disparó el evento.
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by', 'user_id');
    }

    /**
     * Scope para eventos no procesados.
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope para eventos procesados.
     */
    public function scopeProcessed($query)
    {
        return $query->where('processed', true);
    }

    /**
     * Scope para eventos con errores.
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Scope para eventos por tipo.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope para eventos de un contrato específico.
     */
    public function scopeForContract($query, int $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope para eventos de cuotas específicas.
     */
    public function scopeForInstallment($query, string $installmentType)
    {
        return $query->where('installment_type', $installmentType);
    }

    /**
     * Scope para eventos que necesitan reintento.
     */
    public function scopeNeedsRetry($query, int $maxRetries = 3)
    {
        return $query->where('processed', false)
                    ->where('retry_count', '<', $maxRetries)
                    ->whereNotNull('error_message');
    }

    /**
     * Verifica si el evento puede ser reintentado.
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return !$this->processed && 
               $this->retry_count < $maxRetries && 
               !empty($this->error_message);
    }

    /**
     * Verifica si el evento ha fallado permanentemente.
     */
    public function hasFailed(int $maxRetries = 3): bool
    {
        return !$this->processed && 
               $this->retry_count >= $maxRetries && 
               !empty($this->error_message);
    }

    /**
     * Marca el evento como procesado.
     */
    public function markAsProcessed(): bool
    {
        return $this->update([
            'processed' => true,
            'processed_at' => now(),
            'error_message' => null
        ]);
    }

    /**
     * Registra un error en el evento.
     */
    public function recordError(string $errorMessage): bool
    {
        return $this->update([
            'error_message' => $errorMessage,
            'last_retry_at' => now(),
            'retry_count' => $this->retry_count + 1
        ]);
    }

    /**
     * Obtiene un resumen del evento para logging.
     */
    public function getSummary(): string
    {
        return "PaymentEvent {$this->id}: {$this->event_type} for payment {$this->payment_id} " .
               "(contract: {$this->contract_id}, installment: {$this->installment_type})";
    }

    /**
     * Obtiene estadísticas de eventos.
     */
    public static function getStats(array $filters = []): array
    {
        $query = static::query();
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (isset($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        return [
            'total' => $query->count(),
            'processed' => $query->where('processed', true)->count(),
            'pending' => $query->where('processed', false)->whereNull('error_message')->count(),
            'failed' => $query->where('processed', false)->whereNotNull('error_message')->count(),
            'by_type' => $query->groupBy('event_type')
                              ->selectRaw('event_type, count(*) as count')
                              ->pluck('count', 'event_type')
                              ->toArray()
        ];
    }
}