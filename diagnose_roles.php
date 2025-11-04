<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ESTRUCTURA DE LA TABLA ROLES ===\n\n";

$result = DB::select('DESCRIBE roles');
foreach ($result as $column) {
    echo sprintf(
        "%-20s | %-15s | Null: %-3s | Key: %-3s | Extra: %s\n",
        $column->Field,
        $column->Type,
        $column->Null,
        $column->Key,
        $column->Extra
    );
}

echo "\n=== DATOS EN LA TABLA ROLES ===\n\n";
$roles = DB::select('SELECT * FROM roles');
foreach ($roles as $role) {
    echo "ID: " . var_export($role->id ?? null, true) . "\n";
    echo "Name: {$role->name}\n";
    echo "Guard: {$role->guard_name}\n";
    echo "Created: " . ($role->created_at ?? 'NULL') . "\n";
    echo "Updated: " . ($role->updated_at ?? 'NULL') . "\n";
    echo "---\n";
}

echo "\n=== DIAGNÓSTICO ===\n\n";

if (empty($roles)) {
    echo "❌ No hay roles en la tabla\n";
} else {
    $hasNullId = false;
    foreach ($roles as $role) {
        if (!isset($role->id) || $role->id === null) {
            $hasNullId = true;
            echo "❌ El rol '{$role->name}' tiene ID NULL - esto es un problema grave\n";
        }
    }
    
    if (!$hasNullId) {
        echo "✅ Todos los roles tienen ID válido\n";
    } else {
        echo "\n⚠️  SOLUCIÓN: Necesitamos recrear el rol con ID válido\n";
    }
}
