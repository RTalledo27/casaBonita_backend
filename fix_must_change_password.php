<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CORRECCIÓN DE must_change_password ===" . PHP_EOL . PHP_EOL;

// 1. Cambiar el default de la columna en la base de datos
echo "1. Cambiando el valor por defecto de must_change_password a 1..." . PHP_EOL;
DB::statement("ALTER TABLE users MODIFY COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 1");
echo "✅ Valor por defecto actualizado" . PHP_EOL . PHP_EOL;

// 2. Actualizar usuarios que nunca han cambiado su contraseña
echo "2. Actualizando usuarios que nunca han cambiado su contraseña..." . PHP_EOL;
$updated = DB::table('users')
    ->whereNull('password_changed_at')
    ->update(['must_change_password' => 1]);

echo "✅ Actualizados {$updated} usuarios" . PHP_EOL . PHP_EOL;

// 3. Verificar resultado
echo "3. Verificación final:" . PHP_EOL;
$users = DB::table('users')
    ->select('user_id', 'username', 'must_change_password', 'password_changed_at')
    ->get();

foreach ($users as $user) {
    $status = $user->must_change_password ? '🔴 DEBE CAMBIAR' : '✅ OK';
    echo "  {$user->username}: {$status} (pwd_changed_at: " . ($user->password_changed_at ?? 'NULL') . ")" . PHP_EOL;
}

echo PHP_EOL . "=== CORRECCIÓN COMPLETADA ===" . PHP_EOL;
