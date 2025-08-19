<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Verificar usuario administrador
$user = Modules\Security\Models\User::where('email', 'admin@casabonita.com')->first();

if ($user) {
    echo "Usuario encontrado: {$user->name} ({$user->email})\n";
    echo "ID del usuario: {$user->user_id}\n";
    
    // Verificar roles
    $roles = $user->roles;
    echo "Roles asignados: " . $roles->count() . "\n";
    foreach ($roles as $role) {
        echo "  - {$role->name} (ID: {$role->role_id})\n";
    }
    
    // Verificar permisos
    $permissions = $user->getAllPermissions();
    echo "Total de permisos: " . $permissions->count() . "\n";
    
    // Mostrar permisos que contienen 'collections'
    echo "\nPermisos relacionados con collections:\n";
    $collectionsPermissions = Spatie\Permission\Models\Permission::where('name', 'like', '%collections%')->get();
    foreach ($collectionsPermissions as $perm) {
        $hasPermission = $user->can($perm->name) ? 'SÍ' : 'NO';
        echo "  - {$perm->name}: {$hasPermission}\n";
    }
    
    // Verificar algunos permisos específicos del frontend
    $testPermissions = [
        'collections.view',
        'collections.index', 
        'sales.contracts.view',
        'security.users.index'
    ];
    
    echo "\nVerificación de permisos específicos:\n";
    foreach ($testPermissions as $perm) {
        $hasPermission = $user->can($perm) ? 'SÍ' : 'NO';
        echo "  - {$perm}: {$hasPermission}\n";
    }
    
} else {
    echo "Usuario administrador no encontrado\n";
}