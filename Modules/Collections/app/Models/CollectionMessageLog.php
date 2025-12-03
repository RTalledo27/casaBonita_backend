<?php

namespace Modules\Collections\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionMessageLog extends Model
{
    protected $table = 'collection_message_logs';
    protected $fillable = [
        'contract_id',
        'schedule_id',
        'client_id',
        'recipient_email',
        'subject',
        'content_html',
        'status',
        'sent_at',
        'delivered_at',
        'meta',
    ];
    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'meta' => 'array',
    ];
}

