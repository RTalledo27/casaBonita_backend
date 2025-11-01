<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Modules\Security\Models\User;

echo "🔍 Verificando usuarios que necesitan cambiar contraseña\n";
echo "===========================================================\n\n";

$users = User::all();

echo "Total de usuarios: " . $users->count() . "\n\n";

$needsChange = [];

foreach ($users as $user) {
    $status = $user->must_change_password ? '⚠️ DEBE CAMBIAR' : '✅ OK';
    $lastChange = $user->password_changed_at ? $user->password_changed_at->format('Y-m-d H:i:s') : 'Nunca';
    
    echo "Usuario: {$user->username}\n";
    echo "  Estado: {$status}\n";
    echo "  Última cambio: {$lastChange}\n";
    echo "  Último login: " . ($user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Nunca') . "\n";
    
    if ($user->must_change_password) {
        $needsChange[] = $user->username;
    }
    
    echo "\n";
}

if (count($needsChange) > 0) {
    echo "⚠️ Usuarios que deben cambiar contraseña:\n";
    foreach ($needsChange as $username) {
        echo "  - {$username}\n";
    }
} else {
    echo "✅ Ningún usuario necesita cambiar contraseña\n";
}

echo "\n===========================================================\n";
echo "💡 Para forzar cambio de contraseña en un usuario:\n";
echo "   UPDATE users SET must_change_password = 1 WHERE username = 'admin';\n";
