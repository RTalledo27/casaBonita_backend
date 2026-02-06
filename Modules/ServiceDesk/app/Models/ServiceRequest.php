<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Sales\Models\Contract;
use Modules\Security\Models\User;

class ServiceRequest extends Model
{
    protected $primaryKey = 'ticket_id';
    public $incrementing = true;
    public $timestamps    = true; // Enable timestamps for updated_at tracking
    protected $fillable   = [
        'contract_id',
        'opened_by',
        'opened_at',
        'ticket_type',
        'category_id',
        'priority',
        'status',
        'description',
        'sla_due_at',
        'escalated_at',
        'assigned_to',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'opened_at'    => 'datetime',
        'sla_due_at'   => 'datetime',
        'escalated_at' => 'datetime',
        'closed_at'    => 'datetime',
        'assigned_to'  => 'integer',
        'closed_by'    => 'integer',
        'category_id'  => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
    ];


    // RELACIONES
    public function actions()
    {
        return $this->hasMany(ServiceAction::class, 'ticket_id', 'ticket_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'opened_by', 'user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by', 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_id', 'ticket_id');
    }
}
