<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Manzana;
use Modules\Inventory\Models\StreetType;

class InventoryDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear tipos de calle
        $streetTypes = [
            'Avenida',
            'Calle',
            'Pasaje',
            'Boulevard',
            'Carrera'
        ];

        foreach ($streetTypes as $type) {
            StreetType::firstOrCreate(['name' => $type]);
        }

        // Crear manzanas
        $manzanas = [];
        for ($i = 1; $i <= 10; $i++) {
            $manzana = Manzana::firstOrCreate([
                'name' => "Manzana $i"
            ]);
            $manzanas[] = $manzana;
        }

        // Crear lotes
        $streetTypeIds = StreetType::pluck('street_type_id')->toArray();
        $statuses = ['disponible', 'reservado', 'vendido'];
        $currencies = ['USD', 'BOB'];

        foreach ($manzanas as $manzana) {
            // Crear entre 8 y 15 lotes por manzana
            $numLots = rand(8, 15);

            for ($j = 1; $j <= $numLots; $j++) {
                $area = rand(200, 800); // metros cuadrados
                $constructionArea = rand(80, min(300, $area - 50));
                $pricePerM2 = rand(150, 400); // precio por m2
                $totalPrice = $area * $pricePerM2;

                // Funding como valor decimal (porcentaje de financiamiento)
                $fundingPercentage = rand(0, 100) / 100; // 0.0 a 1.0 (0% a 100%)
                $fundingAmount = $totalPrice * $fundingPercentage;

                Lot::firstOrCreate([
                    'manzana_id' => $manzana->manzana_id,
                    'num_lot' => $j
                ], [
                    'street_type_id' => $streetTypeIds[array_rand($streetTypeIds)],
                    'area_m2' => $area,
                    'area_construction_m2' => $constructionArea,
                    'total_price' => $totalPrice,
                    'funding' => $fundingAmount, // Valor decimal en lugar de string
                    'BPP' => rand(10000, 50000),
                    'BFH' => rand(5000, 25000),
                    'initial_quota' => rand(1000, 5000),
                    'currency' => $currencies[array_rand($currencies)],
                    'status' => $statuses[array_rand($statuses)]
                ]);
            }
        }

        $this->command->info('✅ Datos de inventario creados exitosamente');
    }
}
