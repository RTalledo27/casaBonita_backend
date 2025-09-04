<?php

namespace Modules\Collections\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class CollectionsPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): ResponseAlias
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
                'data' => null
            ], 401);
        }

        // Verificar si el usuario tiene el permiso específico
        if (!$user->can($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción',
                'data' => [
                    'required_permission' => $permission,
                    'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray()
                ]
            ], 403);
        }

        return $next($request);
    }

    /**
     * Verifica múltiples permisos (el usuario debe tener al menos uno)
     */
    public function handleMultiple(Request $request, Closure $next, string ...$permissions): ResponseAlias
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
                'data' => null
            ], 401);
        }

        // Verificar si el usuario tiene al menos uno de los permisos
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción',
                'data' => [
                    'required_permissions' => $permissions,
                    'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray()
                ]
            ], 403);
        }

        return $next($request);
    }
}