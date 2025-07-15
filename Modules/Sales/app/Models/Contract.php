<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Accounting\Models\Invoice;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;

// use Modules\Sales\Database\Factories\ContractFactory;

class Contract extends Model
{
    use HasFactory;


    protected $primaryKey = 'contract_id';
    public    $timestamps  = false;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reservation_id',
        'advisor_id', // Asesor inmobiliario que gestiona el contrato
        'previous_contract_id',
        'contract_number',
        'sign_date',
        'total_price',
        'currency',
        'status',
        'transferred_amount_from_previous_contract'
    ];


    // --- RELACIONES ---
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function advisor()
    {
        return $this->belongsTo(Employee::class, 'advisor_id', 'employee_id');
    }

    public function previousContract()
    {
        return $this->belongsTo(Contract::class, 'previous_contract_id');
    }

    public function paymentSchedules()
    {
        return $this->hasMany(PaymentSchedule::class, 'contract_id');
    }

    public function schedules() // Alias para paymentSchedules, si se usa en algún lugar
    {
        return $this->hasMany(PaymentSchedule::class, 'contract_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'contract_id');
    }

    public function approvals()
    {
        return $this->hasMany(ContractApproval::class, 'contract_id');
    }

    public function commission()
    {
        return $this->hasOne(Commission::class, 'contract_id', 'contract_id');
    }
}
