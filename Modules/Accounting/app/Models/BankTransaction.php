<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $primaryKey = 'txn_id';
    public $timestamps = false;
    protected $fillable = [
        'bank_account_id',
        'journal_entry_id',
        'date',
        'amount',
        'currency',
        'reference'
    ];

    public function account()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
