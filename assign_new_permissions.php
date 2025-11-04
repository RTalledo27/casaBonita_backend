<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Security\Models\Role;
use Spatie\Permission\Models\Permission;

echo "Asignando nuevos permisos al rol Administrador...\n";

$admin = Role::where('name', 'Administrador')->first();
echo "Debug - Admin ID: " . ($admin->role_id ?? 'NULL') . "\n";

if (!$admin) {
    echo "No se encontró el rol Administrador\n";
    exit(1);
}

$newPermissions = [
    'finance.access',
    'accounting.access', 
    'audit.access',
    'service-desk.access'
];

foreach ($newPermissions as $permissionName) {
    if (!$admin->hasPermissionTo($permissionName)) {
        $admin->givePermissionTo($permissionName);
        echo " Permiso '{$permissionName}' asignado\n";
    } else {
        echo "ℹ  Permiso '{$permissionName}' ya estaba asignado\n";
    }
}

echo "\n Proceso completado. El rol admin ahora tiene todos los permisos .access\n";

// Mostrar todos los permisos .access del admin
echo "\n Permisos .access del admin:\n";
$accessPermissions = $admin->permissions()->where('name', 'like', '%.access')->pluck('name');
foreach ($accessPermissions as $perm) {
    echo "   - {$perm}\n";
}
