<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICACIÓN DE CAMPO must_change_password ===" . PHP_EOL . PHP_EOL;

$users = DB::table('users')
    ->select('user_id', 'username', 'must_change_password', 'password_changed_at', 'last_login_at')
    ->get();

echo "Total usuarios: " . $users->count() . PHP_EOL . PHP_EOL;

foreach ($users as $user) {
    echo "Usuario: {$user->username}" . PHP_EOL;
    echo "  - user_id: {$user->user_id}" . PHP_EOL;
    echo "  - must_change_password: " . ($user->must_change_password ? 'SÍ (1)' : 'NO (0)') . PHP_EOL;
    echo "  - password_changed_at: " . ($user->password_changed_at ?? 'NULL') . PHP_EOL;
    echo "  - last_login_at: " . ($user->last_login_at ?? 'NULL') . PHP_EOL;
    echo PHP_EOL;
}

echo "=== Verificando estructura de la tabla ===" . PHP_EOL;
$columns = DB::select("SHOW COLUMNS FROM users LIKE 'must_change_password'");
if (empty($columns)) {
    echo "❌ El campo 'must_change_password' NO EXISTE en la tabla users" . PHP_EOL;
} else {
    echo "✅ El campo 'must_change_password' existe:" . PHP_EOL;
    print_r($columns[0]);
}
