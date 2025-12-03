<?php

namespace Modules\Collections\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Followup extends Model
{
    use HasFactory;

    protected $table = 'collection_followups';
    protected $primaryKey = 'followup_id';

    protected $fillable = [
        'client_id',
        'client_name',
        'dni',
        'phone1',
        'phone2',
        'email',
        'address',
        'district',
        'province',
        'department',
        'assigned_employee_id',
        'contract_id',
        'lot_id',
        'lot',
        'lot_area_m2',
        'lot_status',
        'sale_code',
        'contract_status',
        'advisor_id',
        'advisor_name',
        'due_date',
        'sale_price',
        'amount_paid',
        'amount_due',
        'monthly_quota',
        'paid_installments',
        'pending_installments',
        'total_installments',
        'overdue_installments',
        'pending_amount',
        'contact_date',
        'action_taken',
        'management_result',
        'management_notes',
        'home_visit_date',
        'home_visit_reason',
        'home_visit_result',
        'home_visit_notes',
        'management_status',
        'owner',
        'general_notes',
        'general_reason',
        // nuevos campos de gestión
        'segment',
        'tramo',
        'channel',
        'commitment_date',
        'commitment_amount',
    ];
}
