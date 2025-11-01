<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserSession extends Model
{
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'last_activity_at',
        'session_type',
        'total_duration',
        'ip_address',
        'user_agent',
        'status'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'total_duration' => 'integer',
    ];

    // Estados de sesión
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';

    // Tipos de sesión
    public const TYPE_AUTO = 'auto';
    public const TYPE_MANUAL = 'manual';

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Security\Models\User::class, 'user_id', 'user_id');
    }

    /**
     * Iniciar una nueva sesión
     */
    public static function startSession(
        int $userId,
        string $type = self::TYPE_AUTO,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        // Finalizar sesiones activas anteriores
        self::where('user_id', $userId)
            ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAUSED])
            ->update([
                'status' => self::STATUS_ENDED,
                'ended_at' => now(),
            ]);

        // Calcular duración de sesiones finalizadas
        self::where('user_id', $userId)
            ->whereNull('total_duration')
            ->whereNotNull('ended_at')
            ->get()
            ->each(function ($session) {
                $session->calculateDuration();
            });

        // Crear nueva sesión
        return self::create([
            'user_id' => $userId,
            'started_at' => now(),
            'last_activity_at' => now(),
            'session_type' => $type,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finalizar la sesión
     */
    public function endSession(): void
    {
        $this->update([
            'ended_at' => now(),
            'status' => self::STATUS_ENDED,
        ]);

        $this->calculateDuration();
    }

    /**
     * Pausar la sesión
     */
    public function pauseSession(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
        ]);

        $this->calculateDuration();
    }

    /**
     * Reanudar la sesión
     */
    public function resumeSession(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Actualizar última actividad
     */
    public function updateActivity(): void
    {
        $this->update([
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Calcular duración total en segundos
     */
    public function calculateDuration(): void
    {
        $end = $this->ended_at ?? now();
        $duration = $end->diffInSeconds($this->started_at);

        $this->update([
            'total_duration' => $duration,
        ]);
    }

    /**
     * Obtener duración formateada
     */
    public function getFormattedDuration(): string
    {
        $seconds = $this->total_duration ?? $this->getCurrentDuration();
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    /**
     * Obtener duración actual (si está activa)
     */
    public function getCurrentDuration(): int
    {
        if ($this->status === self::STATUS_ENDED && $this->total_duration) {
            return $this->total_duration;
        }

        $end = $this->ended_at ?? now();
        return $end->diffInSeconds($this->started_at);
    }

    /**
     * Verificar si la sesión está inactiva
     */
    public function isInactive(int $minutes = 15): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return true;
        }

        return $this->last_activity_at->addMinutes($minutes)->isPast();
    }

    /**
     * Obtener sesión activa de un usuario
     */
    public static function getActiveSession(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('started_at', 'desc')
            ->first();
    }

    /**
     * Obtener estadísticas de sesiones de un usuario
     */
    public static function getUserStats(int $userId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = self::where('user_id', $userId)
            ->where('status', self::STATUS_ENDED)
            ->whereNotNull('total_duration');

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $sessions = $query->get();

        $totalSeconds = $sessions->sum('total_duration');
        $totalHours = round($totalSeconds / 3600, 2);
        $averageSeconds = $sessions->count() > 0 ? $totalSeconds / $sessions->count() : 0;

        return [
            'total_sessions' => $sessions->count(),
            'total_seconds' => $totalSeconds,
            'total_hours' => $totalHours,
            'average_session_duration' => round($averageSeconds / 60, 2), // en minutos
            'formatted_total' => self::formatSeconds($totalSeconds),
        ];
    }

    /**
     * Formatear segundos a texto legible
     */
    public static function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        } else {
            return sprintf('%dm', $minutes);
        }
    }

    /**
     * Auto-pausar sesiones inactivas
     */
    public static function autoPauseInactiveSessions(int $inactivityMinutes = 15): int
    {
        $threshold = now()->subMinutes($inactivityMinutes);

        return self::where('status', self::STATUS_ACTIVE)
            ->where('last_activity_at', '<', $threshold)
            ->update([
                'status' => self::STATUS_PAUSED,
            ]);
    }
}
