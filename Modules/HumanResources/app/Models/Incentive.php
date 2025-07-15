<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\HumanResources\Database\Factories\IncentiveFactory;

class Incentive extends Model
{
    use HasFactory;

    protected $primaryKey = 'incentive_id';

    protected $fillable = [
        'employee_id',
        'incentive_name',
        'description',
        'amount',
        'target_description',
        'deadline',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'completed_at',
        'payment_date',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deadline' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'payment_date' => 'date'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'activo');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completado');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'activo');
    }

    public function scopeExpired($query)
    {
        return $query->where('deadline', '<', now())
            ->where('status', '!=', 'completado');
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completado',
            'completed_at' => now()
        ]);
    }

    public function markAsPaid()
    {
        $this->update([
            'status' => 'pagado',
            'payment_date' => now()
        ]);
    }
}
