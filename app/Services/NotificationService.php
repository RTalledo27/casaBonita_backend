<?php

namespace App\Services;

use App\Models\Notification;
use App\Events\NotificationCreated;
use Modules\Security\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * Crear una nueva notificación
     *
     * @param array $data
     * @return Notification
     */
    public function create(array $data): Notification
    {
        // Validar que el usuario existe
        if (!User::find($data['user_id'])) {
            throw new \Exception("Usuario no encontrado");
        }

        // Valores por defecto
        $data = array_merge([
            'type' => 'info',
            'priority' => 'medium',
            'is_read' => false,
            'icon' => 'bell',
        ], $data);

        $notification = Notification::create($data);

        // Disparar evento para broadcasting en tiempo real
        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * Crear notificaciones para múltiples usuarios
     *
     * @param array $userIds
     * @param array $data
     * @return Collection
     */
    public function createForUsers(array $userIds, array $data): Collection
    {
        $notifications = collect();

        foreach ($userIds as $userId) {
            $notificationData = array_merge($data, ['user_id' => $userId]);
            $notifications->push($this->create($notificationData));
        }

        return $notifications;
    }

    /**
     * Obtener notificaciones de un usuario con paginación
     *
     * @param int $userId
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(int $userId, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = Notification::forUser($userId)->active();

        // Aplicar filtros
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (isset($filters['is_read'])) {
            if ($filters['is_read'] === true || $filters['is_read'] === 'true') {
                $query->read();
            } else {
                $query->unread();
            }
        }

        if (isset($filters['related_module'])) {
            $query->where('related_module', $filters['related_module']);
        }

        // Ordenar por prioridad y fecha
        return $query->orderedByPriority()
                     ->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }

    /**
     * Contar notificaciones no leídas de un usuario
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::forUser($userId)
                          ->unread()
                          ->active()
                          ->count();
    }

    /**
     * Marcar una notificación como leída
     *
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::forUser($userId)->find($notificationId);

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();
        return true;
    }

    /**
     * Marcar todas las notificaciones como leídas
     *
     * @param int $userId
     * @return int Cantidad de notificaciones actualizadas
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::forUser($userId)
                          ->unread()
                          ->update([
                              'is_read' => true,
                              'read_at' => now(),
                          ]);
    }

    /**
     * Eliminar una notificación
     *
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public function delete(int $notificationId, int $userId): bool
    {
        $notification = Notification::forUser($userId)->find($notificationId);

        if (!$notification) {
            return false;
        }

        return $notification->delete();
    }

    /**
     * Eliminar notificaciones expiradas
     *
     * @return int Cantidad eliminada
     */
    public function deleteExpired(): int
    {
        return Notification::where('expires_at', '<=', now())->delete();
    }

    /**
     * Obtener estadísticas de notificaciones del usuario
     *
     * @param int $userId
     * @return array
     */
    public function getStats(int $userId): array
    {
        $query = Notification::forUser($userId)->active();

        return [
            'total' => $query->count(),
            'unread' => (clone $query)->unread()->count(),
            'by_type' => [
                'info' => (clone $query)->ofType('info')->count(),
                'success' => (clone $query)->ofType('success')->count(),
                'warning' => (clone $query)->ofType('warning')->count(),
                'error' => (clone $query)->ofType('error')->count(),
            ],
            'by_priority' => [
                'high' => (clone $query)->byPriority('high')->count(),
                'medium' => (clone $query)->byPriority('medium')->count(),
                'low' => (clone $query)->byPriority('low')->count(),
            ],
        ];
    }

    /**
     * Crear notificación de pago recibido
     *
     * @param int $userId
     * @param float $amount
     * @param string $clientName
     * @param int $paymentId
     * @return Notification
     */
    public function notifyPaymentReceived(int $userId, float $amount, string $clientName, int $paymentId): Notification
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'success',
            'priority' => 'medium',
            'title' => 'Pago Recibido',
            'message' => "Se registró un pago de S/. " . number_format($amount, 2) . " del cliente {$clientName}",
            'related_module' => 'payments',
            'related_id' => $paymentId,
            'related_url' => "/collections-simplified/installments?payment={$paymentId}",
            'icon' => 'dollar-sign',
        ]);
    }

    /**
     * Crear notificación de contrato nuevo
     *
     * @param int $userId
     * @param string $contractNumber
     * @param int $contractId
     * @return Notification
     */
    public function notifyNewContract(int $userId, string $contractNumber, int $contractId): Notification
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'info',
            'priority' => 'high',
            'title' => 'Nuevo Contrato',
            'message' => "Se ha creado el contrato {$contractNumber}",
            'related_module' => 'contracts',
            'related_id' => $contractId,
            'related_url' => "/sales/contracts/{$contractId}",
            'icon' => 'file-text',
        ]);
    }

    /**
     * Crear notificación de cuota próxima a vencer
     *
     * @param int $userId
     * @param int $daysRemaining
     * @param string $clientName
     * @param int $installmentId
     * @return Notification
     */
    public function notifyInstallmentDueSoon(int $userId, int $daysRemaining, string $clientName, int $installmentId): Notification
    {
        return $this->create([
            'user_id' => $userId,
            'type' => 'warning',
            'priority' => 'high',
            'title' => 'Cuota Próxima a Vencer',
            'message' => "La cuota del cliente {$clientName} vence en {$daysRemaining} días",
            'related_module' => 'collections',
            'related_id' => $installmentId,
            'related_url' => "/collections-simplified/installments",
            'icon' => 'alert-triangle',
        ]);
    }
}
