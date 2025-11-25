<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HumanResources\Models\CommissionScheme;
use Modules\HumanResources\Models\CommissionRule;

class CommissionDefaultSeeder extends Seeder
{
    public function run(): void
    {
        // Crear scheme por defecto
        $scheme = CommissionScheme::create([
            'name' => 'Default - legacy rates',
            'description' => 'Esquema por defecto con las tasas legacy (12/24/36 vs 48/60)',
            'is_default' => true
        ]);

        // Reglas legacy: short term (12/24/36)
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 10,
            'max_sales' => null,
            'term_group' => 'short',
            'term_min_months' => null,
            'term_max_months' => 36,
            'percentage' => 4.20,
            'sale_type' => 'financed',
            'priority' => 100
        ]);
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 8,
            'max_sales' => 9,
            'term_group' => 'short',
            'term_min_months' => null,
            'term_max_months' => 36,
            'percentage' => 4.00,
            'sale_type' => 'financed',
            'priority' => 90
        ]);
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 6,
            'max_sales' => 7,
            'term_group' => 'short',
            'term_min_months' => null,
            'term_max_months' => 36,
            'percentage' => 3.00,
            'sale_type' => 'financed',
            'priority' => 80
        ]);
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 0,
            'max_sales' => 5,
            'term_group' => 'short',
            'term_min_months' => null,
            'term_max_months' => 36,
            'percentage' => 2.00,
            'sale_type' => 'financed',
            'priority' => 10
        ]);

        // Legacy long term (48/60)
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 10,
            'max_sales' => null,
            'term_group' => 'long',
            'term_min_months' => 37,
            'term_max_months' => null,
            'percentage' => 3.00,
            'sale_type' => 'financed',
            'priority' => 100
        ]);
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 8,
            'max_sales' => 9,
            'term_group' => 'long',
            'term_min_months' => 37,
            'term_max_months' => null,
            'percentage' => 2.50,
            'sale_type' => 'financed',
            'priority' => 90
        ]);
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 6,
            'max_sales' => 7,
            'term_group' => 'long',
            'term_min_months' => 37,
            'term_max_months' => null,
            'percentage' => 1.50,
            'sale_type' => 'financed',
            'priority' => 80
        ]);
        CommissionRule::create([
            'scheme_id' => $scheme->id,
            'min_sales' => 0,
            'max_sales' => 5,
            'term_group' => 'long',
            'term_min_months' => 37,
            'term_max_months' => null,
            'percentage' => 1.00,
            'sale_type' => 'financed',
            'priority' => 10
        ]);
    }
}
