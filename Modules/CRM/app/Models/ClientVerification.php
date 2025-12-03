<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class ClientVerification extends Model
{
    protected $table = 'client_verifications';
    protected $fillable = [
        'client_id',
        'type',
        'target_value',
        'code',
        'expires_at',
        'verified_at',
        'status',
        'attempts'
    ];
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime'
    ];
}

