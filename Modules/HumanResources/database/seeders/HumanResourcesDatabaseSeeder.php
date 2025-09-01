<?php

namespace Modules\HumanResources\Database\Seeders;

use Illuminate\Database\Seeder;

class HumanResourcesDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        $this->call([
            TestRHSeeder::class,
            HrIntegrationTestSeeder::class
        ]);
    }
}
