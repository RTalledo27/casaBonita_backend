<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use Modules\Security\Models\User;

class TestNotifications extends Command
{
    protected $signature = 'notifications:test';
    protected $description = 'Crear notificaciones de prueba';

    public function handle()
    {
        $user = User::first();

        if (!$user) {
            $this->error('No hay usuarios en la base de datos');
            return 1;
        }

        $service = new NotificationService();

        // Crear notificaciones de ejemplo
        $examples = [
            [
                'type' => 'success',
                'priority' => 'high',
                'title' => '¡Sistema de Notificaciones Activo!',
                'message' => 'El sistema de notificaciones está funcionando correctamente.',
                'icon' => 'check-circle',
            ],
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

        $this->info("Creando notificaciones para: {$user->name}");
        $this->newLine();

        foreach ($examples as $example) {
            $example['user_id'] = $user->id;
            $notification = $service->create($example);
            $this->line("✅ {$notification->title} - {$notification->type}");
        }

        $this->newLine();
        $stats = $service->getStats($user->id);
        
        $this->info('📊 Estadísticas:');
        $this->line("   Total: {$stats['total']}");
        $this->line("   No leídas: {$stats['unread']}");
        $this->line("   Success: {$stats['by_type']['success']}");
        $this->line("   Warning: {$stats['by_type']['warning']}");
        $this->line("   Info: {$stats['by_type']['info']}");

        return 0;
    }
}
