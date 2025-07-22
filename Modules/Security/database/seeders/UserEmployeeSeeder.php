<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\HumanResources\Models\Employee;
use Modules\Security\Models\Role;
use Modules\Security\Models\User;

class UserEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        try {
            // Crear roles si no existen
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
            $advisorRole = Role::firstOrCreate(['name' => 'asesor_inmobiliario', 'guard_name' => 'sanctum']);

            // Crear usuario administrador
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@casabonita.com'],
                [
                    'username' => 'admin',
                    'first_name' => 'Admin',
                    'last_name' => 'Principal',
                    'password_hash' => bcrypt('admin123'),
                    'status' => 'active'
                ]
            );
            $adminUser->roles()->sync([$adminRole->role_id]);

            // Asociar a empleado administrativo
            $adminEmployee = Employee::firstOrCreate(
                ['user_id' => $adminUser->user_id],
                [
                    'employee_code' => 'EMPADMIN',
                    'employee_type' => 'administrativo',
                    'hire_date' => now(),
                    'base_salary' => 5000,
                    'employment_status' => 'activo'
                ]
            );

            // Crear usuario asesor inmobiliario
            $advisorUser = User::firstOrCreate(
                ['email' => 'asesor@casabonita.com'],
                [
                    'username' => 'asesor',
                    'first_name' => 'Asesor',
                    'last_name' => 'Inmobiliario',
                    'password_hash' => bcrypt('asesor123'),
                    'status' => 'active'
                ]
            );
            $advisorUser->roles()->sync([$advisorRole->role_id]);

            // Asociar a empleado asesor
            $advisorEmployee = Employee::firstOrCreate(
                ['user_id' => $advisorUser->user_id],
                [
                    'employee_code' => 'EMPASESOR',
                    'employee_type' => 'asesor_inmobiliario',
                    'hire_date' => now(),
                    'base_salary' => 2500,
                    'commission_percentage' => 5.0,
                    'individual_goal' => 100000,
                    'employment_status' => 'activo'
                ]
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

