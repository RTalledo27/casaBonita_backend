<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Permission;

echo "🔐 Asignando permiso 'inventory.create' a tu usuario\n";
echo "===================================================\n\n";

try {
    // Buscar tu usuario (el primero, o puedes cambiar el ID)
    echo "1. Buscando tu usuario...\n";
    $user = User::find(1); // Cambiar el ID si es necesario
    
    if (!$user) {
        echo "❌ No se encontró el usuario con ID 1\n";
        echo "Por favor ejecuta: php artisan tinker\n";
        echo "Luego: User::all()->pluck('id', 'email')\n";
        exit(1);
    }
    
    echo "✅ Usuario encontrado: {$user->email}\n\n";
    
    // Verificar si el permiso existe
    echo "2. Verificando permiso 'inventory.create'...\n";
    $permission = Permission::where('name', 'inventory.create')->first();
    
    if (!$permission) {
        echo "⚠️  Permiso no existe, creándolo...\n";
        $permission = Permission::create([
            'name' => 'inventory.create',
            'guard_name' => 'web'
        ]);
        echo "✅ Permiso creado\n\n";
    } else {
        echo "✅ Permiso ya existe\n\n";
    }
    
    // Verificar si el usuario ya tiene el permiso
    if ($user->hasPermissionTo('inventory.create')) {
        echo "✅ El usuario YA tiene el permiso 'inventory.create'\n";
    } else {
        echo "3. Asignando permiso al usuario...\n";
        $user->givePermissionTo('inventory.create');
        echo "✅ Permiso 'inventory.create' asignado exitosamente\n";
    }
    
    echo "\n🎉 ¡Listo! Ahora puedes importar lotes desde LogicWare\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
