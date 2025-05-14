<?php

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table      = 'audit_log';
    protected $primaryKey = 'log_id';
    public $timestamps    = false;
    protected $fillable   = [
        'user_id',
        'action',
        'entity',
        'entity_id',
        'timestamp',
        'changes'
    ];

    public function user()
    {
        return $this->belongsTo(\Modules\Security\Models\User::class, 'user_id');
    }
}
