<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $primaryKey = 'invoice_id';
    public $timestamps = false;
    protected $fillable = [
        'contract_id',
        'issue_date',
        'amount',
        'currency',
        'document_number',
        'sunat_status'
    ];

    public function contract()
    {
        return $this->belongsTo(\Modules\Sales\Models\Contract::class, 'contract_id');
    }
}
