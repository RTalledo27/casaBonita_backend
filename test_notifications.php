<?php

use App\Services\NotificationService;
use App\Models\User;

// Obtener el primer usuario
$user = User::first();

if (!$user) {
    echo "No hay usuarios en la base de datos\n";
    exit(1);
}

// Crear el servicio
$service = new NotificationService();

// Crear notificación de prueba
$notification = $service->create([
    'user_id' => $user->id,
    'type' => 'success',
    'priority' => 'high',
    'title' => '¡Sistema de Notificaciones Activo!',
    'message' => 'El sistema de notificaciones en tiempo real está funcionando correctamente. Esta es una notificación de prueba.',
    'icon' => 'check-circle',
]);

echo "✅ Notificación creada exitosamente!\n";
echo "   ID: {$notification->id}\n";
echo "   Usuario: {$user->name}\n";
echo "   Título: {$notification->title}\n";
echo "   Tipo: {$notification->type}\n";
echo "   Prioridad: {$notification->priority}\n";
echo "\n";

// Crear más notificaciones de ejemplo
$examples = [
    [
        'type' => 'info',
        'priority' => 'medium',
        'title' => 'Nuevo Mensaje',
        'message' => 'Tienes un nuevo mensaje en tu bandeja',
        'icon' => 'message-circle',
    ],
    [
        'type' => 'warning',
        'priority' => 'high',
        'title' => 'Cuota Próxima a Vencer',
        'message' => 'El cliente Juan Pérez tiene una cuota que vence en 3 días',
        'icon' => 'alert-triangle',
        'related_module' => 'collections',
    ],
    [
        'type' => 'success',
        'priority' => 'medium',
        'title' => 'Pago Recibido',
        'message' => 'Se registró un pago de S/. 5,000.00',
        'icon' => 'dollar-sign',
        'related_module' => 'payments',
    ],
];

foreach ($examples as $example) {
    $example['user_id'] = $user->id;
    $service->create($example);
}

echo "✅ Se crearon 4 notificaciones de ejemplo\n";
echo "\n";

// Obtener estadísticas
$stats = $service->getStats($user->id);
echo "📊 Estadísticas:\n";
echo "   Total: {$stats['total']}\n";
echo "   No leídas: {$stats['unread']}\n";
echo "   Por tipo:\n";
echo "     - Info: {$stats['by_type']['info']}\n";
echo "     - Success: {$stats['by_type']['success']}\n";
echo "     - Warning: {$stats['by_type']['warning']}\n";
echo "     - Error: {$stats['by_type']['error']}\n";
