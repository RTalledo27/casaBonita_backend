<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Crear notificación de éxito
$notificationService->create([
    'user_id' => 1,
    'title' => '🎉 Pago Recibido',
    'message' => 'Se ha registrado un pago de S/. 5,000.00 del cliente Juan Pérez',
    'type' => 'success',
    'priority' => 'high',
    'icon' => 'check-circle',
    'related_module' => 'payments',
    'related_id' => 123,
]);

echo "✅ Notificación de ÉXITO creada\n";

// Crear notificación de información
$notificationService->create([
    'user_id' => 1,
    'title' => '📋 Nuevo Contrato',
    'message' => 'Se ha registrado el contrato #2024-001 para el lote A-15',
    'type' => 'info',
    'priority' => 'medium',
    'icon' => 'file-text',
    'related_module' => 'contracts',
    'related_id' => 456,
]);

echo "✅ Notificación de INFO creada\n";

// Crear notificación de advertencia
$notificationService->create([
    'user_id' => 1,
    'title' => '⚠️ Cuota por Vencer',
    'message' => 'La cuota del cliente María García vence en 3 días',
    'type' => 'warning',
    'priority' => 'high',
    'icon' => 'alert-triangle',
    'related_module' => 'installments',
    'related_id' => 789,
]);

echo "✅ Notificación de WARNING creada\n";

// Crear notificación de error
$notificationService->create([
    'user_id' => 1,
    'title' => '❌ Error en Importación',
    'message' => 'La importación de lotes falló. Revisa los errores en el log.',
    'type' => 'error',
    'priority' => 'high',
    'icon' => 'x-circle',
    'related_module' => 'imports',
    'related_id' => 999,
]);

echo "✅ Notificación de ERROR creada\n";

echo "\n🎉 ¡4 notificaciones de prueba creadas exitosamente!\n";
echo "👀 Revisa el frontend para verlas en acción.\n";
