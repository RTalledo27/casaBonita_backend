<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;
use Spatie\Permission\Models\Permission;

echo "🔐 Asignando permisos de cortes al usuario admin...\n\n";

// Crear los permisos si no existen
$cutPermissions = [
    'sales.cuts.view',
    'sales.cuts.create',
    'sales.cuts.close',
    'sales.cuts.review',
    'sales.cuts.notes',
    'sales.cuts.stats',
];

foreach ($cutPermissions as $perm) {
    Permission::firstOrCreate([
        'name' => $perm,
        'guard_name' => 'sanctum'
    ]);
    echo "✓ Permiso creado/verificado: $perm\n";
}

// Obtener usuario admin
$admin = User::where('username', 'admin')->first();

if (!$admin) {
    echo "❌ Usuario admin no encontrado\n";
    exit(1);
}

// Asignar permisos directamente al usuario
$admin->givePermissionTo($cutPermissions);

echo "\n✅ Permisos asignados exitosamente al usuario admin (ID: {$admin->user_id})\n";
echo "📋 Permisos de cortes:\n";

$assignedPermissions = $admin->permissions()->where('name', 'LIKE', 'sales.cuts%')->pluck('name');
foreach ($assignedPermissions as $perm) {
    echo "   • $perm\n";
}

echo "\n🚀 ¡Listo! Refresca el navegador (Ctrl+F5) para ver los cambios.\n";
