<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $primaryKey = 'bank_account_id';
    public $timestamps = false;
    protected $fillable = ['bank_name', 'currency', 'account_number'];

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }
}
