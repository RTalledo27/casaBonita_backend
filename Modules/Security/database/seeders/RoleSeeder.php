<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Security\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void{
     // 1) Obtén todos los permisos que ya sembraste
     $allPermissions = \Spatie\Permission\Models\Permission::pluck('name')->toArray();

     // 2) Crea roles administradores y asígnales TODO
     $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
     $admin->syncPermissions($allPermissions);
     
     $adminEs = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'sanctum']);
     $adminEs->syncPermissions($allPermissions);

     // 3) Ejemplo de rol 'manager' con subset
    /* $managerPerms = [
         'security.users.index',
         'crm.clients.view',
         'crm.interactions.view',
         // …añade aquí los permisos que quieras para manager
     ];
     $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
     $manager->syncPermissions($managerPerms);

     // 4) Otro rol, p.ej. 'agent'
     $agentPerms = [
         'crm.clients.view',
         'crm.clients.spouses.view',
         'crm.interactions.view',
     ];
     $agent = Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'sanctum']);
     $agent->syncPermissions($agentPerms);*/

 
 }
}
