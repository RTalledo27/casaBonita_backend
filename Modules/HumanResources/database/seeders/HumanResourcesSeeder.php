<?php

namespace Modules\HumanResources\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\Bonus;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Team;
use Modules\Security\Models\User;

class HumanResourcesSeeder extends Seeder
{
    public function run(): void
    {
        // Crear equipos
        $salesTeam = Team::create([
            'team_name' => 'Equipo de Ventas',
            'monthly_goal' => 500000.00,
            'is_active' => '1'
        ]);

        $adminTeam = Team::create([
            'team_name' => 'Equipo Administrativo',
            'monthly_goal' => null,
            'is_active' => '1'
        ]);

        // Crear usuarios para empleados
        $hrUser = User::create([
            'first_name' => 'María',
            'last_name' => 'González',
            'username'=>'mgonzales',
            'email' => 'maria.gonzalez@empresa.com',
            'phone' => '987654321',
            'password_hash' => bcrypt('password'),
           // 'email_verified_at' => now(),
        ]);

        $managerUser = User::create([
            'first_name' => 'Carlos',
            'last_name' => 'Rodríguez',
            'username' => 'crodriguez',
            'email' => 'carlos.rodriguez@empresa.com',
            'phone' => '987654322',
            'password_hash' => bcrypt('password'),
            //'email_verified_at' => now(),
        ]);

        $advisorUser1 = User::create([
            'first_name' => 'Ana',
            'last_name' => 'López',
            'username' => 'alopez',
            'email' => 'ana.lopez@empresa.com',
            'phone' => '987654323',
            'password_hash' => bcrypt('password'),
            //'email_verified_at' => now(),
        ]);

        $advisorUser2 = User::create([
            'first_name' => 'Luis',
            'last_name' => 'Martínez',
            'username'=>'lmartinez',
            'email' => 'luis.martinez@empresa.com',
            'phone' => '987654324',
            'password_hash' => bcrypt('password'),
            //'email_verified_at' => now(),
        ]);

        // Crear empleados
        $hrEmployee = Employee::create([
            'user_id' => $hrUser->user_id,
            'employee_code' => 'EMP0001',
            'employee_type' => 'administrativo', // O 'jefe', según cómo lo clasifiques
            'hire_date' => '2023-01-15',
            'base_salary' => 4500.00,
            'commission_percentage' => null,
            'individual_goal' => null,
            'team_id' => $adminTeam->team_id,
            'employment_status' => 'activo'
        ]);

        $managerEmployee = Employee::create([
            'user_id' => $managerUser->user_id,
            'employee_code' => 'EMP0002',
            'employee_type' => 'gerente',
            'hire_date' => '2023-02-01',
            'base_salary' => 6000.00,
            'commission_percentage' => 2.0,
            'individual_goal' => 200000.00,
            'team_id' => $salesTeam->team_id,
            'employment_status' => 'activo'
        ]);

        $advisorEmployee1 = Employee::create([
            'user_id' => $advisorUser1->user_id,
            'employee_code' => 'EMP0003',
            'employee_type' => 'asesor_inmobiliario',
            'hire_date' => '2023-03-01',
            'base_salary' => 2500.00,
            'commission_percentage' => 5.0,
            'individual_goal' => 150000.00,
            'team_id' => $salesTeam->team_id,
            'employment_status' => 'activo'
        ]);

        $advisorEmployee2 = Employee::create([
            'user_id' => $advisorUser2->user_id,
            'employee_code' => 'EMP0004',
            'employee_type' => 'asesor_inmobiliario',
            'hire_date' => '2023-04-01',
            'base_salary' => 2200.00,
            'commission_percentage' => 4.5,
            'individual_goal' => 120000.00,
            'team_id' => $salesTeam->team_id,
            'employment_status' => 'activo'
        ]);

        // Actualizar team leader
        $salesTeam->update(['team_leader_id' => $managerEmployee->employee_id]);

        // Crear bonos de ejemplo
       

        Bonus::create([
            'employee_id' => $advisorEmployee2->employee_id,
            'bonus_type' => 'performance',
            'bonus_name' => 'Bono por cumplimiento de meta mensual',
            'bonus_amount' => 200.00,
            'target_amount' => null,
            'achieved_amount' => null,
            'achievement_percentage' => null,
            'payment_status' => 'pendiente',
            'payment_date' => null,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'period_quarter' => ceil(now()->month / 3),
            'approved_by' => $managerEmployee->employee_id,
            'approved_at' => now(),
            'notes' => 'Otorgado por cumplimiento de meta mensua'
        ]);

        Bonus::create([
            'employee_id' => $advisorEmployee2->employee_id,
            'bonus_type' => 'asistencia',
            'bonus_name' => 'Bono por asistencia perfecta',
            'bonus_amount' => 200.00,
            'target_amount' => null,
            'achieved_amount' => null,
            'achievement_percentage' => null,
            'payment_status' => 'pendiente',
            'payment_date' => null,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'period_quarter' => ceil(now()->month / 3),
            'approved_by' => $managerEmployee->employee_id,
            'approved_at' => now(),
            'notes' => 'Otorgado por cumplimiento de asistencia completa en el mes'
        ]);


        $this->command->info('✅ Datos de Recursos Humanos creados exitosamente');
    }
}
