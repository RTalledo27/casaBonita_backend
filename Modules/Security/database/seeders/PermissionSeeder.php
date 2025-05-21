<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{


    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $permissions = [
            //MODULE SECURITY - PERMISSIONS
            'security.access', //Permiso para acceder a la sección de seguridad
            'security.permissions.view',
            'security.permissions.store',
            'security.permissions.update',
            'security.permissions.destroy',
            //MODULE SECURITY - ROLES
            'security.roles.view',
            'security.roles.store',
            'security.roles.update',
            'security.roles.destroy',
            //MODULE SECURITY - USERS
            'security.users.index',
            'security.users.store',
            'security.users.update',
            'security.users.destroy',
            'security.users.change-password',
            'security.users.toggle-status',
            //MODULE SECURITY - AUTH

            //MODULE CRM - ADDRESS
            'crm.addresses.view',
            'crm.addresses.store',
            'crm.addresses.update',
            'crm.addresses.destroy',
            //MODULE CRM - CLIENTS
            'crm.clients.view',
            'crm.clients.store',
            'crm.clients.update',
            'crm.clients.delete',

            'crm.clients.spouses.view',
            'crm.clients.spouses.create',
            'crm.clients.spouses.delete',
            'crm.clients.export',
            //MODULE CRM - INTERACTIONS
            'crm.access', //Permiso para acceder a la sección de CRM
            'crm.interactions.view',
            'crm.interactions.create',
            'crm.interactions.update',
            'crm.interactions.delete',
            
            

          

        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }
    }
}
