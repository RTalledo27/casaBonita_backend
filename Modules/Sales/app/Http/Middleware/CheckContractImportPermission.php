<?php

namespace Modules\Sales\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckContractImportPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Verificar si el usuario está autenticado
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        // Verificar permisos específicos para importación de contratos
        $allowedRoles = [
            'administrador',
            'gerente_ventas',
            'supervisor_ventas',
            'coordinador_ventas'
        ];

        $allowedEmployeeTypes = [
            'administrador',
            'gerente',
            'supervisor',
            'coordinador'
        ];

        // Si el usuario tiene un rol permitido
        if ($user->hasAnyRole($allowedRoles)) {
            return $next($request);
        }

        // Si es un empleado con tipo permitido
        if ($user->employee && in_array($user->employee->employee_type, $allowedEmployeeTypes)) {
            return $next($request);
        }

        // Si tiene permisos específicos
        if ($user->can('import_contracts') || $user->can('manage_sales')) {
            return $next($request);
        }

        // Verificar si es administrador del sistema
        if ($user->hasRole('super_admin') || $user->is_admin) {
            return $next($request);
        }

        // Si no tiene permisos, denegar acceso
        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para importar contratos. Contacta al administrador.',
            'required_permissions' => [
                'roles' => $allowedRoles,
                'permissions' => ['import_contracts', 'manage_sales']
            ]
        ], 403);
    }
}