<?php

namespace Modules\Security\Models;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Model;
use Modules\Security\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\CRM\Models\CrmInteraction;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// use Modules\Security\Database\Factories\UserFactory;

class User extends Authenticatable
 {
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $primaryKey = 'user_id';
    protected $guard_name = 'sanctum';
    public    $timestamps  = true;


    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

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
        return $this->hasMany(\Modules\Audit\Models\AuditLog::class, 'user_id');
    }
}
