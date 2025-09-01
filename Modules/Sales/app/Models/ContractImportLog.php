<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class ContractImportLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contract_import_logs';
    protected $primaryKey = 'import_log_id';

    protected $fillable = [
        'user_id',
        'file_name',
        'file_size',
        'file_path',
        'status',
        'message',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'error_details',
        'processing_time',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'error_details' => 'array',
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'success_count' => 'integer',
        'error_count' => 'integer',
        'processing_time' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $dates = [
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Estados posibles de importación
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Obtener todos los estados disponibles
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PROCESSING => 'Procesando',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_FAILED => 'Fallido',
            self::STATUS_CANCELLED => 'Cancelado'
        ];
    }

    /**
     * Relación con el usuario que realizó la importación
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para importaciones exitosas
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
                    ->where('error_count', 0);
    }

    /**
     * Scope para importaciones con errores
     */
    public function scopeWithErrors($query)
    {
        return $query->where('error_count', '>', 0);
    }

    /**
     * Scope para importaciones recientes
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Accessor para obtener el estado en español
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? 'Desconocido';
    }

    /**
     * Accessor para obtener el tamaño del archivo formateado
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Accessor para obtener el tiempo de procesamiento formateado
     */
    public function getFormattedProcessingTimeAttribute(): string
    {
        if (!$this->processing_time) {
            return 'N/A';
        }

        $seconds = $this->processing_time;
        
        if ($seconds < 60) {
            return $seconds . ' segundos';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $minutes . ' min ' . $remainingSeconds . ' seg';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . ' h ' . $remainingMinutes . ' min';
    }

    /**
     * Accessor para obtener el porcentaje de éxito
     */
    public function getSuccessRateAttribute(): float
    {
        if (!$this->processed_rows || $this->processed_rows == 0) {
            return 0;
        }

        return round(($this->success_count / $this->processed_rows) * 100, 2);
    }

    /**
     * Accessor para verificar si la importación fue exitosa
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->error_count == 0;
    }

    /**
     * Accessor para verificar si la importación está en progreso
     */
    public function getIsProcessingAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Marcar importación como iniciada
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now()
        ]);
    }

    /**
     * Marcar importación como completada
     */
    public function markAsCompleted(array $results): void
    {
        $processingTime = $this->started_at ? 
            now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'success_count' => $results['processed'] ?? 0,
            'error_count' => $results['errors'] ?? 0,
            'error_details' => $results['error_details'] ?? [],
            'processing_time' => $processingTime,
            'message' => $results['message'] ?? 'Importación completada'
        ]);
    }

    /**
     * Marcar importación como fallida
     */
    public function markAsFailed(string $message, array $errorDetails = []): void
    {
        $processingTime = $this->started_at ? 
            now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'processing_time' => $processingTime,
            'message' => $message,
            'error_details' => $errorDetails
        ]);
    }

    /**
     * Obtener estadísticas de importaciones
     */
    public static function getImportStats(int $days = 30): array
    {
        $query = self::recent($days);
        
        return [
            'total_imports' => $query->count(),
            'successful_imports' => $query->where('status', self::STATUS_COMPLETED)->count(),
            'failed_imports' => $query->where('status', self::STATUS_FAILED)->count(),
            'processing_imports' => $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])->count(),
            'total_processed_rows' => $query->sum('processed_rows'),
            'total_success_count' => $query->sum('success_count'),
            'total_error_count' => $query->sum('error_count'),
            'average_processing_time' => $query->whereNotNull('processing_time')->avg('processing_time')
        ];
    }
}