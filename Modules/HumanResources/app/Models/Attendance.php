<?php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\HumanResources\Database\Factories\AttendanceFactory;

class Attendance extends Model
{
    use HasFactory;

    protected $primaryKey = 'attendance_id';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'break_start_time',
        'break_end_time',
        'total_hours',
        'regular_hours',
        'overtime_hours',
        'status',
        'notes',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'break_start_time' => 'datetime',
        'break_end_time' => 'datetime',
        'total_hours' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'approved_at' => 'datetime'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('attendance_date', $date);
    }

    public function scopeByMonth($query, $month, $year)
    {
        return $query->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'presente');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'ausente');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'tardanza');
    }

    public function calculateHours()
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return;
        }

        $checkIn = $this->check_in_time;
        $checkOut = $this->check_out_time;

        // Calcular tiempo de descanso
        $breakTime = 0;
        if ($this->break_start_time && $this->break_end_time) {
            $breakTime = $this->break_end_time->diffInMinutes($this->break_start_time) / 60;
        }

        $totalHours = $checkOut->diffInMinutes($checkIn) / 60 - $breakTime;
        $regularHours = min($totalHours, 8); // 8 horas regulares
        $overtimeHours = max(0, $totalHours - 8);

        $this->update([
            'total_hours' => $totalHours,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours
        ]);
    }
}
