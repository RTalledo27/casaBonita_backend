<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeder simplificado que crea únicamente un usuario administrador
        // con todos los permisos necesarios para el sistema
        $this->call(AdminUserSeeder::class);
        
        $this->command->info('');
        $this->command->info('🎯 Sistema listo con usuario administrador!');
        $this->command->info('📧 Email: admin@casabonita.com');
        $this->command->info('🔑 Password: password');
        $this->command->info('');
        $this->command->info('✅ Ahora puedes acceder al módulo Collections sin problemas de permisos.');
    }
}
