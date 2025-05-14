<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAction extends Model
{
    protected $primaryKey = 'action_id';
    public $timestamps    = false;
    protected $fillable   = [
        'ticket_id',
        'user_id',
        'performed_at',
        'notes',
        'next_action_date'
    ];

    public function request()
    {
        return $this->belongsTo(ServiceRequest::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(\Modules\Security\Models\User::class, 'user_id');
    }
}
