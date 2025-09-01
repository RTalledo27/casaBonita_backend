<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Security\Models\User;

class ServiceAction extends Model
{
    protected $primaryKey = 'action_id';
    public $timestamps    = true;
    protected $fillable   = [
        'ticket_id',
        'user_id',
        'action_type',
        'performed_at',
        'notes',
        'next_action_date'

    ];

    protected $casts = [
        'performed_at',
        'next_action_date',
        'created_at',
        'updated_at',
        'deleted_at',
        'performed_at' => 'datetime',
        'next_action_date' => 'datetime',

    ];

    // RELACIONES
    public function ticket()
    {
        return $this->belongsTo(ServiceRequest::class, 'ticket_id', 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
