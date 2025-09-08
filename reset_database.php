<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Limpiar todas las tablas
try {
    echo "Limpiando base de datos...\n";
    
    // Obtener todas las tablas
    $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
    
    if (!empty($tables)) {
        // Deshabilitar foreign key checks
        DB::statement('SET session_replication_role = replica;');
        
        // Eliminar todas las tablas
        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table->tablename} CASCADE");
            echo "Tabla {$table->tablename} eliminada\n";
        }
        
        // Rehabilitar foreign key checks
        DB::statement('SET session_replication_role = DEFAULT;');
    }
    
    echo "Base de datos limpiada exitosamente\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}