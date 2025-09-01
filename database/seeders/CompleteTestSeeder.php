<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder;
use Modules\Audit\Database\Seeders\AuditDatabaseSeeder;
use Modules\CRM\Database\Seeders\CRMDatabaseSeeder;
use Modules\HumanResources\Database\Seeders\HumanResourcesDatabaseSeeder;
use Modules\Inventory\Database\Seeders\InventoryDatabaseSeeder;
use Modules\Sales\Database\Seeders\SalesDatabaseSeeder;
use Modules\Security\Database\Seeders\SecurityDatabaseSeeder;

class CompleteTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Iniciando seeder completo del sistema...');

        // Deshabilitar verificaciones de claves foráneas temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 1. Seguridad (usuarios, roles, permisos) - PRIMERO
            $this->command->info('📋 Seeding Security module...');
            $this->call(SecurityDatabaseSeeder::class);

            // 2. CRM (clientes) - SEGUNDO
            $this->command->info('👥 Seeding CRM module...');
            $this->call(CRMDatabaseSeeder::class);

            // 3. Inventario (manzanas, lotes) - TERCERO
            $this->command->info('🏘️  Seeding Inventory module...');
            $this->call(InventoryDatabaseSeeder::class);

            // 4. Recursos Humanos (empleados, equipos) - CUARTO
            $this->command->info('👨‍💼 Seeding Human Resources module...');
            $this->call(HumanResourcesDatabaseSeeder::class);

            // 5. Ventas (reservaciones, contratos) - QUINTO
            $this->command->info('💰 Seeding Sales module...');
            $this->call(SalesDatabaseSeeder::class);

            // 6. Contabilidad - SEXTO
            $this->command->info('📊 Seeding Accounting module...');
            $this->call(AccountingDatabaseSeeder::class);

            // 7. Auditoría - SÉPTIMO
            $this->command->info('🔍 Seeding Audit module...');
            $this->call(AuditDatabaseSeeder::class);
        } finally {
            // Rehabilitar verificaciones de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command->info('✅ ¡Seeder completo ejecutado exitosamente!');
        $this->command->info('');
        $this->command->info('📋 Resumen de datos creados:');
        $this->command->info('   - Usuarios y roles de seguridad');
        $this->command->info('   - 30 clientes con direcciones e interacciones');
        $this->command->info('   - 10 manzanas con 80-150 lotes');
        $this->command->info('   - Equipos de trabajo y empleados');
        $this->command->info('   - 15 reservaciones y 10 contratos');
        $this->command->info('   - Comisiones y bonos históricos');
        $this->command->info('   - Usuario admin del dashboard: admin@dashboard.com / password');
        $this->command->info('');
        $this->command->info('🎯 El sistema está listo para demostrar toda su funcionalidad!');
    }
}
