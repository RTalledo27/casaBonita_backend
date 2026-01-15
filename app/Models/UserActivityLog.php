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
    public const ACTION_SECURITY_USER_CREATED = 'security_user_created';
    public const ACTION_SECURITY_USER_UPDATED = 'security_user_updated';
    public const ACTION_SECURITY_USER_DELETED = 'security_user_deleted';
    public const ACTION_SECURITY_USER_ROLES_UPDATED = 'security_user_roles_updated';
    public const ACTION_SECURITY_USER_STATUS_UPDATED = 'security_user_status_updated';
    public const ACTION_SECURITY_USER_PASSWORD_RESET = 'security_user_password_reset';
    public const ACTION_SECURITY_ROLE_CREATED = 'security_role_created';
    public const ACTION_SECURITY_ROLE_UPDATED = 'security_role_updated';
    public const ACTION_SECURITY_ROLE_DELETED = 'security_role_deleted';
    public const ACTION_SECURITY_ROLE_PERMISSIONS_UPDATED = 'security_role_permissions_updated';
    public const ACTION_SECURITY_PERMISSION_CREATED = 'security_permission_created';
    public const ACTION_SECURITY_PERMISSION_UPDATED = 'security_permission_updated';
    public const ACTION_SECURITY_PERMISSION_DELETED = 'security_permission_deleted';

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
            self::ACTION_SECURITY_USER_CREATED => 'Usuario creado',
            self::ACTION_SECURITY_USER_UPDATED => 'Usuario actualizado',
            self::ACTION_SECURITY_USER_DELETED => 'Usuario eliminado',
            self::ACTION_SECURITY_USER_ROLES_UPDATED => 'Roles de usuario actualizados',
            self::ACTION_SECURITY_USER_STATUS_UPDATED => 'Estado de usuario actualizado',
            self::ACTION_SECURITY_USER_PASSWORD_RESET => 'Contraseña de usuario restablecida',
            self::ACTION_SECURITY_ROLE_CREATED => 'Rol creado',
            self::ACTION_SECURITY_ROLE_UPDATED => 'Rol actualizado',
            self::ACTION_SECURITY_ROLE_DELETED => 'Rol eliminado',
            self::ACTION_SECURITY_ROLE_PERMISSIONS_UPDATED => 'Permisos de rol actualizados',
            self::ACTION_SECURITY_PERMISSION_CREATED => 'Permiso creado',
            self::ACTION_SECURITY_PERMISSION_UPDATED => 'Permiso actualizado',
            self::ACTION_SECURITY_PERMISSION_DELETED => 'Permiso eliminado',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }
}
