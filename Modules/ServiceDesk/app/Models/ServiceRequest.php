<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $primaryKey = 'ticket_id';
    public $timestamps    = false;
    protected $fillable   = [
        'contract_id',
        'opened_at',
        'ticket_type',
        'priority',
        'status',
        'description'
    ];

    public function contract()
    {
        return $this->belongsTo(\Modules\Sales\Models\Contract::class, 'contract_id');
    }

    public function actions()
    {
        return $this->hasMany(ServiceAction::class, 'ticket_id');
    }
}
