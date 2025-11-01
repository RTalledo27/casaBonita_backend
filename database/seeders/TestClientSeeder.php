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
            // Crear algunos clientes de prueba usando los campos correctos del modelo
            Client::create([
                'first_name' => 'Maria',
                'last_name' => 'González',
                'email' => 'maria.gonzalez@example.com',
                'primary_phone' => '123456789',
                'doc_type' => 'DNI',
                'doc_number' => '12345678',
                'type' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Client::create([
                'first_name' => 'Mario',
                'last_name' => 'López',
                'email' => 'mario.lopez@example.com',
                'primary_phone' => '987654321',
                'doc_type' => 'DNI',
                'doc_number' => '87654321',
                'type' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Client::create([
                'first_name' => 'Ana',
                'last_name' => 'Martínez',
                'email' => 'ana.martinez@example.com',
                'primary_phone' => '555666777',
                'doc_type' => 'DNI',
                'doc_number' => '55566677',
                'type' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Client::create([
                'first_name' => 'Carlos',
                'last_name' => 'Rodríguez',
                'email' => 'carlos.rodriguez@example.com',
                'primary_phone' => '111222333',
                'doc_type' => 'DNI',
                'doc_number' => '11122233',
                'type' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Clientes de prueba creados exitosamente.');
        } else {
            $this->command->info('Ya existen clientes en la base de datos.');
        }
    }
}
