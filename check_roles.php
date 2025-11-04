<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;

echo "Roles disponibles en la base de datos:\n\n";

$roles = Role::all();

if ($roles->isEmpty()) {
    echo "❌ No se encontraron roles\n";
} else {
    foreach ($roles as $role) {
        echo "- ID: {$role->id} | Nombre: {$role->name} | Guard: {$role->guard_name}\n";
        echo "  Permisos actuales: " . $role->permissions()->count() . "\n";
        echo "  Permisos .access: " . $role->permissions()->where('name', 'like', '%.access')->count() . "\n\n";
    }
}
