<?php

namespace Modules\Audit\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AuditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Permission::firstOrCreate(['name' => 'audit.access']);
        Permission::firstOrCreate(['name' => 'audit.logs.view']);
        Permission::firstOrCreate(['name' => 'audit.actions.track']);
    }
}
