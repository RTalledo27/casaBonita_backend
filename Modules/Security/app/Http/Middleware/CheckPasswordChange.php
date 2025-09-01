<?php

namespace Modules\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Si no hay usuario autenticado, continuar
        if (!$user) {
            return $next($request);
        }
        
        // Rutas que están permitidas incluso si debe cambiar contraseña
        $allowedRoutes = [
            'api/v1/security/change-password',
            'api/v1/security/logout',
            'api/v1/security/me'
        ];
        
        $currentRoute = $request->path();
        
        // Si la ruta actual está en las permitidas, continuar
        foreach ($allowedRoutes as $allowedRoute) {
            if (str_contains($currentRoute, $allowedRoute)) {
                return $next($request);
            }
        }
        
        // Si el usuario debe cambiar su contraseña, bloquear acceso
        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Debe cambiar su contraseña antes de continuar.',
                'must_change_password' => true,
                'redirect_to' => '/change-password'
            ], 403);
        }
        
        return $next($request);
    }
}