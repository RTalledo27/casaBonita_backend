<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ambiente SUNAT
    |--------------------------------------------------------------------------
    | beta: Ambiente de pruebas (no requiere certificado real)
    | production: Ambiente de producción (requiere certificado)
    */
    'environment' => env('SUNAT_ENVIRONMENT', 'beta'),

    /*
    |--------------------------------------------------------------------------
    | Datos de la Empresa
    |--------------------------------------------------------------------------
    */
    'ruc' => env('SUNAT_RUC', '20613704214'),
    'razon_social' => env('SUNAT_RAZON_SOCIAL', 'CASA BONITA GRAU S.A.C.'),
    'nombre_comercial' => env('SUNAT_NOMBRE_COMERCIAL', 'CASA BONITA'),
    'direccion' => env('SUNAT_DIRECCION', 'JR. SAN CRISTOBAL NRO. 217 URB. SANTA ISABEL'),
    'ubigeo' => env('SUNAT_UBIGEO', '200101'),
    'departamento' => env('SUNAT_DEPARTAMENTO', 'PIURA'),
    'provincia' => env('SUNAT_PROVINCIA', 'PIURA'),
    'distrito' => env('SUNAT_DISTRITO', 'PIURA'),

    /*
    |--------------------------------------------------------------------------
    | Credenciales API SUNAT
    |--------------------------------------------------------------------------
    */
    'api_client_id' => env('SUNAT_API_CLIENT_ID', 'b029f530-c73c-4f48-aa05-c0f87b7a4223'),
    'api_client_secret' => env('SUNAT_API_CLIENT_SECRET', 'VLC7SZ5d0tdC8o5btKJlqg=='),

    /*
    |--------------------------------------------------------------------------
    | Certificado Digital
    |--------------------------------------------------------------------------
    */
    'cert_path' => env('SUNAT_CERT_PATH', 'certs/certificado.p12'),
    'cert_password' => env('SUNAT_CERT_PASSWORD', 'Casabonita25'),

    /*
    |--------------------------------------------------------------------------
    | Configuración de Impuestos
    |--------------------------------------------------------------------------
    */
    'igv_rate' => 18.00,

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'xml_path' => 'sunat/xml',
        'pdf_path' => 'sunat/pdf',
        'cdr_path' => 'sunat/cdr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Emisión Automática
    |--------------------------------------------------------------------------
    */
    'auto_emit_on_payment' => env('SUNAT_AUTO_EMIT_ON_PAYMENT', true),
    'default_document_type' => '03', // Boleta por defecto
];
