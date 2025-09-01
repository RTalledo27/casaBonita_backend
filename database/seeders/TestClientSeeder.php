<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\CRM\Models\Client;

class TestClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya existen clientes
        $existingClients = Client::count();
        
        if ($existingClients === 0) {
            // Crear algunos clientes de prueba
            Client::create([
                'first_name' => 'Maria',
                'last_name' => 'González',
                'email' => 'maria.gonzalez@example.com',
                'phone' => '123456789',
                'address' => 'Calle Principal 123',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Client::create([
                'first_name' => 'Mario',
                'last_name' => 'López',
                'email' => 'mario.lopez@example.com',
                'phone' => '987654321',
                'address' => 'Avenida Central 456',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Client::create([
                'first_name' => 'Ana',
                'last_name' => 'Martínez',
                'email' => 'ana.martinez@example.com',
                'phone' => '555666777',
                'address' => 'Plaza Mayor 789',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Client::create([
                'first_name' => 'Carlos',
                'last_name' => 'Rodríguez',
                'email' => 'carlos.rodriguez@example.com',
                'phone' => '111222333',
                'address' => 'Barrio Norte 321',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Clientes de prueba creados exitosamente.');
        } else {
            $this->command->info('Ya existen clientes en la base de datos.');
        }
    }
}
