<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Security\Database\Factories\RoleFactory;
use Spatie\Permission\Models\Role as SpatieRole;

// use Modules\Security\Database\Factories\RoleFactory;

class Role extends SpatieRole
{

    use HasFactory;
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guard_name = 'sanctum';

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }


    // Spatie por defecto usa timestamps; quítalos sólo si NO quieres created_at/updated_at
    public $timestamps = true;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * Relación con usuarios a través de la tabla pivote user_roles
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_roles',
            'role_id',
            'user_id'
        );
    }
}
