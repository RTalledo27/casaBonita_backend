<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Entorno de SUNAT
    |--------------------------------------------------------------------------
    |
    | Define el entorno de conexión:
    | 'beta'       -> Entorno de pruebas (Sandbox)
    | 'produccion' -> Entorno real
    |
    */
    'environment' => env('SUNAT_ENVIRONMENT', 'beta'),

    /*
    |--------------------------------------------------------------------------
    | Credenciales y Empresa
    |--------------------------------------------------------------------------
    */
    'ruc' => env('SUNAT_RUC', '20613704214'),
    'razon_social' => env('SUNAT_RAZON_SOCIAL', 'CASA BONITA GRAU S.A.C.'),
    'nombre_comercial' => env('SUNAT_NOMBRE_COMERCIAL', 'CASA BONITA'),
    
    'ubigeo' => env('SUNAT_UBIGEO', '200101'),
    'direccion' => env('SUNAT_DIRECCION', 'JR. SAN CRISTOBAL NRO. 217 URB. SANTA ISABEL'),
    
    /*
    |--------------------------------------------------------------------------
    | Certificado Digital
    |--------------------------------------------------------------------------
    |
    | Ruta relativa a storage/
    */
    'cert_path' => env('SUNAT_CERT_PATH', 'certs/certificado.p12'),
    'cert_password' => env('SUNAT_CERT_PASSWORD', 'Casabonita25'),

    /*
    |--------------------------------------------------------------------------
    | Usuario SOL (Secundario)
    |--------------------------------------------------------------------------
    |
    | Necesario para el envío a producción
    */
    'sol_user' => env('SUNAT_SOL_USER', ''),
    'sol_pass' => env('SUNAT_SOL_PASS', ''),

    /*
    |--------------------------------------------------------------------------
    | Ruta wkhtmltopdf
    |--------------------------------------------------------------------------
    |
    | Ruta al ejecutable para generar PDFs
    */
    'wkhtmltopdf_path' => env('WKHTMLTOPDF_PATH', 'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe'),
];
