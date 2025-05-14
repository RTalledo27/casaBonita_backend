<?php

namespace Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalSignature extends Model
{
    
    protected $primaryKey = 'signature_id';
    public $timestamps = false;
    protected $fillable = ['entity', 'entity_id', 'hash', 'certificate', 'signed_at'];
}
