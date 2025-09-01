<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CRM\Database\Factories\FamilyMemberFactory;

// use Modules\CRM\Database\Factories\FamilyMemberFactory;

class FamilyMember extends Model
{
    use HasFactory;

    protected $primaryKey = 'family_member_id';
    public $timestamps = false;

    protected static function newFactory(): FamilyMemberFactory
    {
        return FamilyMemberFactory::new();
    }

    protected $fillable = [
        'client_id',
        'first_name',
        'last_name',
        'dni',
        'relation',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
