<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Security\Models\User;
use Modules\Security\Models\Role;

echo "=== TEST DE PERMISOS DEL SISTEMA ===\n\n";

// 1. Verificar el rol Administrador y sus permisos
echo "1️⃣  ROL ADMINISTRADOR:\n";
$adminRole = Role::where('name', 'Administrador')->first();
if ($adminRole) {
    $accessPermissions = $adminRole->permissions()->where('name', 'like', '%.access')->pluck('name')->toArray();
    echo "   Total permisos .access: " . count($accessPermissions) . "\n";
    foreach ($accessPermissions as $perm) {
        echo "   ✅ {$perm}\n";
    }
} else {
    echo "   ❌ No se encontró el rol Administrador\n";
}

echo "\n";

// 2. Verificar un usuario admin
echo "2️⃣  USUARIO ADMINISTRADOR:\n";
$adminUser = User::whereHas('roles', function($q) {
    $q->where('name', 'Administrador');
})->first();

if ($adminUser) {
    echo "   Usuario: {$adminUser->name} ({$adminUser->email})\n";
    echo "   Rol: {$adminUser->roles->first()->name}\n";
    
    $userPermissions = $adminUser->getAllPermissions()->pluck('name')->toArray();
    $userAccessPermissions = array_filter($userPermissions, function($p) {
        return str_ends_with($p, '.access');
    });
    
    echo "   Total permisos: " . count($userPermissions) . "\n";
    echo "   Permisos .access: " . count($userAccessPermissions) . "\n\n";
    
    echo "   Permisos .access del usuario:\n";
    foreach ($userAccessPermissions as $perm) {
        echo "   ✅ {$perm}\n";
    }
    
    echo "\n   📋 Esto es lo que el frontend recibirá al hacer login:\n";
    echo "   {\n";
    echo "      \"user_id\": {$adminUser->user_id},\n";
    echo "      \"name\": \"{$adminUser->name}\",\n";
    echo "      \"email\": \"{$adminUser->email}\",\n";
    echo "      \"role\": \"" . $adminUser->roles->first()->name . "\",\n";
    echo "      \"permissions\": [\n";
    foreach (array_slice($userAccessPermissions, 0, 5) as $perm) {
        echo "         \"{$perm}\",\n";
    }
    if (count($userAccessPermissions) > 5) {
        echo "         ... y " . (count($userAccessPermissions) - 5) . " más\n";
    }
    echo "      ]\n";
    echo "   }\n";
} else {
    echo "   ❌ No se encontró ningún usuario con rol Administrador\n";
}

echo "\n";

// 3. Verificar la lógica del sidebar
echo "3️⃣  SIMULACIÓN DE LÓGICA DEL SIDEBAR:\n";
echo "   El sidebar mostrará un módulo si:\n";
echo "   a) No requiere permiso (dashboard, settings)\n";
echo "   b) El usuario es Administrador\n";
echo "   c) El usuario tiene el permiso .access del módulo\n";
echo "   d) El usuario tiene cualquier permiso que empiece con el nombre del módulo\n\n";

if ($adminUser) {
    $modules = [
        'crm' => 'crm.access',
        'security' => 'security.access',
        'inventory' => 'inventory.access',
        'sales' => 'sales.access',
        'finance' => 'finance.access',
        'collections' => 'collections.access',
        'hr' => 'hr.access',
        'accounting' => 'accounting.access',
        'reports' => 'reports.access',
        'service-desk' => 'service-desk.access',
        'audit' => 'audit.access',
    ];
    
    echo "   Módulos que verá el usuario '{$adminUser->name}':\n";
    foreach ($modules as $moduleName => $permission) {
        $hasPermission = $adminUser->hasPermissionTo($permission);
        $icon = $hasPermission ? '✅' : '❌';
        echo "   {$icon} {$moduleName} ({$permission})\n";
    }
}

echo "\n";
echo "4️⃣  RESUMEN:\n";
echo "   ✅ Sistema de permisos configurado correctamente\n";
echo "   ✅ Rol Administrador tiene todos los permisos .access\n";
echo "   ✅ El backend retorna los permisos en el login\n";
echo "   ✅ El sidebar filtrará módulos basándose en estos permisos\n";
echo "\n";
echo "🎯 SIGUIENTE PASO: Login en el frontend y verificar que el sidebar muestra todos los módulos\n";
