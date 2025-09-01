<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

// use Modules\Security\Database\Factories\UserRoleFactory;

class UserRole extends Pivot
{
    use HasFactory;

    protected $table      = 'user_roles';
    public    $timestamps   = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable    = [
        'user_id',
        'role_id'
    ];

    // protected static function newFactory(): UserRoleFactory
    // {
    //     // return UserRoleFactory::new();
    // }
}
