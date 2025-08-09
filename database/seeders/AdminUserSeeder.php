<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Modules\Security\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Creando permisos, roles y usuario administrador...');

        // 1. Crear todos los permisos necesarios
        $this->createPermissions();

        // 2. Crear rol admin con todos los permisos
        $this->createAdminRole();

        // 3. Crear usuario administrador
        $this->createAdminUser();

        $this->command->info('✅ Usuario administrador creado exitosamente!');
        $this->command->info('📧 Email: admin@casabonita.com');
        $this->command->info('🔑 Password: password');
    }

    private function createPermissions(): void
    {
        $this->command->info('📋 Creando permisos...');

        $permissions = [
            // MODULE SECURITY
            'security.access',
            'security.permissions.view',
            'security.permissions.store',
            'security.permissions.update',
            'security.permissions.destroy',
            'security.roles.view',
            'security.roles.store',
            'security.roles.update',
            'security.roles.destroy',
            'security.users.index',
            'security.users.store',
            'security.users.update',
            'security.users.destroy',
            'security.users.change-password',
            'security.users.toggle-status',

            // MODULE CRM
            'crm.access',
            'crm.addresses.view',
            'crm.addresses.store',
            'crm.addresses.update',
            'crm.addresses.destroy',
            'crm.clients.view',
            'crm.clients.store',
            'crm.clients.update',
            'crm.clients.delete',
            'crm.clients.spouses.view',
            'crm.clients.spouses.store',
            'crm.clients.spouses.delete',
            'crm.clients.export',
            'crm.interactions.view',
            'crm.interactions.store',
            'crm.interactions.update',
            'crm.interactions.delete',

            // MODULE INVENTORY
            'inventory.access',
            'inventory.manzanas.view',
            'inventory.manzanas.store',
            'inventory.manzanas.update',
            'inventory.manzanas.delete',
            'inventory.street-types.view',
            'inventory.street-types.store',
            'inventory.street-types.update',
            'inventory.street-types.delete',
            'inventory.lots.view',
            'inventory.lots.store',
            'inventory.lots.update',
            'inventory.lots.delete',
            'inventory.media.manage',
            'inventory.media.index',
            'inventory.media.store',
            'inventory.media.update',
            'inventory.media.destroy',

            // MODULE SALES
            'sales.access',
            'sales.reservations.access',
            'sales.reservations.view',
            'sales.reservations.store',
            'sales.reservations.update',
            'sales.reservations.cancel',
            'sales.reservations.convert',
            'sales.contracts.view',
            'sales.contracts.store',
            'sales.contracts.update',
            'sales.contracts.delete',
            'sales.conversions.process',

            // MODULE SERVICE DESK
            'service-desk.tickets.view',
            'service-desk.tickets.store',
            'service-desk.tickets.update',
            'service-desk.tickets.delete',
            'service-desk.tickets.assign',
            'service-desk.tickets.actions',
            'service-desk.tickets.close',
            'service-desk.actions.view',
            'service-desk.actions.store',
            'service-desk.actions.update',
            'service-desk.actions.delete',

            // MODULE HUMAN RESOURCES
            'hr.access',
            'hr.employees.view',
            'hr.employees.store',
            'hr.employees.update',
            'hr.employees.destroy',
            'hr.employees.generate-user',
            'hr.employees.dashboard',
            'hr.commissions.view',
            'hr.commissions.store',
            'hr.commissions.update',
            'hr.commissions.destroy',
            'hr.commissions.pay',
            'hr.commissions.process',
            'hr.commissions.split-payment',
            'hr.commission-verifications.view',
            'hr.commission-verifications.verify',
            'hr.commission-verifications.reverse',
            'hr.commission-verifications.process',
            'hr.commission-verifications.stats',
            'hr.payroll.view',
            'hr.payroll.generate',
            'hr.payroll.process',
            'hr.payroll.approve',
            'hr.bonuses.view',
            'hr.bonuses.store',
            'hr.bonuses.update',
            'hr.bonuses.destroy',
            'hr.bonuses.process',
            'hr.bonus-types.view',
            'hr.bonus-types.store',
            'hr.bonus-types.update',
            'hr.bonus-types.destroy',
            'hr.bonus-goals.view',
            'hr.bonus-goals.store',
            'hr.bonus-goals.update',
            'hr.bonus-goals.destroy',
            'hr.teams.view',
            'hr.teams.store',
            'hr.teams.update',
            'hr.teams.destroy',
            'hr.teams.assign-leader',
            'hr.teams.toggle-status',
            'hr.employee-import.validate',
            'hr.employee-import.import',
            'hr.employee-import.template',
            'hr.employees.commissions.view',

            // MODULE COLLECTIONS - CRÍTICO PARA RESOLVER EL PROBLEMA
            'collections.access',
            'collections.customer-payments.view',
            'collections.customer-payments.create',
            'collections.customer-payments.update',
            'collections.customer-payments.delete',
            'collections.customer-payments.redetect',
            'collections.customer-payments.stats',
            'collections.accounts-receivable.view',
            'collections.accounts-receivable.overdue',
            'collections.hr-integration.view',
            'collections.hr-integration.sync',
            'collections.hr-integration.process',
            'collections.hr-integration.mark',

            // MODULE FINANCE
            'finance.access',
            'finance.accounts.view',
            'finance.accounts.store',
            'finance.accounts.update',
            'finance.accounts.delete',

            // MODULE ACCOUNTING
            'accounting.access',
            'accounting.entries.view',
            'accounting.entries.store',
            'accounting.entries.update',
            'accounting.entries.delete',

            // MODULE AUDIT
            'audit.access',
            'audit.logs.view',
            'audit.reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $this->command->info('✅ ' . count($permissions) . ' permisos creados/verificados.');
    }

    private function createAdminRole(): void
    {
        $this->command->info('👑 Creando rol administrador...');

        // Crear rol admin
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'sanctum',
        ]);

        // Verificar que el rol se creó correctamente
        if (!$adminRole->role_id) {
            $this->command->error('❌ Error: No se pudo crear el rol admin');
            return;
        }

        $this->command->info('✅ Rol admin creado con ID: ' . $adminRole->role_id);

        // Asignar TODOS los permisos al rol admin
        $allPermissions = Permission::where('guard_name', 'sanctum')->get();
        
        if ($allPermissions->count() > 0) {
            $adminRole->syncPermissions($allPermissions);
            $this->command->info('✅ ' . $allPermissions->count() . ' permisos asignados al rol admin.');
        } else {
            $this->command->warn('⚠️ No se encontraron permisos para asignar.');
        }
    }

    private function createAdminUser(): void
    {
        $this->command->info('👤 Creando usuario administrador...');

        // Crear usuario administrador
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@casabonita.com'],
            [
                'username' => 'admin',
                'first_name' => 'Administrador',
                'last_name' => 'Sistema',
                'password_hash' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        // Asignar rol admin al usuario
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminUser->assignRole($adminRole);
        }

        $this->command->info('✅ Usuario administrador creado y configurado.');
    }
}