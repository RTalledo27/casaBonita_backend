<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Obtener perfil del usuario autenticado
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Obtener el rol principal
        $role = $user->roles()->first()?->name ?? 'user';
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->user_id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'address' => $user->address ?? '',
                'role' => $role,
                'department' => $user->department,
                'position' => $user->position,
                'avatar' => $user->photo_profile,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString()
            ]
        ]);
    }

    /**
     * Actualizar perfil del usuario
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        // Guardar valores anteriores para comparación
        $oldValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'address' => $user->address
        ];

        $user->update($validator->validated());

        // Registrar actividad
        UserActivityLog::log(
            $user->user_id,
            UserActivityLog::ACTION_PROFILE_UPDATED,
            'Información de contacto modificada',
            [
                'changes' => array_diff_assoc($validator->validated(), $oldValues)
            ]
        );

        $role = $user->roles()->first()?->name ?? 'user';

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data' => [
                'id' => $user->user_id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'role' => $role,
                'department' => $user->department,
                'position' => $user->position
            ]
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ], 400);
        }

        // Actualizar contraseña
        $user->update([
            'password_hash' => Hash::make($request->new_password),
            'password_changed_at' => now()
        ]);

        // Registrar actividad
        UserActivityLog::log(
            $user->user_id,
            UserActivityLog::ACTION_PASSWORD_CHANGED,
            'Contraseña actualizada correctamente'
        );

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente'
        ]);
    }

    /**
     * Obtener preferencias de notificaciones
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $preferences = $user->preferences['notifications'] ?? [
            'email' => true,
            'push' => true,
            'system' => true,
            'weekly' => false
        ];

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Actualizar preferencias de notificaciones
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'boolean',
            'push' => 'boolean',
            'system' => 'boolean',
            'weekly' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Actualizar o crear preferencias (asumiendo que tienes una columna JSON 'preferences')
        $preferences = $user->preferences ?? [];
        $preferences['notifications'] = $validator->validated();
        
        $user->update(['preferences' => $preferences]);

        // Registrar actividad
        UserActivityLog::log(
            $user->user_id,
            UserActivityLog::ACTION_PREFERENCES_UPDATED,
            'Configuración de notificaciones modificada',
            ['preferences' => $validator->validated()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferencias guardadas correctamente'
        ]);
    }

    /**
     * Obtener actividad reciente del usuario
     */
    public function getActivity(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 20);
        $user = $request->user();

        $activities = UserActivityLog::getRecentActivity($user->user_id, $limit);

        $data = $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->getActionLabel(),
                'details' => $activity->details,
                'timestamp' => $activity->created_at->diffForHumans(),
                'created_at' => $activity->created_at->toISOString(),
                'metadata' => $activity->metadata
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
