<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $primaryKey = 'line_id';
    public $timestamps = false;
    protected $fillable = ['journal_entry_id', 'account_id', 'lot_id', 'debit', 'credit'];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function lot()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Lot::class, 'lot_id');
    }
}
