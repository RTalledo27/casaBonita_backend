<?php
// config/cors.php
return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    // Métodos permitidos
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // ORÍGENES: NO uses "*" si hay credenciales
    'allowed_origins' => [
        'https://app.casabonita.pe',
        'http://localhost:4200',
        'http://127.0.0.1:4200',
    ],

    // Si quieres permitir cualquier subdominio *.casabonita.pe, usa el patrón (sin duplicarlo)
    'allowed_origins_patterns' => [
        '#^https://.*\.casabonita\.pe$#'
    ],

    // Encabezados: permite todos para simplificar
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // SOLO pon true si usas cookies/Sanctum (SPA con sesión).
    // Si usas Bearer token en Authorization, déjalo en false.
    'supports_credentials' => true,
];
