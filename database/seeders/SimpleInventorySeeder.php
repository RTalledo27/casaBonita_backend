<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\StreetType;

class SimpleInventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear tipos de calle básicos
        $streetTypes = [
            'Avenida',
            'Calle',
            'Pasaje'
        ];

        foreach ($streetTypes as $type) {
            StreetType::firstOrCreate(['name' => $type]);
        }

        // Crear manzanas básicas
        $manzanas = [];
        for ($i = 1; $i <= 3; $i++) {
            $manzana = Manzana::firstOrCreate([
                'name' => "Manzana $i"
            ]);
            $manzanas[] = $manzana;
        }

        // Crear lotes básicos
        $streetTypeIds = StreetType::pluck('street_type_id')->toArray();
        
        foreach ($manzanas as $index => $manzana) {
            // Crear 3 lotes por manzana
            for ($j = 1; $j <= 3; $j++) {
                $lotNumber = $j; // 1, 2, 3, etc.
                
                Lot::firstOrCreate([
                    'manzana_id' => $manzana->manzana_id,
                    'num_lot' => $lotNumber
                ], [
                    'street_type_id' => $streetTypeIds[0] ?? null,
                    'area_m2' => 150 + ($j * 20),
                    'area_construction_m2' => 100 + ($j * 10),
                    'total_price' => (150 + ($j * 20)) * 600,
                    'currency' => 'USD',
                    'status' => 'disponible'
                ]);
            }
        }

        $this->command->info('✅ Inventario básico creado exitosamente');
    }
}
