<?php

namespace Modules\HumanResources\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\BonusType;
use Modules\HumanResources\Models\BonusGoal;

class BonusTypesProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding production bonus types and goals...');

        // =============================================
        // 1. INDIVIDUAL_GOAL - Meta Individual
        // =============================================
        $individualGoal = BonusType::updateOrCreate(
            ['type_code' => 'INDIVIDUAL_GOAL'],
            [
                'type_name' => 'Meta Individual',
                'description' => 'Bono por cumplimiento de meta individual de ventas (monto o cantidad)',
                'calculation_method' => 'percentage_of_goal',
                'is_automatic' => true,
                'requires_approval' => false,
                'applicable_employee_types' => ['asesor_inmobiliario', 'vendedor'],
                'frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        // Goals escalonados para Meta Individual (basados en % de cumplimiento)
        $individualGoals = [
            [
                'goal_name' => 'Meta individual 80%',
                'min_achievement' => 80,
                'max_achievement' => 99.99,
                'bonus_amount' => 300,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
            [
                'goal_name' => 'Meta individual 100%',
                'min_achievement' => 100,
                'max_achievement' => 119.99,
                'bonus_amount' => 600,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
            [
                'goal_name' => 'Meta individual 120%',
                'min_achievement' => 120,
                'max_achievement' => 149.99,
                'bonus_amount' => 1000,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
            [
                'goal_name' => 'Meta individual 150%',
                'min_achievement' => 150,
                'max_achievement' => null,
                'bonus_amount' => 1500,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
        ];

        foreach ($individualGoals as $goal) {
            BonusGoal::updateOrCreate(
                [
                    'bonus_type_id' => $individualGoal->bonus_type_id,
                    'goal_name' => $goal['goal_name'],
                ],
                array_merge($goal, [
                    'bonus_type_id' => $individualGoal->bonus_type_id,
                    'is_active' => true,
                    'valid_from' => '2025-01-01',
                    'valid_until' => null,
                ])
            );
        }

        $this->command->info("  ✓ INDIVIDUAL_GOAL: {$individualGoal->type_name} + " . count($individualGoals) . " niveles");

        // =============================================
        // 2. TEAM_GOAL - Meta de Equipo
        // =============================================
        $teamGoal = BonusType::updateOrCreate(
            ['type_code' => 'TEAM_GOAL'],
            [
                'type_name' => 'Meta de Equipo',
                'description' => 'Bono por cumplimiento de meta grupal del equipo de ventas',
                'calculation_method' => 'sales_count',
                'is_automatic' => true,
                'requires_approval' => false,
                'applicable_employee_types' => ['asesor_inmobiliario', 'vendedor'],
                'frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        $teamGoals = [
            [
                'goal_name' => 'Meta equipo 80%',
                'min_achievement' => 80,
                'max_achievement' => 99.99,
                'bonus_amount' => 200,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
            [
                'goal_name' => 'Meta equipo 100%',
                'min_achievement' => 100,
                'max_achievement' => 119.99,
                'bonus_amount' => 400,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
            [
                'goal_name' => 'Meta equipo 120%',
                'min_achievement' => 120,
                'max_achievement' => null,
                'bonus_amount' => 700,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
        ];

        foreach ($teamGoals as $goal) {
            BonusGoal::updateOrCreate(
                [
                    'bonus_type_id' => $teamGoal->bonus_type_id,
                    'goal_name' => $goal['goal_name'],
                ],
                array_merge($goal, [
                    'bonus_type_id' => $teamGoal->bonus_type_id,
                    'is_active' => true,
                    'valid_from' => '2025-01-01',
                    'valid_until' => null,
                ])
            );
        }

        $this->command->info("  ✓ TEAM_GOAL: {$teamGoal->type_name} + " . count($teamGoals) . " niveles");

        // =============================================
        // 3. OFFICE_GOAL - Meta de Oficina
        // =============================================
        $officeGoal = BonusType::updateOrCreate(
            ['type_code' => 'OFFICE_GOAL'],
            [
                'type_name' => 'Meta de Oficina',
                'description' => 'Bono por cumplimiento de meta de la oficina/sucursal',
                'calculation_method' => 'sales_count',
                'is_automatic' => true,
                'requires_approval' => false,
                'applicable_employee_types' => ['asesor_inmobiliario', 'vendedor'],
                'frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        $officeGoals = [
            [
                'goal_name' => 'Meta oficina 100%',
                'min_achievement' => 100,
                'max_achievement' => 119.99,
                'bonus_amount' => 300,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
            [
                'goal_name' => 'Meta oficina 120%',
                'min_achievement' => 120,
                'max_achievement' => null,
                'bonus_amount' => 500,
                'bonus_percentage' => null,
                'target_value' => null,
                'employee_type' => null,
                'team_id' => null,
                'office_id' => null,
            ],
        ];

        foreach ($officeGoals as $goal) {
            BonusGoal::updateOrCreate(
                [
                    'bonus_type_id' => $officeGoal->bonus_type_id,
                    'goal_name' => $goal['goal_name'],
                ],
                array_merge($goal, [
                    'bonus_type_id' => $officeGoal->bonus_type_id,
                    'is_active' => true,
                    'valid_from' => '2025-01-01',
                    'valid_until' => null,
                ])
            );
        }

        $this->command->info("  ✓ OFFICE_GOAL: {$officeGoal->type_name} + " . count($officeGoals) . " niveles");

        // =============================================
        // 4. QUARTERLY - Bono Trimestral
        // =============================================
        $quarterly = BonusType::updateOrCreate(
            ['type_code' => 'QUARTERLY'],
            [
                'type_name' => 'Bono Trimestral',
                'description' => 'Bono por cantidad de ventas acumuladas en el trimestre',
                'calculation_method' => 'sales_count',
                'is_automatic' => true,
                'requires_approval' => false,
                'applicable_employee_types' => ['asesor_inmobiliario'],
                'frequency' => 'quarterly',
                'is_active' => true,
            ]
        );

        BonusGoal::updateOrCreate(
            [
                'bonus_type_id' => $quarterly->bonus_type_id,
                'goal_name' => 'Trimestral - 30+ ventas',
            ],
            [
                'bonus_type_id' => $quarterly->bonus_type_id,
                'goal_name' => 'Trimestral - 30+ ventas',
                'min_achievement' => 30,
                'max_achievement' => null,
                'bonus_amount' => 1000,
                'bonus_percentage' => null,
                'target_value' => 30,
                'employee_type' => 'asesor_inmobiliario',
                'team_id' => null,
                'office_id' => null,
                'is_active' => true,
                'valid_from' => '2025-01-01',
                'valid_until' => null,
            ]
        );

        $this->command->info("  ✓ QUARTERLY: {$quarterly->type_name}");

        // =============================================
        // 5. BIWEEKLY - Bono Quincenal
        // =============================================
        $biweekly = BonusType::updateOrCreate(
            ['type_code' => 'BIWEEKLY'],
            [
                'type_name' => 'Bono Quincenal',
                'description' => 'Bono por cantidad de ventas en una quincena',
                'calculation_method' => 'sales_count',
                'is_automatic' => true,
                'requires_approval' => false,
                'applicable_employee_types' => ['asesor_inmobiliario'],
                'frequency' => 'biweekly',
                'is_active' => true,
            ]
        );

        BonusGoal::updateOrCreate(
            [
                'bonus_type_id' => $biweekly->bonus_type_id,
                'goal_name' => 'Quincenal - 6+ ventas',
            ],
            [
                'bonus_type_id' => $biweekly->bonus_type_id,
                'goal_name' => 'Quincenal - 6+ ventas',
                'min_achievement' => 6,
                'max_achievement' => null,
                'bonus_amount' => 500,
                'bonus_percentage' => null,
                'target_value' => 6,
                'employee_type' => 'asesor_inmobiliario',
                'team_id' => null,
                'office_id' => null,
                'is_active' => true,
                'valid_from' => '2025-01-01',
                'valid_until' => null,
            ]
        );

        $this->command->info("  ✓ BIWEEKLY: {$biweekly->type_name}");

        // =============================================
        // 6. COLLECTION - Bono de Recaudación
        // =============================================
        $collection = BonusType::updateOrCreate(
            ['type_code' => 'COLLECTION'],
            [
                'type_name' => 'Bono de Recaudación',
                'description' => 'Bono por monto de recaudación mensual',
                'calculation_method' => 'collection_amount',
                'is_automatic' => true,
                'requires_approval' => false,
                'applicable_employee_types' => ['asesor_inmobiliario'],
                'frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        BonusGoal::updateOrCreate(
            [
                'bonus_type_id' => $collection->bonus_type_id,
                'goal_name' => 'Recaudación - S/50,000+',
            ],
            [
                'bonus_type_id' => $collection->bonus_type_id,
                'goal_name' => 'Recaudación - S/50,000+',
                'min_achievement' => 100,
                'max_achievement' => null,
                'bonus_amount' => 500,
                'bonus_percentage' => null,
                'target_value' => 50000,
                'employee_type' => 'asesor_inmobiliario',
                'team_id' => null,
                'office_id' => null,
                'is_active' => true,
                'valid_from' => '2025-01-01',
                'valid_until' => null,
            ]
        );

        $this->command->info("  ✓ COLLECTION: {$collection->type_name}");

        // =============================================
        // 7. SPECIAL - Bono Especial (manual)
        // =============================================
        BonusType::updateOrCreate(
            ['type_code' => 'SPECIAL'],
            [
                'type_name' => 'Bono Especial',
                'description' => 'Bono especial asignado manualmente por administración',
                'calculation_method' => 'fixed_amount',
                'is_automatic' => false,
                'requires_approval' => true,
                'applicable_employee_types' => ['asesor_inmobiliario', 'vendedor', 'administrativo', 'gerente', 'supervisor', 'jefe_ventas'],
                'frequency' => 'one_time',
                'is_active' => true,
            ]
        );

        $this->command->info("  ✓ SPECIAL: Bono Especial (manual, todos los tipos)");
        $this->command->info('');
        $this->command->info('✅ Bonus types and goals seeded successfully!');
    }
}
