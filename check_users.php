<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Security\Models\User;

echo "\n=== VERIFICACIÓN DE USUARIOS EN LA BASE DE DATOS ===\n\n";

try {
    $totalUsers = User::count();
    echo "📊 Total de usuarios: {$totalUsers}\n\n";

    if ($totalUsers === 0) {
        echo "⚠️  No hay usuarios en la base de datos.\n";
        echo "💡 Ejecuta: php artisan db:seed --class=AdminUserSeeder\n\n";
        exit;
    }

    echo "👥 Lista de usuarios:\n";
    echo str_repeat("─", 80) . "\n";
    printf("%-5s | %-15s | %-30s | %-10s\n", "ID", "USERNAME", "EMAIL", "STATUS");
    echo str_repeat("─", 80) . "\n";

    $users = User::select('user_id', 'username', 'email', 'status')
        ->orderBy('user_id')
        ->get();

    foreach ($users as $user) {
        printf(
            "%-5s | %-15s | %-30s | %-10s\n",
            $user->user_id,
            $user->username ?? 'N/A',
            $user->email ?? 'N/A',
            $user->status ?? 'N/A'
        );
    }

    echo str_repeat("─", 80) . "\n\n";

    // Verificar usuario admin específicamente
    $admin = User::where('username', 'admin')->first();
    
    if ($admin) {
        echo "✅ Usuario 'admin' encontrado:\n";
        echo "   • ID: {$admin->user_id}\n";
        echo "   • Username: {$admin->username}\n";
        echo "   • Email: {$admin->email}\n";
        echo "   • Status: {$admin->status}\n";
        echo "   • Password hash: " . (empty($admin->password_hash) ? '❌ VACÍO' : '✓ Existe') . "\n\n";

        // Verificar si la contraseña 'admin123' coincide
        if (Hash::check('admin123', $admin->password_hash)) {
            echo "🔐 Password 'admin123' es CORRECTO ✅\n\n";
        } else {
            echo "⚠️  Password 'admin123' NO coincide ❌\n";
            echo "💡 Resetea el password con:\n";
            echo "   php artisan tinker\n";
            echo "   \$user = User::find({$admin->user_id});\n";
            echo "   \$user->password_hash = Hash::make('admin123');\n";
            echo "   \$user->save();\n\n";
        }
    } else {
        echo "⚠️  Usuario 'admin' NO encontrado\n";
        echo "💡 Crea el usuario admin con:\n";
        echo "   php artisan db:seed --class=AdminUserSeeder\n\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "💡 Verifica que:\n";
    echo "   1. La base de datos esté configurada correctamente en .env\n";
    echo "   2. Las migraciones estén ejecutadas: php artisan migrate\n";
    echo "   3. El servidor MySQL esté corriendo\n\n";
}

echo "\n=== FIN DE VERIFICACIÓN ===\n\n";
