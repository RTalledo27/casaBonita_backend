<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// routes/web.php
Route::get('/health', function () {
    try {
        $checks = [
            'php_version' => PHP_VERSION,
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'mbstring' => extension_loaded('mbstring'),
                'xml' => extension_loaded('xml'),
                'tokenizer' => extension_loaded('tokenizer'),
                'json' => extension_loaded('json'),
            ],
            'storage_writable' => is_writable('/tmp'),
            'vendor_exists' => file_exists('vendor/autoload.php'),
            'app_exists' => file_exists('bootstrap/app.php'),
        ];

        return response()->json([
            'status' => 'healthy',
            'checks' => $checks
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});


// routes/web.php
Route::get('/debug-proxies', function (Request $request) {
    return response()->json([
        'app_version' => app()->version(),
        'trusted_proxies' => config('trustedhosts.proxies'),
        'trusted_headers' => config('trustedhosts.headers'),
        'current_ip' => $request->ip(),
        'client_ips' => $request->ips(),
        'forwarded_headers' => [
            'x-forwarded-for' => $request->header('x-forwarded-for'),
            'x-forwarded-host' => $request->header('x-forwarded-host'),
            'x-forwarded-proto' => $request->header('x-forwarded-proto'),
            'x-forwarded-port' => $request->header('x-forwarded-port'),
        ],
        'is_secure' => $request->secure(),
        'is_https' => $request->isSecure(),
    ]);
});
