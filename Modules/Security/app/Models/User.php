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
use Modules\HumanResources\Models\Employee;
use Modules\Sales\Models\ContractApproval;
use Modules\Security\Models\Role;
use Modules\ServiceDesk\Models\ServiceRequest;

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

    protected $casts = [
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'hire_date' => 'date',
        'birth_date' => 'date',
        'preferences' => 'array',
    ];


    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'dni',
        'email',
        'phone',
        'status',
        'position',
        'department',
        'address',
        'hire_date',
        'birth_date',
        'photo_profile',
        'photo_profile',
        'cv_file',
        'password_hash',
        'must_change_password',
        'password_changed_at',
        'last_login_at',
        'created_by',
        'preferences'
    ];


    // protected static function newFactory(): UserFactory
    // {
    //     // return UserFactory::new();
    // }}


    public function assignedTickets()
    {
        return $this->hasMany(ServiceRequest::class, 'assigned_to', 'user_id');
    }

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

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    //POR IMPLEMENTAR

    public function auditLogs()
    {
        return $this->hasMany(\Modules\Audit\Models\AuditLog::class, 'user_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(\App\Models\UserActivityLog::class, 'user_id', 'user_id');
    }


    //CONTRATOS APROBADOS 
    public function contractApprovals(){
        return $this->hasMany(ContractApproval::class, 'user_id');
    }

    
}
