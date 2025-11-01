<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public const UPDATED_AT = null; // Solo tiene created_at

    protected $fillable = [
        'user_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    // Acciones comunes
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_PROFILE_UPDATED = 'profile_updated';
    public const ACTION_PASSWORD_CHANGED = 'password_changed';
    public const ACTION_PREFERENCES_UPDATED = 'preferences_updated';
    public const ACTION_CONTRACT_CREATED = 'contract_created';
    public const ACTION_CONTRACT_UPDATED = 'contract_updated';
    public const ACTION_PAYMENT_REGISTERED = 'payment_registered';
    public const ACTION_LOT_ASSIGNED = 'lot_assigned';
    public const ACTION_COMMISSION_CALCULATED = 'commission_calculated';
    public const ACTION_REPORT_VIEWED = 'report_viewed';
    public const ACTION_REPORT_EXPORTED = 'report_exported';
    public const ACTION_DATA_IMPORTED = 'data_imported';
    public const ACTION_DATA_EXPORTED = 'data_exported';

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Security\Models\User::class, 'user_id', 'user_id');
    }

    /**
     * Registrar una actividad
     */
    public static function log(
        int $userId,
        string $action,
        ?string $details = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata
        ]);
    }

    /**
     * Obtener actividad reciente de un usuario
     */
    public static function getRecentActivity(int $userId, int $limit = 20)
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener descripción amigable de la acción
     */
    public function getActionLabel(): string
    {
        return match($this->action) {
            self::ACTION_LOGIN => 'Inicio de sesión',
            self::ACTION_LOGOUT => 'Cierre de sesión',
            self::ACTION_PROFILE_UPDATED => 'Perfil actualizado',
            self::ACTION_PASSWORD_CHANGED => 'Contraseña cambiada',
            self::ACTION_PREFERENCES_UPDATED => 'Preferencias actualizadas',
            self::ACTION_CONTRACT_CREATED => 'Contrato creado',
            self::ACTION_CONTRACT_UPDATED => 'Contrato actualizado',
            self::ACTION_PAYMENT_REGISTERED => 'Pago registrado',
            self::ACTION_LOT_ASSIGNED => 'Lote asignado',
            self::ACTION_COMMISSION_CALCULATED => 'Comisión calculada',
            self::ACTION_REPORT_VIEWED => 'Reporte visualizado',
            self::ACTION_REPORT_EXPORTED => 'Reporte exportado',
            self::ACTION_DATA_IMPORTED => 'Datos importados',
            self::ACTION_DATA_EXPORTED => 'Datos exportados',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }
}
