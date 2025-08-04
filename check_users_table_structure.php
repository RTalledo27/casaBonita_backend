<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== ESTRUCTURA DE LA TABLA USERS ===\n\n";

try {
    // Obtener información de las columnas de la tabla users
    $columns = DB::select("SHOW COLUMNS FROM users");
    
    echo "Columnas de la tabla 'users':\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-15s %-10s %-10s %-15s %-20s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $column) {
        printf("%-20s %-15s %-10s %-10s %-15s %-20s\n", 
            $column->Field, 
            $column->Type, 
            $column->Null, 
            $column->Key, 
            $column->Default ?? 'NULL', 
            $column->Extra
        );
    }
    
    echo "\n";
    echo str_repeat("-", 80) . "\n";
    
    // Verificar si existen usuarios
    $userCount = DB::table('users')->count();
    echo "\nTotal de usuarios en la tabla: {$userCount}\n";
    
    if ($userCount > 0) {
        echo "\nPrimeros 3 usuarios (solo campos básicos):\n";
        $users = DB::table('users')
                   ->select('user_id', 'first_name', 'last_name', 'email', 'status')
                   ->limit(3)
                   ->get();
        
        foreach ($users as $user) {
            echo "- ID: {$user->user_id}, Nombre: {$user->first_name} {$user->last_name}, Email: {$user->email}, Estado: {$user->status}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL SCRIPT ===\n";