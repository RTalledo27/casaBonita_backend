<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Security\Models\Permission;
use Modules\Security\Models\Role;

class BillingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear permiso "billing.access"
        $permission = Permission::firstOrCreate(
            ['name' => 'billing.access'],
            ['label' => 'Access Billing Module', 'description' => 'Can access billing module and emit documents']
        );

        // 2. Asignar permiso al rol Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        // Otros permisos granulares (opcional, por ahora solo billing.access)
        // Permission::firstOrCreate(['name' => 'billing.emit'], ...);
        // Permission::firstOrCreate(['name' => 'billing.view'], ...);
    }
}
