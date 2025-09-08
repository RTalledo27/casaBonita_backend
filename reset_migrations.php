<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Iniciando limpieza de base de datos...\n";
    
    // Deshabilitar verificación de claves foráneas
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    
    // Obtener todas las tablas
    $tables = DB::select('SHOW TABLES');
    $database = config('database.connections.mysql.database');
    
    foreach ($tables as $table) {
        $tableName = $table->{"Tables_in_{$database}"};
        echo "Eliminando tabla: {$tableName}\n";
        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
    }
    
    // Rehabilitar verificación de claves foráneas
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    
    echo "Base de datos limpiada exitosamente.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}