<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $primaryKey = 'journal_entry_id';
    public $timestamps = false;
    protected $fillable = ['date', 'description', 'posted_by', 'status'];

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function poster()
    {
        return $this->belongsTo(\Modules\Security\Models\User::class, 'posted_by');
    }
}
