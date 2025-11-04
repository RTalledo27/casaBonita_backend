<?php

require 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use Modules\Security\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Inicializar aplicación
$kernel = app()->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "\n🧪 TEST: Sistema de Sincronización de Permisos\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Obtener usuario de prueba
$user = User::where('username', 'pprueba')->first();

if (!$user) {
    echo "❌ Usuario 'pprueba' no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$user->name} (ID: {$user->user_id})\n\n";

// 2. Verificar rol actual
$currentRole = $user->roles->first();
echo "📋 Rol actual: " . ($currentRole ? $currentRole->name : 'Sin rol') . "\n";

// 3. Mostrar permisos actuales
echo "🔐 Permisos actuales:\n";
$currentPermissions = $user->getAllPermissions()->pluck('name')->toArray();
echo "   Total: " . count($currentPermissions) . "\n";
if (count($currentPermissions) > 0) {
    echo "   Primeros 5: " . implode(', ', array_slice($currentPermissions, 0, 5)) . "\n";
}
echo "\n";

// 4. Simular cambio de permisos en el rol
if ($currentRole) {
    echo "🔄 Simulando cambio de permisos...\n";
    
    // Obtener permisos actuales del rol
    $rolePermissions = $currentRole->permissions->pluck('name')->toArray();
    echo "   Permisos del rol antes: " . count($rolePermissions) . "\n";
    
    // Agregar un permiso temporal
    $testPermission = Permission::where('name', 'crm.clients.export')->first();
    if ($testPermission && !in_array($testPermission->name, $rolePermissions)) {
        echo "   ➕ Agregando permiso: {$testPermission->name}\n";
        $currentRole->givePermissionTo($testPermission);
        
        // Limpiar caché
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        echo "   🧹 Caché limpiado\n";
        
        // Recargar usuario
        $user = User::find($user->user_id);
        $user->load(['roles.permissions', 'permissions']);
        
        $newPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        echo "   Total permisos después: " . count($newPermissions) . "\n";
        
        if (in_array($testPermission->name, $newPermissions)) {
            echo "   ✅ Permiso agregado correctamente (visible sin cerrar sesión)\n";
        } else {
            echo "   ❌ Permiso NO visible (problema de caché)\n";
        }
        
        // Remover el permiso para dejar todo como estaba
        echo "   ➖ Removiendo permiso de prueba...\n";
        $currentRole->revokePermissionTo($testPermission);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    } else {
        echo "   ⚠️  No se pudo encontrar permiso de prueba o ya existe\n";
    }
}

echo "\n";
echo "🏁 Test completado\n";
echo str_repeat("=", 60) . "\n";
