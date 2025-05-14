<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CRM\Models\CrmInteraction;

// use Modules\Security\Database\Factories\UserFactory;

class User extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public    $timestamps  = true;


    /**
     * The attributes that are mass assignable.
     */

    protected $hidden     = ['password_hash'];


    protected $fillable = [
        'username',
        'password_hash',
        'email',
        'status',
        'photo_profile'
    ];

    // protected static function newFactory(): UserFactory
    // {
    //     // return UserFactory::new();
    // }}

    //ROLES:
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'user_roles',
            'user_id',
            'role_id'
        );
    }

    public function interactions()
    {
        return $this->hasMany(CrmInteraction::class, 'user_id');
    }

    //POR IMPLEMENTAR

    public function auditLogs()
    {
        //return $this->hasMany(\Modules\Audit\Models\AuditLog::class, 'user_id');
    }
}
