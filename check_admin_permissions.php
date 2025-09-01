<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

try {
    echo "🔍 Verificando usuario administrador...\n";
    
    // Buscar usuario admin
    $adminUser = User::where('email', 'admin@casabonita.com')->first();
    
    if (!$adminUser) {
        echo "❌ Usuario admin no encontrado\n";
        exit(1);
    }
    
    echo "✅ Usuario admin encontrado (ID: {$adminUser->id})\n";
    echo "📧 Email: {$adminUser->email}\n";
    echo "👤 Username: {$adminUser->username}\n";
    echo "📊 Status: {$adminUser->status}\n\n";
    
    // Verificar roles
    $roles = $adminUser->roles;
    echo "🎭 Roles asignados: " . $roles->count() . "\n";
    foreach ($roles as $role) {
        echo "   - {$role->name} (ID: {$role->role_id})\n";
    }
    echo "\n";
    
    // Verificar permisos específicos
    $requiredPermissions = [
        'hr.commission-verifications.view',
        'hr.commission-verifications.verify',
        'hr.commission-verifications.process',
        'hr.commission-verifications.reverse'
    ];
    
    echo "🔐 Verificando permisos específicos:\n";
    foreach ($requiredPermissions as $permission) {
        $hasPermission = $adminUser->hasPermissionTo($permission);
        $status = $hasPermission ? '✅' : '❌';
        echo "   {$status} {$permission}\n";
    }
    
    // Contar todos los permisos
    $allPermissions = $adminUser->getAllPermissions();
    echo "\n📋 Total de permisos: " . $allPermissions->count() . "\n";
    
    // Verificar si existe el permiso en la base de datos
    echo "\n🔍 Verificando existencia de permisos en BD:\n";
    foreach ($requiredPermissions as $permission) {
        $exists = Permission::where('name', $permission)->exists();
        $status = $exists ? '✅' : '❌';
        echo "   {$status} {$permission} existe en BD\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n✅ Verificación completada\n";