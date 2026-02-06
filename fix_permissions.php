<?php

use Modules\Security\Models\Permission;
use Modules\Security\Models\Role;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    DB::beginTransaction();

    echo "1. Buscando o creando permiso 'billing.access'...\n";
    $perm = Permission::firstOrCreate(
        ['name' => 'billing.access', 'guard_name' => 'web'],
        ['label' => 'Access Billing', 'description' => 'Access Billing Module']
    );
    echo "   Permiso ID: " . $perm->id . "\n";

    echo "2. Buscando rol 'Super Admin'...\n";
    $role = Role::where('name', 'Super Admin')->first();
    
    if ($role) {
        echo "   Rol encontrado ID: " . $role->id . "\n";
        
        if (!$role->hasPermissionTo('billing.access')) {
            $role->givePermissionTo('billing.access');
            echo "   ✅ Permiso asignado exitosamente.\n";
        } else {
            echo "   ℹ️ El rol ya tenía el permiso.\n";
        }
    } else {
        echo "   ❌ ERROR: No se encontró el rol 'Super Admin'.\n";
    }

    DB::commit();
    echo "\n --- PROCESO COMPLETADO ---\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERROR FATAL: " . $e->getMessage() . "\n";
}
