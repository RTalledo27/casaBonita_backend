<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== DIAGNÓSTICO DE EMPLEADOS Y CONTRATOS ===\n\n";

// Verificar tablas de empleados en HumanResources
echo "Verificando tablas de empleados:\n";
$hrTables = ['hr_employees', 'human_resources_employees', 'employees'];
foreach ($hrTables as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "- Tabla '{$table}' existe con {$count} registros\n";
        
        if ($count > 0) {
            $columns = Schema::getColumnListing($table);
            echo "  Columnas: " . implode(', ', $columns) . "\n";
            
            // Mostrar algunos registros
            $records = DB::table($table)->take(3)->get();
            foreach ($records as $record) {
                echo "  Registro: " . json_encode($record) . "\n";
            }
        }
    } else {
        echo "- Tabla '{$table}' NO existe\n";
    }
}
echo "\n";

// Verificar usuarios que podrían ser empleados
echo "Usuarios en el sistema:\n";
$users = DB::table('users')->get(['user_id', 'username', 'first_name', 'last_name', 'position', 'department']);
foreach ($users as $user) {
    echo "ID: {$user->user_id}, Usuario: {$user->username}, Nombre: {$user->first_name} {$user->last_name}, Posición: {$user->position}\n";
}
echo "\n";

// Crear datos de prueba para resolver el problema
echo "=== SOLUCIÓN PROPUESTA ===\n";
echo "1. Los contratos no tienen advisor_id asignado\n";
echo "2. No hay empleados en la tabla employees\n";
echo "3. Hay 1 usuario que podría ser un asesor\n\n";

echo "Para resolver el problema de comisiones, necesitamos:\n";
echo "a) Crear empleados en la tabla correcta\n";
echo "b) Asignar advisor_id a los contratos\n";
echo "c) Cambiar las fechas de algunos contratos a junio 2024 para pruebas\n\n";

echo "=== DIAGNÓSTICO COMPLETADO ===\n";