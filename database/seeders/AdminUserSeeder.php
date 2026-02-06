<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Security\Models\User;
use Modules\Security\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder:
     * 1. Ejecuta PermissionsSeeder para crear todos los permisos y roles
     * 2. Crea el usuario administrador
     * 3. Asigna el rol Administrador al usuario
     */
    public function run(): void
    {
        $this->command->info('🚀 Iniciando creación de usuario administrador...');

        // Ejecutar PermissionsSeeder para crear todos los permisos y roles
        $this->call(PermissionsSeeder::class);

        // Obtener el rol de administrador (ya creado por PermissionsSeeder)
        $adminRole = $this->getAdminRole();

        // Crear usuario administrador
        $adminUser = $this->createAdminUser();

        // Asignar rol al usuario
        $adminUser->assignRole($adminRole);

        $this->command->info('✅ Usuario administrador creado exitosamente!');
        $this->command->info('📋 Detalles del usuario:');
        $this->command->line('   • ID: ' . $adminUser->user_id);
        $this->command->line('   • Usuario: ' . $adminUser->username);
        $this->command->line('   • Email: ' . $adminUser->email);
        $this->command->line('   • Rol: ' . $adminRole->name);
        $this->command->line('   • Permisos: ' . $adminRole->permissions->count() . ' permisos asignados');
    }

    /**
     * Obtener rol de administrador (ya creado por PermissionsSeeder)
     */
    private function getAdminRole(): Role
    {
        $this->command->info('👑 Obteniendo rol de administrador...');

        $adminRole = Role::where('name', 'Administrador')->first();

        if (!$adminRole) {
            throw new \Exception('Error: No se encontró el rol Administrador. Asegúrese de que PermissionsSeeder se haya ejecutado correctamente.');
        }

        $this->command->line("   ✓ Rol 'Administrador' encontrado con {$adminRole->permissions->count()} permisos");

        return $adminRole;
    }

    /**
     * Crear usuario administrador
     */
    private function createAdminUser(): User
    {
        $this->command->info('� Creando usuario administrador...');

        // Buscar o crear usuario admin
        $adminUser = User::where('email', 'admin@casabonita.com')->first();

        if ($adminUser) {
            $this->command->line("   ℹ Usuario administrador ya existe (ID: {$adminUser->user_id})");
            return $adminUser;
        }

        // Crear nuevo usuario administrador
        $adminUser = User::create([
            'username' => 'admin',
            'email' => 'admin@casabonita.com',
            'password_hash' => Hash::make('admin123'),
            'status' => 'active',
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->line("   ✓ Usuario administrador creado (ID: {$adminUser->user_id})");

        return $adminUser;
    }
}

