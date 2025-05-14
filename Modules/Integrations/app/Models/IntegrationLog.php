<?php

namespace Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    protected $primaryKey = 'log_id';
    public $timestamps = false;
    protected $fillable = ['service', 'entity', 'entity_id', 'status', 'message', 'logged_at'];
}
