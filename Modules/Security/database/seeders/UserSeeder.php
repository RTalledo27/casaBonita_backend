<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Security\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */


     /*

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
        'password_hash',
        'created_by'*/

    public function run(): void
    {
        $admin = User::factory()->create([
            'username'      => 'admin',
            'first_name'    => 'Admin',
            'last_name'     => 'User',
            'email'         => 'admin@casabonita.com',
            'password_hash' => bcrypt('Romaim27'),
            'status'        => 'active',
            'photo_profile' => 'https://img.freepik.com/free-vector/business-user-cog_78370-7040.jpg?semt=ais_hybrid&w=740'

        ]);


        $admin->assignRole('admin');
    }
}
