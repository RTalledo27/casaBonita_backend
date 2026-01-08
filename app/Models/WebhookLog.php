<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'message_id',
        'event_type',
        'correlation_id',
        'source_id',
        'payload',
        'status',
        'received_at',
        'processed_at',
        'error_message',
        'headers',
        'retry_count'
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'retry_count' => 'integer'
    ];

    // Scopes para filtrar por estado
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Scope para eventos recientes
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('received_at', '>=', now()->subHours($hours));
    }
}
