<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Security\Models\User;

echo "🔍 Verificando permisos del admin...\n\n";

$admin = User::where('username', 'admin')->first();

if (!$admin) {
    echo "❌ Usuario admin no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$admin->username} (ID: {$admin->user_id})\n";
echo "📊 Total de permisos: " . $admin->getAllPermissions()->count() . "\n\n";

echo "🔐 Permisos de cortes de ventas:\n";
$cutsPerms = $admin->getAllPermissions()->filter(function($p) {
    return strpos($p->name, 'sales.cuts') === 0;
});

if ($cutsPerms->count() > 0) {
    foreach ($cutsPerms as $perm) {
        echo "   ✅ {$perm->name}\n";
    }
} else {
    echo "   ❌ NO TIENE PERMISOS DE CORTES\n";
}

echo "\n📝 Todos los permisos de sales:\n";
$salesPerms = $admin->getAllPermissions()->filter(function($p) {
    return strpos($p->name, 'sales.') === 0;
});

foreach ($salesPerms as $perm) {
    echo "   • {$perm->name}\n";
}
