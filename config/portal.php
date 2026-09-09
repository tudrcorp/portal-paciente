<?php

/*
|--------------------------------------------------------------------------
| Portal del paciente — origen de datos
|--------------------------------------------------------------------------
|
| DATA_SOURCE controla la contingencia:
| - database: Eloquent / DB directa (comportamiento actual)
| - api: consume portal-paciente-api (recomendado en producción)
|
*/

return [

    // "database" | "api"
    'data_source' => env('DATA_SOURCE', 'database'),

    'api' => [
        // URL base del API Node (sin slash final).
        'base_url' => rtrim((string) env('PORTAL_PACIENTE_API_URL', 'http://localhost:4100'), '/'),

        // Timeouts cortos para no congelar el portal bajo carga.
        'timeout_seconds' => (float) env('PORTAL_PACIENTE_API_TIMEOUT', 8),
        'connect_timeout_seconds' => (float) env('PORTAL_PACIENTE_API_CONNECT_TIMEOUT', 3),

        // Reintentos solo en errores de red (no en 4xx).
        'retry_times' => (int) env('PORTAL_PACIENTE_API_RETRY', 1),
        'retry_sleep_ms' => (int) env('PORTAL_PACIENTE_API_RETRY_SLEEP_MS', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integracorp (documentos clínicos)
    |--------------------------------------------------------------------------
    |
    | En producción los PDF de telemedicina viven en Integracorp:
    | https://integracorp.tudrgroup.com/storage/telemedicina-doc/...
    |
    | Orden de resolución:
    | 1) disco public del portal
    | 2) CLINICAL_STORAGE_ROOT (NFS/volumen compartido)
    | 3) URL pública INTEGRACORP_URL/storage/...
    |
    */
    'integracorp' => [
        // Producción: https://integracorp.tudrgroup.com
        'url' => rtrim((string) (env('INTEGRACORP_URL') ?: 'https://integracorp.tudrgroup.com'), '/'),

        // Override opcional; por defecto {INTEGRACORP_URL}/storage
        'storage_base_url' => rtrim((string) (env('INTEGRACORP_STORAGE_BASE_URL') ?: ''), '/'),

        // Ruta absoluta local/NFS al storage/app/public de Integracorp (modo database).
        'clinical_storage_root' => env('CLINICAL_STORAGE_ROOT') ?: null,

        'download_timeout_seconds' => (float) (env('INTEGRACORP_DOWNLOAD_TIMEOUT') ?: 30),
        'connect_timeout_seconds' => (float) (env('INTEGRACORP_CONNECT_TIMEOUT') ?: 5),
    ],

    // Alias legacy (mismo valor que integracorp.clinical_storage_root).
    'clinical_storage_root' => env('CLINICAL_STORAGE_ROOT') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Soporte / recuperación de clave
    |--------------------------------------------------------------------------
    |
    | MediChat: destino fijo del CTA “Olvidé mi clave” en el login.
    |
    */
    'support_whatsapp_phone' => env('PORTAL_SUPPORT_WHATSAPP_PHONE', '+584242132112'),
    'support_whatsapp_name' => env('PORTAL_SUPPORT_WHATSAPP_NAME', 'MediChat'),

];
