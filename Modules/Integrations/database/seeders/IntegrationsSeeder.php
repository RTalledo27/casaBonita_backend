<?php

namespace Modules\Integrations\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class IntegrationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'integrations.access']);
        Permission::firstOrCreate(['name' => 'integrations.api.sunat']);
        Permission::firstOrCreate(['name' => 'integrations.api.payment']);
    }
}
