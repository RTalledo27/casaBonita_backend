<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Listar notificaciones del usuario autenticado
     *
     * GET /api/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $perPage = $request->input('per_page', 20);
        
        $filters = $request->only(['type', 'priority', 'is_read', 'related_module']);

        $notifications = $this->notificationService->getUserNotifications($userId, $perPage, $filters);

        return response()->json($notifications);
    }

    /**
     * Obtener contador de notificaciones no leídas
     *
     * GET /api/notifications/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        $userId = auth()->id();
        $count = $this->notificationService->getUnreadCount($userId);

        return response()->json([
            'unread_count' => $count
        ]);
    }

    /**
     * Marcar una notificación como leída
     *
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $userId = auth()->id();
        $success = $this->notificationService->markAsRead($id, $userId);

        if (!$success) {
            return response()->json([
                'message' => 'Notificación no encontrada'
            ], 404);
        }

        return response()->json([
            'message' => 'Notificación marcada como leída',
            'success' => true
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     *
     * POST /api/notifications/mark-all-read
     */
    public function markAllAsRead(): JsonResponse
    {
        $userId = auth()->id();
        $count = $this->notificationService->markAllAsRead($userId);

        return response()->json([
            'message' => "Se marcaron {$count} notificaciones como leídas",
            'count' => $count
        ]);
    }

    /**
     * Eliminar una notificación
     *
     * DELETE /api/notifications/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = auth()->id();
        $success = $this->notificationService->delete($id, $userId);

        if (!$success) {
            return response()->json([
                'message' => 'Notificación no encontrada'
            ], 404);
        }

        return response()->json([
            'message' => 'Notificación eliminada',
            'success' => true
        ]);
    }

    /**
     * Obtener estadísticas de notificaciones
     *
     * GET /api/notifications/stats
     */
    public function stats(): JsonResponse
    {
        $userId = auth()->id();
        $stats = $this->notificationService->getStats($userId);

        return response()->json($stats);
    }

    /**
     * Crear una notificación de prueba (solo para desarrollo)
     *
     * POST /api/notifications/test
     */
    public function createTest(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $notification = $this->notificationService->create([
            'user_id' => $userId,
            'type' => $request->input('type', 'info'),
            'priority' => $request->input('priority', 'medium'),
            'title' => $request->input('title', 'Notificación de Prueba'),
            'message' => $request->input('message', 'Esta es una notificación de prueba del sistema'),
            'icon' => $request->input('icon', 'bell'),
        ]);

        return response()->json([
            'message' => 'Notificación de prueba creada',
            'notification' => $notification
        ], 201);
    }
}
