<?php

namespace Modules\ServiceDesk\Models;

use Illuminate\Database\Eloquent\Model;

class SlaConfig extends Model
{
    protected $fillable = [
        'priority',
        'response_hours',
        'resolution_hours',
        'is_active',
    ];

    protected $casts = [
        'response_hours' => 'integer',
        'resolution_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the SLA due datetime based on creation time
     */
    public function calculateDueAt(\DateTime $createdAt): \DateTime
    {
        $dueAt = clone $createdAt;
        $dueAt->modify("+{$this->resolution_hours} hours");
        return $dueAt;
    }

    /**
     * Get the SLA response due datetime
     */
    public function calculateResponseDueAt(\DateTime $createdAt): \DateTime
    {
        $dueAt = clone $createdAt;
        $dueAt->modify("+{$this->response_hours} hours");
        return $dueAt;
    }

    /**
     * Get SLA config by priority
     */
    public static function getByPriority(string $priority): ?self
    {
        return static::where('priority', $priority)
            ->where('is_active', true)
            ->first();
    }
}
