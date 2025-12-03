<?php

namespace Modules\Collections\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FollowupLog extends Model
{
    use HasFactory;

    protected $table = 'collection_followup_logs';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'followup_id',
        'client_id',
        'employee_id',
        'channel',
        'result',
        'notes',
        'logged_at',
    ];
}

