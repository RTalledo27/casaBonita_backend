<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'system_activity_logs';

    protected $fillable = [
        'user_id',
        'actor_identifier',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTION_HTTP_REQUEST = 'http_request';
    public const ACTION_LOGIN_FAILED = 'login_failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Security\Models\User::class, 'user_id', 'user_id');
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_HTTP_REQUEST => 'Request API',
            self::ACTION_LOGIN_FAILED => 'Intento de login fallido',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}

