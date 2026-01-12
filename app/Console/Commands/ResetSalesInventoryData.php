<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetSalesInventoryData extends Command
{
    protected $signature = 'db:reset-sales-inventory
                            {--force : Ejecutar sin confirmación}
                            {--seed=test : none|simple|test (recarga data luego del borrado)}
                            {--lock-wait=15 : Segundos máximos esperando locks de MySQL por tabla}';

    protected $description = 'Borra datos de contratos/reservas/cronogramas e inventario, y opcionalmente recarga data';

    public function handle(): int
    {
        $seedProfile = (string) $this->option('seed');
        $allowedProfiles = ['none', 'simple', 'test'];
        if (!in_array($seedProfile, $allowedProfiles, true)) {
            $this->error("Perfil --seed inválido: {$seedProfile}. Usa: " . implode('|', $allowedProfiles));
            return 1;
        }

        if (!$this->option('force')) {
            $this->warn('Esto borrará datos de: contratos, reservas, pagos/cronogramas, cobranzas asociadas e inventario.');
            if (!$this->confirm('¿Continuar?')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        $tables = [
            'collection_followup_logs',
            'collection_message_logs',
            'collection_followups',
            'customer_payments',
            'accounts_receivable',
            'payments',
            'payment_schedules',
            'commissions',
            'contract_approvals',
            'contracts',
            'reservations',
            'lot_financial_templates',
            'lot_media',
            'lot_import_logs',
            'lots',
            'manzana_financing_rules',
            'manzanas',
            'street_types',
        ];

        $this->info('🧹 Limpiando tablas objetivo...');

        $lockWait = (int) $this->option('lock-wait');
        if ($lockWait > 0) {
            DB::statement('SET SESSION lock_wait_timeout = ' . $lockWait);
            DB::statement('SET SESSION innodb_lock_wait_timeout = ' . $lockWait);
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $this->line("  - {$table} (no existe, omitido)");
                    continue;
                }

                $this->line("  ⏳ {$table}");
                try {
                    DB::table($table)->truncate();
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Error en tabla {$table}: " . $e->getMessage(), previous: $e);
                }
                $this->line("  ✓ {$table}");
            }
        } catch (\Throwable $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Error borrando datos: ' . $e->getMessage());
            $this->line('Sugerencia: detener workers/reverb y reintentar, o aumentar --lock-wait.');
            return 1;
        }

        Schema::enableForeignKeyConstraints();
        $this->info('✅ Datos borrados.');

        if ($seedProfile === 'none') {
            $this->info('ℹ️  Seed omitido (--seed=none).');
            return 0;
        }

        $this->info("🌱 Recargando data (perfil: {$seedProfile})...");

        try {
            if ($seedProfile === 'simple') {
                $this->callSeeder('Database\\Seeders\\SimpleInventorySeeder');
                $this->callSeeder('Database\\Seeders\\TestClientSeeder');
                return 0;
            }

            if ($seedProfile === 'test') {
                $this->callSeeder('Database\\Seeders\\SimpleInventorySeeder');
                $this->callSeeder('Database\\Seeders\\TestClientSeeder');
                $this->callSeeder('Database\\Seeders\\TestSalesDataSeeder');
                $this->callSeeder('Database\\Seeders\\TestPaymentScheduleSeeder');
                return 0;
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error('Error ejecutando seed: ' . $e->getMessage());
            return 1;
        }
    }

    private function callSeeder(string $class): void
    {
        $this->line("  ▶ {$class}");
        Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
        $this->output->write(Artisan::output());
    }
}
