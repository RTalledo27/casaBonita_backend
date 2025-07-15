<?php

namespace Modules\Collections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CRM\Models\Client;
use Modules\Security\Models\User;


class CustomerPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'client_id',
        'ar_id',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'notes',
        'processed_by',
        'journal_entry_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2'
    ];

    protected $dates = ['deleted_at'];

    // Métodos de pago disponibles
    const METHOD_CASH = 'CASH';
    const METHOD_TRANSFER = 'TRANSFER';
    const METHOD_CHECK = 'CHECK';
    const METHOD_C_CARD = 'CREDIT CARD';
    const METHOD_D_CARD = 'DEBIT CARD';
    const METHOD_YAPE = 'YAPE';
    const METHOD_PLIN = 'PLIN';
    const METHOD_OTHER = 'OTHER';

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function accountReceivable()
    {
        return $this->belongsTo(AccountReceivable::class, 'ar_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by', 'user_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(\Modules\Accounting\Models\JournalEntry::class, 'journal_entry_id');
    }

    // Scopes
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByProcessor($query, $userId)
    {
        return $query->where('processed_by', $userId);
    }

    // Métodos de negocio
    public static function generatePaymentNumber()
    {
        $lastPayment = self::orderBy('payment_id', 'desc')->first();
        $nextNumber = $lastPayment ? intval(substr($lastPayment->payment_number, 4)) + 1 : 1;
        return 'PAY-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function requiresReference()
    {
        return in_array($this->payment_method, [self::METHOD_TRANSFER, self::METHOD_CHECK]);
    }
}
