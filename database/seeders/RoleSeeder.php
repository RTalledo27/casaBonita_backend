<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('module:seed', [
            'module' => ['Security'],
            '--class' => 'RoleSeeder',
        ]);
    }
}

