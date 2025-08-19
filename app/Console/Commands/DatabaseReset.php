<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-all {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the entire database - drop all tables, run migrations and seed admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('¿Estás seguro de que quieres eliminar TODOS los datos de la base de datos? Esta acción no se puede deshacer.')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        $this->info('🔄 Iniciando reset completo de la base de datos...');

        try {
            // Paso 1: Deshabilitar verificación de claves foráneas
            $this->info('📋 Deshabilitando verificación de claves foráneas...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Paso 2: Obtener todas las tablas
            $this->info('🔍 Obteniendo lista de tablas...');
            $tables = DB::select('SHOW TABLES');
            $databaseName = DB::getDatabaseName();
            $tableKey = "Tables_in_{$databaseName}";

            // Paso 3: Eliminar todas las tablas
            $this->info('🗑️  Eliminando todas las tablas...');
            $tableCount = 0;
            foreach ($tables as $table) {
                $tableName = $table->$tableKey;
                DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                $tableCount++;
                $this->line("   ✓ Tabla eliminada: {$tableName}");
            }

            $this->info("📊 Total de tablas eliminadas: {$tableCount}");

            // Paso 4: Rehabilitar verificación de claves foráneas
            $this->info('🔧 Rehabilitando verificación de claves foráneas...');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Paso 5: Ejecutar migraciones
            $this->info('🏗️  Ejecutando migraciones...');
            Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());

            // Paso 6: Ejecutar seeder de admin
            $this->info('👤 Creando usuario administrador...');
            Artisan::call('db:seed', [
                '--class' => 'AdminUserSeeder',
                '--force' => true
            ]);
            $this->line(Artisan::output());

            $this->newLine();
            $this->info('✅ Reset de base de datos completado exitosamente!');
            $this->info('📋 Se ha creado:');
            $this->line('   • Usuario: admin');
            $this->line('   • Email: admin@casabonita.com');
            $this->line('   • Password: admin123');
            $this->line('   • Rol: Administrador (con todos los permisos)');
            $this->newLine();
            $this->warn('⚠️  Recuerda cambiar la contraseña después del primer login.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error durante el reset de la base de datos:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->info('🔧 Rehabilitando verificación de claves foráneas...');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            return 1;
        }
    }
}