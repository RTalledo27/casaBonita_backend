<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Accounting\Models\JournalEntry;

// use Modules\Sales\Database\Factories\PaymentFactory;

class Payment extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_id';
    public    $timestamps  = false;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'schedule_id',
        'journal_entry_id',
        'payment_date',
        'amount',
        'method',
        'reference'
    ];

    // protected static function newFactory(): PaymentFactory
    // {
    //     // return PaymentFactory::new();
    // }

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class,);
    }
}
