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
        Permission::firstOrCreate(['name' => 'audit.access', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'audit.logs.view', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'audit.actions.track', 'guard_name' => 'sanctum']);
    }
}
