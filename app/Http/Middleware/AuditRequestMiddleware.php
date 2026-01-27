<?php

namespace App\Http\Middleware;

use App\Models\SystemActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        try {
            if (!filter_var(env('AUDIT_HTTP_ENABLED', true), FILTER_VALIDATE_BOOL)) {
                return $response;
            }

            $user = $request->user();
            $userId = ($user && isset($user->user_id)) ? (int) $user->user_id : null;

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $route = $request->route();

            $method = strtoupper($request->getMethod());
            $path = '/' . ltrim($request->path(), '/');
            $status = $response->getStatusCode();

            $metadata = [
                'method' => $method,
                'path' => $path,
                'full_url' => $request->fullUrl(),
                'status' => $status,
                'duration_ms' => $durationMs,
                'route_name' => $route?->getName(),
                'action' => $route?->getActionName(),
                'query' => $request->query(),
                'body' => $this->sanitizeBody($request),
            ];

            $details = $method . ' ' . $path . ' (' . $status . ')';

            SystemActivityLog::create([
                'user_id' => $userId,
                'actor_identifier' => $this->actorIdentifier($request),
                'action' => SystemActivityLog::ACTION_HTTP_REQUEST,
                'details' => $details,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
        }

        return $response;
    }

    private function actorIdentifier(Request $request): ?string
    {
        $username = $request->input('username');
        if (is_string($username) && trim($username) !== '') {
            return trim($username);
        }

        $email = $request->input('email');
        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        return null;
    }

    private function sanitizeBody(Request $request): array
    {
        $payload = $request->except([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
        ]);

        foreach ($payload as $key => $value) {
            if (is_string($key) && str_contains(strtolower($key), 'password')) {
                $payload[$key] = '[redacted]';
            }
        }

        $files = $request->allFiles();
        if (!empty($files)) {
            $payload['_files'] = array_keys($files);
        }

        return $payload;
    }
}
