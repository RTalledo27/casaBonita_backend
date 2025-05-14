<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Security\Database\Factories\RoleFactory;

class Role extends Model
{
    use HasFactory;
    protected $primaryKey = 'role_id';
    public    $timestamps  = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable    = ['name', 'description'];

    // protected static function newFactory(): RoleFactory
    // {
    //     // return RoleFactory::new();
    // }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_roles',
            'role_id',
            'user_id'
        );
    }
}
