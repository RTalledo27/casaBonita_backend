<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Security\Models\User;

// use Modules\Sales\Database\Factories\ContractApprovalFactory;

class ContractApproval extends Model
{
    use HasFactory;

    protected $primaryKey = 'approval_id';
    public $timestamps = true;



    /**
     * The attributes that are mass assignable.
     */

    protected $fillable = [
        'contract_id',
        'user_id',
        'status',
        'approved_at',
        'comments'
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
   

}
