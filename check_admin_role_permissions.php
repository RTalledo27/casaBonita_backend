<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;

echo "=== VERIFICACIÓN DE PERMISOS DEL ROL ADMINISTRADOR ===\n\n";

$role = Role::where('name', 'Administrador')->orWhere('name', 'admin')->first();

if (!$role) {
    echo "❌ Rol Administrador no encontrado\n";
    exit(1);
}

echo "✅ Rol encontrado: {$role->name}\n";
echo "ID: {$role->id}\n";
echo "Guard: {$role->guard_name}\n\n";

$permissions = $role->permissions;

echo "📋 Total de permisos: {$permissions->count()}\n\n";

if ($permissions->count() > 0) {
    echo "Permisos del rol '{$role->name}':\n";
    echo str_repeat('-', 60) . "\n";
    
    // Agrupar permisos por módulo
    $groupedPermissions = [];
    foreach ($permissions as $permission) {
        $parts = explode('.', $permission->name);
        $module = $parts[0] ?? 'other';
        
        if (!isset($groupedPermissions[$module])) {
            $groupedPermissions[$module] = [];
        }
        $groupedPermissions[$module][] = $permission->name;
    }
    
    foreach ($groupedPermissions as $module => $perms) {
        echo "\n🔹 Módulo: " . strtoupper($module) . " (" . count($perms) . " permisos)\n";
        foreach ($perms as $perm) {
            echo "   - {$perm}\n";
        }
    }
} else {
    echo "⚠️ Este rol NO tiene permisos asignados\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Resumen:\n";
echo "- Rol: {$role->name}\n";
echo "- Total permisos: {$permissions->count()}\n";

// Verificar permisos .access específicos
$accessPermissions = $permissions->filter(function($p) {
    return str_ends_with($p->name, '.access');
})->pluck('name')->toArray();

echo "- Permisos .access: " . count($accessPermissions) . "\n";
if (count($accessPermissions) > 0) {
    foreach ($accessPermissions as $ap) {
        echo "  ✓ {$ap}\n";
    }
}
