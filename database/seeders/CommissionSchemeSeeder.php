<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\CommissionScheme;
use Modules\HumanResources\Models\CommissionRule;

class CommissionSchemeSeeder extends Seeder
{
    /**
     * Seed the default commission scheme and rules.
     * 
     * Tabla de comisiones por defecto:
     * ┌────────────┬──────────────────────┬──────────────────────┐
     * │ Ventas     │ Plazo Corto (12-36)  │ Plazo Largo (48-60)  │
     * ├────────────┼──────────────────────┼──────────────────────┤
     * │ 1 - 5      │ 2.00%                │ 1.00%                │
     * │ 6 - 7      │ 3.00%                │ 1.50%                │
     * │ 8 - 9      │ 4.00%                │ 2.50%                │
     * │ 10+        │ 4.20%                │ 3.00%                │
     * └────────────┴──────────────────────┴──────────────────────┘
     */
    public function run(): void
    {
        $this->command->info('💰 Creando esquema de comisiones por defecto...');

        // Crear esquema principal
        $scheme = CommissionScheme::firstOrCreate(
            ['name' => 'Esquema Casa Bonita 2025'],
            [
                'description' => 'Esquema de comisiones por defecto. Tasas basadas en cantidad de ventas mensuales y plazo del financiamiento.',
                'effective_from' => '2025-01-01',
                'effective_to' => null,
                'is_default' => true,
            ]
        );

        $this->command->line("   ✓ Esquema '{$scheme->name}' creado (ID: {$scheme->id})");

        // Si ya tiene reglas, no crear duplicados
        if ($scheme->rules()->count() > 0) {
            $this->command->line("   ℹ El esquema ya tiene {$scheme->rules()->count()} reglas, omitiendo creación.");
            return;
        }

        // Definir reglas de comisión (12 reglas según tabla oficial)
        $rules = [
            // ── CASH (Al Contado) ──────────────────────────────────
            [
                'min_sales' => 1,
                'max_sales' => null,
                'term_group' => 'short',
                'sale_type' => 'cash',
                'term_min_months' => 12,
                'term_max_months' => 36,
                'percentage' => 4.00,
                'priority' => 100,
            ],
            [
                'min_sales' => 1,
                'max_sales' => null,
                'term_group' => 'short',
                'sale_type' => 'cash',
                'term_min_months' => 48,
                'term_max_months' => 60,
                'percentage' => 3.00,
                'priority' => 90,
            ],

            // ── FINANCIADO: 0-3 ventas (sin comisión) ──────────────
            [
                'min_sales' => 0,
                'max_sales' => 3,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 12,
                'term_max_months' => 36,
                'percentage' => 0.00,
                'priority' => 5,
            ],
            [
                'min_sales' => 0,
                'max_sales' => 3,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 48,
                'term_max_months' => 60,
                'percentage' => 0.00,
                'priority' => 5,
            ],

            // ── FINANCIADO: 4-5 ventas ─────────────────────────────
            [
                'min_sales' => 4,
                'max_sales' => 5,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 12,
                'term_max_months' => 36,
                'percentage' => 2.00,
                'priority' => 10,
            ],
            [
                'min_sales' => 4,
                'max_sales' => 5,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 48,
                'term_max_months' => 60,
                'percentage' => 1.00,
                'priority' => 10,
            ],

            // ── FINANCIADO: 6-7 ventas ─────────────────────────────
            [
                'min_sales' => 6,
                'max_sales' => 7,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 12,
                'term_max_months' => 36,
                'percentage' => 3.00,
                'priority' => 20,
            ],
            [
                'min_sales' => 6,
                'max_sales' => 7,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 48,
                'term_max_months' => 60,
                'percentage' => 1.50,
                'priority' => 20,
            ],

            // ── FINANCIADO: 8-9 ventas ─────────────────────────────
            [
                'min_sales' => 8,
                'max_sales' => 9,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 12,
                'term_max_months' => 36,
                'percentage' => 4.00,
                'priority' => 30,
            ],
            [
                'min_sales' => 8,
                'max_sales' => 9,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 48,
                'term_max_months' => 60,
                'percentage' => 2.50,
                'priority' => 30,
            ],

            // ── FINANCIADO: 10+ ventas ─────────────────────────────
            [
                'min_sales' => 10,
                'max_sales' => null,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 12,
                'term_max_months' => 36,
                'percentage' => 4.20,
                'priority' => 40,
            ],
            [
                'min_sales' => 10,
                'max_sales' => null,
                'term_group' => 'short',
                'sale_type' => 'financed',
                'term_min_months' => 48,
                'term_max_months' => 60,
                'percentage' => 3.00,
                'priority' => 40,
            ],
        ];

        // Crear reglas
        foreach ($rules as $ruleData) {
            CommissionRule::create(array_merge(
                ['scheme_id' => $scheme->id],
                $ruleData
            ));
        }

        $this->command->line("   ✓ {$scheme->rules()->count()} reglas de comisiones creadas");
        $this->command->info('✅ Esquema de comisiones creado exitosamente!');
    }
}
