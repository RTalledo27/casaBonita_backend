<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class UserSessionController extends Controller
{
    /**
     * Obtener sesión activa del usuario
     */
    public function getActiveSession(Request $request): JsonResponse
    {
        $session = UserSession::getActiveSession($request->user()->user_id);

        if (!$session) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay sesión activa'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'started_at' => $session->started_at->toIso8601String(),
                'last_activity_at' => $session->last_activity_at->toIso8601String(),
                'session_type' => $session->session_type,
                'status' => $session->status,
                'current_duration' => $session->getCurrentDuration(),
                'formatted_duration' => $session->getFormattedDuration(),
            ],
            'message' => 'Sesión activa obtenida'
        ]);
    }

    /**
     * Iniciar sesión manualmente
     */
    public function startSession(Request $request): JsonResponse
    {
        $session = UserSession::startSession(
            $request->user()->user_id,
            UserSession::TYPE_MANUAL
        );

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'started_at' => $session->started_at->toIso8601String(),
                'session_type' => $session->session_type,
                'status' => $session->status,
            ],
            'message' => 'Sesión iniciada correctamente'
        ]);
    }

    /**
     * Finalizar sesión
     */
    public function endSession(Request $request): JsonResponse
    {
        $session = UserSession::getActiveSession($request->user()->user_id);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No hay sesión activa para finalizar'
            ], 404);
        }

        $session->endSession();

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'total_duration' => $session->total_duration,
                'formatted_duration' => $session->getFormattedDuration(),
            ],
            'message' => 'Sesión finalizada correctamente'
        ]);
    }

    /**
     * Pausar sesión
     */
    public function pauseSession(Request $request): JsonResponse
    {
        $session = UserSession::getActiveSession($request->user()->user_id);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No hay sesión activa para pausar'
            ], 404);
        }

        $session->pauseSession();

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'status' => $session->status,
                'current_duration' => $session->getCurrentDuration(),
            ],
            'message' => 'Sesión pausada correctamente'
        ]);
    }

    /**
     * Reanudar sesión
     */
    public function resumeSession(Request $request): JsonResponse
    {
        $session = UserSession::where('user_id', $request->user()->user_id)
            ->where('status', UserSession::STATUS_PAUSED)
            ->orderBy('started_at', 'desc')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No hay sesión pausada para reanudar'
            ], 404);
        }

        $session->resumeSession();

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_id,
                'status' => $session->status,
            ],
            'message' => 'Sesión reanudada correctamente'
        ]);
    }

    /**
     * Actualizar actividad (heartbeat)
     */
    public function updateActivity(Request $request): JsonResponse
    {
        $session = UserSession::getActiveSession($request->user()->user_id);

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No hay sesión activa'
            ], 404);
        }

        // Si está inactiva, auto-reanudar
        if ($session->isInactive()) {
            $session->resumeSession();
        } else {
            $session->updateActivity();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'last_activity_at' => $session->last_activity_at->toIso8601String(),
                'current_duration' => $session->getCurrentDuration(),
                'formatted_duration' => $session->getFormattedDuration(),
            ],
            'message' => 'Actividad actualizada'
        ]);
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:today,week,month,year',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $validated['period'] ?? 'today';
        $startDate = null;
        $endDate = null;

        // Determinar rango de fechas según el periodo
        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                $endDate = Carbon::tomorrow();
                break;
            case 'week':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
        }

        // Usar fechas personalizadas si se proporcionan
        if (isset($validated['start_date'])) {
            $startDate = Carbon::parse($validated['start_date']);
        }
        if (isset($validated['end_date'])) {
            $endDate = Carbon::parse($validated['end_date']);
        }

        $stats = UserSession::getUserStats($request->user()->user_id, $startDate, $endDate);

        // Obtener sesión activa actual
        $activeSession = UserSession::getActiveSession($request->user()->user_id);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'stats' => $stats,
                'active_session' => $activeSession ? [
                    'session_id' => $activeSession->session_id,
                    'started_at' => $activeSession->started_at->toIso8601String(),
                    'current_duration' => $activeSession->getCurrentDuration(),
                    'formatted_duration' => $activeSession->getFormattedDuration(),
                    'status' => $activeSession->status,
                ] : null,
            ],
            'message' => 'Estadísticas obtenidas correctamente'
        ]);
    }

    /**
     * Obtener historial de sesiones
     */
    public function getHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $sessions = UserSession::where('user_id', $request->user()->user_id)
            ->orderBy('started_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $sessions->map(function ($session) {
                return [
                    'session_id' => $session->session_id,
                    'started_at' => $session->started_at->toIso8601String(),
                    'ended_at' => $session->ended_at?->toIso8601String(),
                    'session_type' => $session->session_type,
                    'status' => $session->status,
                    'total_duration' => $session->total_duration,
                    'formatted_duration' => $session->getFormattedDuration(),
                    'started_at_human' => $session->started_at->diffForHumans(),
                ];
            }),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
            'message' => 'Historial obtenido correctamente'
        ]);
    }
}
