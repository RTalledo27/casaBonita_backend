<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Servicio para integración con API de LOGICWARE CRM
 * 
 * Este servicio maneja la autenticación y las peticiones al API externa
 * según la documentación proporcionada en el Manual de Integración API LOGICWARE CRM
 */
class LogicwareApiService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $subdomain;
    protected int $timeout;
    protected ?string $bearerToken = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.logicware.base_url', 'https://gw.logicwareperu.com'), '/');
        $this->apiKey = config('services.logicware.api_key');
        $this->subdomain = config('services.logicware.subdomain', 'casabonita');
        $this->timeout = config('services.logicware.timeout', 30);
        
        Log::info('[LogicwareAPI] Servicio inicializado', [
            'base_url' => $this->baseUrl,
            'subdomain' => $this->subdomain,
            'has_api_key' => !empty($this->apiKey)
        ]);
    }

    /**
     * Generar Bearer Token usando el API Key y Subdomain
     * Endpoint: POST /auth/external/token
     * Headers: X-API-Key, X-Subdomain, Accept
     * 
     * @param bool $forceRefresh Si es true, invalida el caché y genera un token nuevo
     * @return string Bearer Token
     * @throws Exception
     */
    public function generateToken(bool $forceRefresh = false): string
    {
        try {
            $this->validateApiKey();

            $cacheKey = "logicware_bearer_token_{$this->subdomain}";
            
            // Si no se fuerza la renovación y hay token en caché válido, usarlo
            if (!$forceRefresh) {
                $cachedToken = Cache::get($cacheKey);
                if (!empty($cachedToken)) {
                    // Token existe en caché, reutilizarlo sin hacer petición
                    $this->bearerToken = $cachedToken;
                    return $this->bearerToken;
                }
            }

            $url = "{$this->baseUrl}/auth/external/token";
            
            Log::info('[LogicwareAPI] Generando nuevo Bearer Token', [
                'url' => $url,
                'subdomain' => $this->subdomain,
                'forced_refresh' => $forceRefresh
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Subdomain' => $this->subdomain,
                    'Accept' => 'application/json'
                ])
                ->post($url);

            if (!$response->successful()) {
                Log::error('[LogicwareAPI] Error al generar token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                throw new Exception("Error al generar token: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            
            // La respuesta viene en formato: {"succeeded": true, "data": {"accessToken": "..."}}
            if (empty($data['data']['accessToken'])) {
                Log::error('[LogicwareAPI] Estructura de respuesta inesperada', [
                    'response' => $data
                ]);
                throw new Exception("No se recibió accessToken en la respuesta");
            }

            $this->bearerToken = $data['data']['accessToken'];
            
            // Guardar en caché por 23 horas (los tokens duran 24h)
            // Se verifica cada 5 minutos pero solo se renueva si el caché expiró
            Cache::put($cacheKey, $this->bearerToken, now()->addHours(23));

            Log::info('[LogicwareAPI] Bearer Token generado y guardado en caché', [
                'valid_until' => now()->addHours(23)->format('Y-m-d H:i:s')
            ]);

            return $this->bearerToken;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error al generar token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener headers de autenticación con Bearer Token
     * 
     * @return array
     * @throws Exception
     */
    protected function getAuthHeaders(): array
    {
        // Generar token si no existe (usa caché automáticamente)
        if (empty($this->bearerToken)) {
            $this->generateToken();
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'X-Subdomain' => $this->subdomain,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        
        Log::info('[LogicwareAPI] 📤 Headers preparados', [
            'has_authorization' => isset($headers['Authorization']),
            'token_preview' => substr($this->bearerToken ?? '', 0, 30) . '...',
            'subdomain' => $this->subdomain
        ]);
        
        return $headers;
    }

    /**
     * Verificar que el API Key esté configurado
     * 
     * @throws Exception
     */
    protected function validateApiKey(): void
    {
        if (empty($this->apiKey) || $this->apiKey === null) {
            throw new Exception('API Key no configurado. Verifica que la variable LOGICWARE_API_KEY esté definida en el archivo .env');
        }
    }

    /**
     * Obtener stock completo de unidades inmobiliarias
     * Endpoint: GET /external/units/stock/full
     * Autenticación: Bearer Token
     * Límite: 4 solicitudes/día ⚠️
     * 
     * IMPORTANTE: Esta función usa caché para evitar consumir el límite diario
     * 
     * @param array $filters Filtros opcionales
     * @param bool $forceRefresh Forzar consulta real (consume una de las 4 consultas diarias)
     * @return array
     * @throws Exception
     */
    public function getProperties(array $filters = [], bool $forceRefresh = false): array
    {
        try {
            $this->validateApiKey();

            // Clave de caché única por subdomain
            $cacheKey = "logicware_stock_{$this->subdomain}";
            $cacheDuration = now()->addHours(6); // 6 horas de caché

            // Si NO se fuerza refresh, intentar obtener del caché
            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                
                Log::info('[LogicwareAPI] ✅ Datos obtenidos del CACHÉ (no consume consulta diaria)', [
                    'cache_key' => $cacheKey,
                    'total' => isset($cachedData['data']) ? count($cachedData['data']) : 0,
                    'cached_at' => $cachedData['cached_at'] ?? 'unknown'
                ]);

                return $cachedData;
            }

            // Consultar API real (⚠️ CONSUME 1 DE 4 CONSULTAS DIARIAS)
            $url = "{$this->baseUrl}/external/units/stock/full";
            
            // Incrementar contador de consultas diarias
            $this->incrementDailyRequestCounter();
            
            Log::warning('[LogicwareAPI] ⚠️ CONSULTANDO API REAL (consume 1 de 4 consultas diarias)', [
                'url' => $url,
                'filters' => $filters,
                'subdomain' => $this->subdomain,
                'force_refresh' => $forceRefresh,
                'daily_requests' => $this->getDailyRequestCount()
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get($url, $filters);

            if (!$response->successful()) {
                // Si es error 429 (rate limit), usar datos mock
                if ($response->status() === 429) {
                    Log::warning('[LogicwareAPI] ⚠️ Rate limit alcanzado, usando datos MOCK para desarrollo', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    
                    return $this->getMockProperties();
                }
                
                Log::error('[LogicwareAPI] Error en respuesta', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                throw new Exception("Error al obtener unidades: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            
            // Agregar metadata de caché
            $data['cached_at'] = now()->toDateTimeString();
            $data['cache_expires_at'] = $cacheDuration->toDateTimeString();

            // Guardar en caché solo si no es muy grande (evitar error max_allowed_packet)
            $dataSize = strlen(json_encode($data));
            $maxCacheSize = 1048576; // 1MB
            
            if ($dataSize < $maxCacheSize) {
                Cache::put($cacheKey, $data, $cacheDuration);
                Log::info('[LogicwareAPI] ✅ Unidades obtenidas del API y guardadas en caché', [
                    'total' => isset($data['data']) ? count($data['data']) : 0,
                    'cache_duration' => '6 horas',
                    'data_size' => number_format($dataSize / 1024, 2) . ' KB',
                    'daily_requests_used' => $this->getDailyRequestCount()
                ]);
            } else {
                Log::warning('[LogicwareAPI] ⚠️ Respuesta demasiado grande para cachear', [
                    'total' => isset($data['data']) ? count($data['data']) : 0,
                    'data_size' => number_format($dataSize / 1024, 2) . ' KB',
                    'max_cache_size' => number_format($maxCacheSize / 1024, 2) . ' KB',
                    'note' => 'Los datos NO se guardaron en caché para evitar errores de MySQL'
                ]);
            }

            return $data;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error al obtener unidades', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener contador de consultas diarias realizadas
     * 
     * @return int
     */
    public function getDailyRequestCount(): int
    {
        $counterKey = "logicware_daily_requests_" . now()->format('Y-m-d');
        return Cache::get($counterKey, 0);
    }

    /**
     * Incrementar contador de consultas diarias
     * 
     * @return void
     */
    protected function incrementDailyRequestCounter(): void
    {
        $counterKey = "logicware_daily_requests_" . now()->format('Y-m-d');
        $count = Cache::get($counterKey, 0);
        Cache::put($counterKey, $count + 1, now()->endOfDay());
    }

    /**
     * Verificar si aún quedan consultas disponibles hoy
     * 
     * @return bool
     */
    public function hasAvailableRequests(): bool
    {
        return $this->getDailyRequestCount() < 4;
    }

    /**
     * Limpiar caché de stock
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $cacheKey = "logicware_stock_{$this->subdomain}";
        Cache::forget($cacheKey);
        
        Log::info('[LogicwareAPI] Caché limpiado', [
            'cache_key' => $cacheKey
        ]);
    }

    /**
     * Obtener una unidad específica filtrando por código
     * 
     * @param string $code Código de la unidad (ejemplo: E2-02)
     * @return array|null
     * @throws Exception
     */
    public function getProperty($code): ?array
    {
        try {
            $this->validateApiKey();

            Log::info('[LogicwareAPI] Obteniendo unidad por código', [
                'code' => $code
            ]);

            // Obtener todas y filtrar por código
            $data = $this->getProperties();
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                return null;
            }

            foreach ($data['data'] as $unit) {
                if (isset($unit['code']) && $unit['code'] === $code) {
                    return $unit;
                }
            }

            return null;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error al obtener unidad', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener propiedades disponibles (no vendidas)
     * 
     * @param bool $forceRefresh Forzar consulta real
     * @return array
     * @throws Exception
     */
    public function getAvailableProperties(bool $forceRefresh = false): array
    {
        return $this->getProperties([
            'status' => 'available'
        ], $forceRefresh);
    }

    /**
     * Obtener ventas de clientes dentro de un rango de fechas
     * Endpoint: GET /external/clients/sales
     * Headers: Authorization Bearer, X-Subdomain
     * 
     * @param string|null $startDate (formato YYYY-MM-DD)
     * @param string|null $endDate (formato YYYY-MM-DD)
     * @param bool $forceRefresh Forzar consulta real
     * @return array
     * @throws Exception
     */
    public function getSales(?string $startDate = null, ?string $endDate = null, bool $forceRefresh = false): array
    {
        try {
            $this->validateApiKey();

            $start = $startDate ?? now()->startOfMonth()->toDateString();
            $end = $endDate ?? now()->endOfMonth()->toDateString();

            $cacheKey = "logicware_sales_{$this->subdomain}_{$start}_{$end}";
            $cacheDuration = now()->addHours(6);

            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                Log::info('[LogicwareAPI] Datos de VENTAS obtenidos del CACHÉ', ['cache_key' => $cacheKey]);
                return $cachedData;
            }

            // Construir URL y query params
            $url = "{$this->baseUrl}/external/clients/sales";
            $query = [];
            if ($start) $query['startDate'] = $start;
            if ($end) $query['endDate'] = $end;

            Log::info('[LogicwareAPI] Obteniendo ventas del API', ['url' => $url, 'query' => $query]);

            // Realizar petición
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get($url, $query);

            if (!$response->successful()) {
                // Si es error 429 (rate limit), usar datos mock
                if ($response->status() === 429) {
                    Log::warning('[LogicwareAPI] ⚠️ Rate limit alcanzado para ventas, usando datos MOCK', [
                        'status' => $response->status()
                    ]);
                    
                    return $this->getMockSales($start, $end);
                }
                
                Log::error('[LogicwareAPI] Error al obtener ventas', ['status' => $response->status(), 'body' => $response->body()]);
                throw new Exception("Error al obtener ventas: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            $data['cached_at'] = now()->toDateTimeString();
            $data['cache_expires_at'] = $cacheDuration->toDateTimeString();

            // Guardar en caché solo si no es muy grande
            $dataSize = strlen(json_encode($data));
            $maxCacheSize = 1048576; // 1MB
            
            if ($dataSize < $maxCacheSize) {
                Cache::put($cacheKey, $data, $cacheDuration);
                Log::info('[LogicwareAPI] Ventas obtenidas y guardadas en caché', [
                    'total' => isset($data['data']) ? count($data['data']) : 0,
                    'data_size' => number_format($dataSize / 1024, 2) . ' KB'
                ]);
            } else {
                Log::warning('[LogicwareAPI] ⚠️ Respuesta de ventas demasiado grande para cachear', [
                    'total' => isset($data['data']) ? count($data['data']) : 0,
                    'data_size' => number_format($dataSize / 1024, 2) . ' KB'
                ]);
            }

            return $data;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error en getSales', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Obtener cronograma de pagos de un contrato
     * Endpoint: GET /external/payment-schedules/{correlative}
     * Headers: Authorization Bearer, X-Subdomain
     * 
     * Este endpoint devuelve todas las cuotas de pago con su estado actual,
     * permitiendo sincronizar qué cuotas ya han sido pagadas en Logicware.
     * 
     * @param string $correlative Número correlativo de la proforma (ej: 202511-000000596)
     * @param bool $forceRefresh Forzar consulta sin usar caché
     * @return array
     * @throws Exception
     */
    public function getPaymentSchedule(string $correlative, bool $forceRefresh = false): array
    {
        try {
            $this->validateApiKey();

            $cacheKey = "logicware_payment_schedule_{$this->subdomain}_{$correlative}";
            $cacheDuration = now()->addMinutes(30); // Caché más corto porque puede cambiar

            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                Log::info('[LogicwareAPI] Cronograma de pagos obtenido del CACHÉ', ['correlative' => $correlative]);
                return $cachedData;
            }

            $url = "{$this->baseUrl}/external/payment-schedules/{$correlative}";

            Log::info('[LogicwareAPI] Obteniendo cronograma de pagos', [
                'url' => $url,
                'correlative' => $correlative
            ]);

            // Timeout extendido para cronogramas (45 segundos)
            $response = Http::timeout(45)
                ->withHeaders($this->getAuthHeaders())
                ->get($url);

            if (!$response->successful()) {
                Log::error('[LogicwareAPI] Error al obtener cronograma de pagos', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'correlative' => $correlative
                ]);
                throw new Exception("Error al obtener cronograma de pagos: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            $data['cached_at'] = now()->toDateTimeString();
            $data['cache_expires_at'] = $cacheDuration->toDateTimeString();

            // Guardar en caché
            Cache::put($cacheKey, $data, $cacheDuration);

            Log::info('[LogicwareAPI] Cronograma de pagos obtenido y guardado en caché', [
                'correlative' => $correlative,
                'total_installments' => isset($data['data']) ? count($data['data']) : 0
            ]);

            return $data;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error en getPaymentSchedule', [
                'error' => $e->getMessage(),
                'correlative' => $correlative
            ]);
            throw $e;
        }
    }

    /**
     * Obtener STOCK COMPLETO de todas las unidades con información detallada
     * Endpoint: GET /external/units/stock/full
     * Headers: Authorization Bearer, X-Subdomain
     * 
     * Este endpoint incluye TODOS los datos:
     * - Información completa de la unidad (área, precio, características)
     * - Estado actual (disponible, reservado, vendido)
     * - Datos del vendedor/asesor asignado
     * - Historial de reservas y ventas
     * - Cliente asociado (si aplica)
     * - Información financiera completa
     * 
     * @param bool $forceRefresh Forzar consulta real (consume 1 de 4 llamadas diarias)
     * @return array
     * @throws Exception
     */
    public function getFullStockData(bool $forceRefresh = false): array
    {
        try {
            $this->validateApiKey();

            $cacheKey = "logicware_full_stock_{$this->subdomain}";
            $cacheDuration = now()->addHours(6); // 6 horas de caché

            // Verificar caché primero (a menos que se fuerce refresh)
            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                Log::info('[LogicwareAPI] 📦 Stock COMPLETO obtenido del CACHÉ', [
                    'cache_key' => $cacheKey,
                    'total_units' => isset($cachedData['data']) ? count($cachedData['data']) : 0
                ]);
                return $cachedData;
            }

            // Si no hay consultas disponibles y no se fuerza, usar caché expirado si existe
            if (!$this->hasAvailableRequests() && !$forceRefresh) {
                Log::warning('[LogicwareAPI] ⚠️ Límite de consultas alcanzado, intentando usar caché expirado');
                $expiredCache = Cache::get($cacheKey);
                if ($expiredCache) {
                    return $expiredCache;
                }
            }

            // Incrementar contador de peticiones
            $this->incrementDailyRequestCounter();

            $url = "{$this->baseUrl}/external/units/stock/full";

            Log::warning('[LogicwareAPI] ⚠️ CONSULTANDO STOCK COMPLETO (consume 1 de 4 consultas diarias)', [
                'url' => $url,
                'subdomain' => $this->subdomain,
                'force_refresh' => $forceRefresh,
                'daily_requests' => $this->getDailyRequestCount()
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get($url);

            if (!$response->successful()) {
                // Si es error 429 (rate limit), intentar usar datos en caché aunque estén expirados
                if ($response->status() === 429) {
                    Log::warning('[LogicwareAPI] ⚠️ Rate limit alcanzado para stock completo', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    
                    $expiredCache = Cache::get($cacheKey);
                    if ($expiredCache) {
                        Log::info('[LogicwareAPI] Usando caché expirado debido a rate limit');
                        return $expiredCache;
                    }
                    
                    throw new Exception("Rate limit alcanzado y no hay datos en caché disponibles");
                }
                
                Log::error('[LogicwareAPI] Error al obtener stock completo', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                throw new Exception("Error al obtener stock completo: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            
            // Agregar metadata
            $data['cached_at'] = now()->toDateTimeString();
            $data['cache_expires_at'] = $cacheDuration->toDateTimeString();
            $data['daily_requests_used'] = $this->getDailyRequestCount();

            // Guardar en caché con manejo de tamaño
            $dataSize = strlen(json_encode($data));
            $maxCacheSize = 2097152; // 2MB para stock completo (más grande que otros endpoints)
            
            if ($dataSize < $maxCacheSize) {
                Cache::put($cacheKey, $data, $cacheDuration);
                Log::info('[LogicwareAPI] ✅ Stock COMPLETO obtenido y guardado en caché', [
                    'total_units' => isset($data['data']) ? count($data['data']) : 0,
                    'cache_duration' => '6 horas',
                    'data_size' => number_format($dataSize / 1024, 2) . ' KB',
                    'daily_requests_used' => $this->getDailyRequestCount()
                ]);
            } else {
                Log::warning('[LogicwareAPI] ⚠️ Respuesta de stock completo demasiado grande para cachear', [
                    'total_units' => isset($data['data']) ? count($data['data']) : 0,
                    'data_size' => number_format($dataSize / 1024 / 1024, 2) . ' MB',
                    'max_cache_size' => '2 MB',
                    'note' => 'Los datos NO se guardaron en caché para evitar errores'
                ]);
            }

            return $data;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error al obtener stock completo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Refrescar el Bearer Token
     * Genera un nuevo token y reemplaza el actual
     * 
     * @return string Nuevo Bearer Token
     * @throws Exception
     */
    public function refreshToken(): string
    {
        // Limpiar el token actual
        $this->bearerToken = null;
        
        // Generar uno nuevo
        return $this->generateToken();
    }

    /**
     * Verificar la conexión con el API
     * 
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $this->validateApiKey();
            // Hacer una petición simple para verificar conectividad
            $this->getProperties(['limit' => 1]);
            return true;
        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Test de conexión falló', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Probar la autenticación completa (Token + Stock)
     * 
     * @return array
     */
    public function discoverEndpoints(): array
    {
        $this->validateApiKey();
        
        $results = [];
        
        // Paso 1: Intentar generar token
        try {
            $tokenUrl = "{$this->baseUrl}/auth/external/token";
            Log::info('[LogicwareAPI] Probando generación de token', ['url' => $tokenUrl]);
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Subdomain' => $this->subdomain,
                    'Accept' => 'application/json'
                ])
                ->post($tokenUrl);
            
            $results['TOKEN_GENERATION'] = [
                'url' => $tokenUrl,
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 500)
            ];
            
            if ($response->successful()) {
                $tokenData = $response->json();
                $token = $tokenData['accessToken'] ?? null;
                
                if ($token) {
                    Log::info('[LogicwareAPI] ✅ Token generado exitosamente!');
                    
                    // Paso 2: Intentar obtener stock con el token
                    try {
                        $stockUrl = "{$this->baseUrl}/external/units/stock/full";
                        Log::info('[LogicwareAPI] Probando obtención de stock', ['url' => $stockUrl]);
                        
                        $stockResponse = Http::timeout(10)
                            ->withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                                'X-Subdomain' => $this->subdomain,
                                'Accept' => 'application/json'
                            ])
                            ->get($stockUrl);
                        
                        $results['STOCK_FULL'] = [
                            'url' => $stockUrl,
                            'status' => $stockResponse->status(),
                            'success' => $stockResponse->successful(),
                            'body_preview' => substr($stockResponse->body(), 0, 500)
                        ];
                        
                        if ($stockResponse->successful()) {
                            Log::info('[LogicwareAPI] ✅ Stock obtenido exitosamente!');
                        }
                    } catch (\Exception $e) {
                        $results['STOCK_FULL'] = ['error' => $e->getMessage()];
                    }
                }
            }
        } catch (\Exception $e) {
            $results['TOKEN_GENERATION'] = ['error' => $e->getMessage()];
        }
        
        return $results;
    }

    /**
     * Obtener datos MOCK de propiedades para desarrollo
     * Se usa cuando se alcanza el rate limit del API
     * 
     * @return array
     */
    protected function getMockProperties(): array
    {
        Log::info('[LogicwareAPI] 🧪 Generando datos MOCK para desarrollo');
        
        return [
            'succeeded' => true,
            'message' => 'Units stock retrieved successfully (MOCK DATA)',
            'data' => [
                [
                    'id' => 'MOCK-001',
                    'code' => 'A-01',
                    'name' => 'Lote A-01 (MOCK)',
                    'area' => 120.50,
                    'price' => 150000.00,
                    'currency' => 'PEN',
                    'status' => 'disponible',
                    'block' => 'A',
                    'lot' => '01',
                    'project' => 'Casa Bonita'
                ],
                [
                    'id' => 'MOCK-002',
                    'code' => 'A-02',
                    'name' => 'Lote A-02 (MOCK)',
                    'area' => 135.75,
                    'price' => 165000.00,
                    'currency' => 'PEN',
                    'status' => 'disponible',
                    'block' => 'A',
                    'lot' => '02',
                    'project' => 'Casa Bonita'
                ],
                [
                    'id' => 'MOCK-003',
                    'code' => 'B-01',
                    'name' => 'Lote B-01 (MOCK)',
                    'area' => 110.00,
                    'price' => 140000.00,
                    'currency' => 'PEN',
                    'status' => 'vendido',
                    'block' => 'B',
                    'lot' => '01',
                    'project' => 'Casa Bonita'
                ],
                [
                    'id' => 'MOCK-004',
                    'code' => 'C-15',
                    'name' => 'Lote C-15 (MOCK)',
                    'area' => 145.25,
                    'price' => 180000.00,
                    'currency' => 'PEN',
                    'status' => 'disponible',
                    'block' => 'C',
                    'lot' => '15',
                    'project' => 'Casa Bonita'
                ]
            ],
            'cached_at' => now()->toDateTimeString(),
            'is_mock' => true,
            'mock_reason' => 'Rate limit exceeded - Using mock data for development'
        ];
    }

    /**
     * Obtener datos MOCK de ventas para desarrollo
     * 
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getMockSales(string $startDate, string $endDate): array
    {
        Log::info('[LogicwareAPI] 🧪 Generando datos MOCK de ventas para desarrollo');
        
        return [
            'succeeded' => true,
            'message' => 'Sales retrieved successfully (MOCK DATA)',
            'data' => [
                [
                    'id' => 'SALE-MOCK-001',
                    'documentNumber' => '202511-MOCK001',
                    'saleDate' => '2025-11-01',
                    'client' => [
                        'documentType' => 'DNI',
                        'documentNumber' => '12345678',
                        'name' => 'Juan',
                        'lastName' => 'Pérez García',
                        'email' => 'juan.perez@example.com',
                        'phone' => '987654321'
                    ],
                    'items' => [
                        [
                            'propertyCode' => 'A-01',
                            'totalPrice' => 150000.00,
                            'downPayment' => 30000.00,
                            'financedAmount' => 120000.00,
                            'installments' => 24,
                            'monthlyPayment' => 5000.00,
                            'currency' => 'PEN'
                        ]
                    ],
                    'advisor' => [
                        'code' => 'ADV001',
                        'name' => 'María López'
                    ]
                ],
                [
                    'id' => 'SALE-MOCK-002',
                    'documentNumber' => '202511-MOCK002',
                    'saleDate' => '2025-11-02',
                    'client' => [
                        'documentType' => 'DNI',
                        'documentNumber' => '87654321',
                        'name' => 'Ana',
                        'lastName' => 'Torres Sánchez',
                        'email' => 'ana.torres@example.com',
                        'phone' => '912345678'
                    ],
                    'items' => [
                        [
                            'propertyCode' => 'C-15',
                            'totalPrice' => 180000.00,
                            'downPayment' => 45000.00,
                            'financedAmount' => 135000.00,
                            'installments' => 36,
                            'monthlyPayment' => 3750.00,
                            'currency' => 'PEN'
                        ]
                    ],
                    'advisor' => [
                        'code' => 'ADV002',
                        'name' => 'Carlos Ruiz'
                    ]
                ]
            ],
            'cached_at' => now()->toDateTimeString(),
            'is_mock' => true,
            'mock_reason' => 'Rate limit exceeded - Using mock data for development'
        ];
    }

    /**
     * Obtener etapas (stages) de un proyecto
     * Endpoint: GET /external/stages?projectCode={code}
     * 
     * @param string $projectCode Código del proyecto (ejemplo: casabonita)
     * @param bool $forceRefresh Forzar consulta real
     * @return array
     * @throws Exception
     */
    public function getStages(string $projectCode = 'casabonita', bool $forceRefresh = false): array
    {
        try {
            $this->validateApiKey();

            $cacheKey = "logicware_stages_{$this->subdomain}_{$projectCode}";
            $cacheDuration = now()->addHours(24); // Stages no cambian frecuentemente

            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                Log::info('[LogicwareAPI] Stages obtenidos del CACHÉ', [
                    'cache_key' => $cacheKey,
                    'total' => isset($cachedData['data']) ? count($cachedData['data']) : 0
                ]);
                return $cachedData;
            }

            $url = "{$this->baseUrl}/external/stages";
            
            Log::info('[LogicwareAPI] Obteniendo stages del proyecto', [
                'url' => $url,
                'projectCode' => $projectCode
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get($url, ['projectCode' => $projectCode]);

            // Si el token expiró (401), regenerar y reintentar UNA vez
            if ($response->status() === 401) {
                Log::warning('[LogicwareAPI] 🔄 Token expirado (401), regenerando y reintentando...');
                
                // Limpiar token expirado del caché
                $tokenCacheKey = "logicware_bearer_token_{$this->subdomain}";
                Cache::forget($tokenCacheKey);
                $this->bearerToken = null;
                
                // Reintentar con nuevo token
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getAuthHeaders())
                    ->get($url, ['projectCode' => $projectCode]);
                    
                if ($response->status() === 401) {
                    Log::error('[LogicwareAPI] ❌ Aún 401 después de regenerar token');
                    throw new Exception("Error de autenticación: Token inválido incluso después de regenerar");
                }
            }

            if (!$response->successful()) {
                // Si es error 429 (rate limit), usar datos mock
                if ($response->status() === 429) {
                    Log::warning('[LogicwareAPI] ⚠️ Rate limit alcanzado para stages, usando datos MOCK', [
                        'status' => $response->status()
                    ]);
                    return $this->getMockStages($projectCode);
                }
                
                Log::error('[LogicwareAPI] Error al obtener stages', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                throw new Exception("Error al obtener stages: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            
            Log::info('[LogicwareAPI] 🔍 Datos RAW recibidos del API', [
                'data_structure' => isset($data['data']) ? 'existe' : 'NO existe',
                'first_stage' => isset($data['data'][0]) ? $data['data'][0] : 'N/A'
            ]);
            
            // Mapear stages para normalizar campos según estructura del API de LogicWare
            if (isset($data['data']) && is_array($data['data'])) {
                $data['data'] = array_map(function ($stage) {
                    // Normalizar campos del API de LogicWare al formato esperado por el frontend
                    $normalized = [
                        'id' => $stage['stageId'] ?? $stage['id'] ?? uniqid('stage-'),
                        'stageId' => $stage['stageId'] ?? null,
                        'name' => $stage['stageName'] ?? $stage['name'] ?? 'Sin nombre',
                        'code' => $stage['projectCode'] ?? $stage['code'] ?? null,
                        'projectCode' => $stage['projectCode'] ?? null,
                        'deliveryDate' => $stage['deliveryDate'] ?? null,
                        'createdAt' => $stage['createdAt'] ?? null,
                        'updatedAt' => $stage['updatedAt'] ?? null,
                        'units' => $stage['units'] ?? 0, // El API no devuelve este campo, se obtiene con getStockByStage
                    ];
                    
                    Log::info('[LogicwareAPI] ✅ Stage normalizado', [
                        'id' => $normalized['id'],
                        'name' => $normalized['name'],
                        'code' => $normalized['code']
                    ]);
                    
                    return $normalized;
                }, $data['data']);
                
                Log::info('[LogicwareAPI] ✅ Total stages procesados: ' . count($data['data']));
            }
            
            $data['cached_at'] = now()->toDateTimeString();
            $data['cache_expires_at'] = $cacheDuration->toDateTimeString();

            Cache::put($cacheKey, $data, $cacheDuration);
            
            Log::info('[LogicwareAPI] ✅ Stages obtenidos y guardados en caché', [
                'total' => isset($data['data']) ? count($data['data']) : 0,
                'projectCode' => $projectCode
            ]);

            return $data;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error en getStages', [
                'error' => $e->getMessage(),
                'projectCode' => $projectCode
            ]);
            throw $e;
        }
    }

    /**
     * Obtener stock de unidades por etapa (stage)
     * Endpoint: GET /external/units/stock?projectCode={code}&stageId={id}
     * 
     * @param string $projectCode Código del proyecto
     * @param string $stageId ID de la etapa
     * @param bool $forceRefresh Forzar consulta real
     * @return array
     * @throws Exception
     */
    public function getStockByStage(string $projectCode, string $stageId, bool $forceRefresh = false): array
    {
        try {
            $this->validateApiKey();

            $cacheKey = "logicware_stock_{$this->subdomain}_{$projectCode}_{$stageId}";
            $cacheDuration = now()->addHours(6);

            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                Log::info('[LogicwareAPI] Stock por stage obtenido del CACHÉ', [
                    'cache_key' => $cacheKey,
                    'total' => isset($cachedData['data']) ? count($cachedData['data']) : 0
                ]);
                return $cachedData;
            }

            $url = "{$this->baseUrl}/external/units/stock";
            
            Log::info('[LogicwareAPI] Obteniendo stock por stage', [
                'url' => $url,
                'projectCode' => $projectCode,
                'stageId' => $stageId
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAuthHeaders())
                ->get($url, [
                    'projectCode' => $projectCode,
                    'stageId' => $stageId
                ]);

            // Si el token expiró (401), regenerar y reintentar UNA vez
            if ($response->status() === 401) {
                Log::warning('[LogicwareAPI] 🔄 Token expirado (401), regenerando y reintentando...');
                
                // Limpiar token expirado del caché
                $tokenCacheKey = "logicware_bearer_token_{$this->subdomain}";
                Cache::forget($tokenCacheKey);
                $this->bearerToken = null;
                
                // Reintentar con nuevo token
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getAuthHeaders())
                    ->get($url, [
                        'projectCode' => $projectCode,
                        'stageId' => $stageId
                    ]);
                    
                if ($response->status() === 401) {
                    Log::error('[LogicwareAPI] ❌ Aún 401 después de regenerar token');
                    throw new Exception("Error de autenticación: Token inválido incluso después de regenerar");
                }
            }

            if (!$response->successful()) {
                // Si es error 429 (rate limit), usar datos mock
                if ($response->status() === 429) {
                    Log::warning('[LogicwareAPI] ⚠️ Rate limit alcanzado para stock, usando datos MOCK', [
                        'status' => $response->status()
                    ]);
                    return $this->getMockStockByStage($projectCode, $stageId);
                }
                
                Log::error('[LogicwareAPI] Error al obtener stock por stage', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url
                ]);
                throw new Exception("Error al obtener stock: HTTP {$response->status()} - " . $response->body());
            }

            $data = $response->json();
            
            // Normalizar estructura: El API devuelve data.properties[], nosotros necesitamos data[]
            if (isset($data['data']['properties']) && is_array($data['data']['properties'])) {
                Log::info('[LogicwareAPI] 📦 Normalizando estructura de properties', [
                    'total_properties' => count($data['data']['properties'])
                ]);
                
                // Mover properties al nivel data y mapear campos
                $data['data'] = array_map(function($unit) {
                    return [
                        // Campos básicos
                        'id' => $unit['id'] ?? null,
                        'code' => $unit['code'] ?? null,
                        'name' => $unit['description'] ?? $unit['code'] ?? 'Sin nombre',
                        
                        // Ubicación
                        'stageId' => $unit['stageId'] ?? null,
                        'stageName' => $unit['stageName'] ?? null,
                        'block' => $unit['blockName'] ?? null,
                        'blockName' => $unit['blockName'] ?? null,
                        'lotNumber' => $unit['code'] ?? null,
                        
                        // Dimensiones y área
                        'area' => $unit['areaSqm'] ?? 0,
                        'areaSqm' => $unit['areaSqm'] ?? 0,
                        'frontage' => $unit['dimensions']['front'] ?? 0,
                        'depth' => $unit['dimensions']['right'] ?? 0,
                        'dimensions' => $unit['dimensions'] ?? null,
                        
                        // Precios
                        'price' => $unit['totalPrice'] ?? 0,
                        'totalPrice' => $unit['totalPrice'] ?? 0,
                        'pricePerSqm' => $unit['pricePerSqm'] ?? 0,
                        'currency' => $unit['currency'] ?? 'PEN',
                        
                        // Estado y características
                        'status' => $unit['status'] ?? 'Desconocido',
                        'remarks' => $unit['remarks'] ?? null,
                        'orientation' => $unit['orientation'] ?? null,
                        'isCorner' => $unit['isCorner'] ?? false,
                        
                        // Fechas
                        'deliveryDate' => $unit['deliveryDate'] ?? null,
                        'createdAt' => $unit['createdAt'] ?? null,
                        'updatedAt' => $unit['updatedAt'] ?? null,
                        
                        // Features (construir desde remarks y características)
                        'features' => array_filter([
                            $unit['remarks'] ?? null,
                            $unit['isCorner'] ? 'Lote esquinero' : null,
                            $unit['orientation'] ? 'Orientación: ' . $unit['orientation'] : null,
                        ]),
                    ];
                }, $data['data']['properties']);
                
                Log::info('[LogicwareAPI] ✅ Properties normalizadas', [
                    'total' => count($data['data']),
                    'primer_lote' => $data['data'][0]['code'] ?? 'N/A'
                ]);
            }
            
            $data['cached_at'] = now()->toDateTimeString();
            $data['cache_expires_at'] = $cacheDuration->toDateTimeString();

            Cache::put($cacheKey, $data, $cacheDuration);
            
            Log::info('[LogicwareAPI] ✅ Stock por stage obtenido y guardado en caché', [
                'total' => isset($data['data']) ? count($data['data']) : 0,
                'projectCode' => $projectCode,
                'stageId' => $stageId
            ]);

            return $data;

        } catch (Exception $e) {
            Log::error('[LogicwareAPI] Error en getStockByStage', [
                'error' => $e->getMessage(),
                'projectCode' => $projectCode,
                'stageId' => $stageId
            ]);
            throw $e;
        }
    }

    /**
     * Obtener datos MOCK de stages para desarrollo
     * 
     * @param string $projectCode
     * @return array
     */
    protected function getMockStages(string $projectCode): array
    {
        Log::info('[LogicwareAPI] 🧪 Generando datos MOCK de stages');
        
        return [
            'succeeded' => true,
            'message' => 'Stages retrieved successfully (MOCK DATA)',
            'data' => [
                [
                    'id' => 'STAGE-001',
                    'name' => 'Etapa 1 - Fase A',
                    'code' => 'ETAPA-1A',
                    'projectCode' => $projectCode,
                    'description' => 'Primera etapa del proyecto - Zona A',
                    'totalUnits' => 45,
                    'availableUnits' => 32,
                    'status' => 'active'
                ],
                [
                    'id' => 'STAGE-002',
                    'name' => 'Etapa 1 - Fase B',
                    'code' => 'ETAPA-1B',
                    'projectCode' => $projectCode,
                    'description' => 'Primera etapa del proyecto - Zona B',
                    'totalUnits' => 38,
                    'availableUnits' => 28,
                    'status' => 'active'
                ],
                [
                    'id' => 'STAGE-003',
                    'name' => 'Etapa 2 - Residencial',
                    'code' => 'ETAPA-2',
                    'projectCode' => $projectCode,
                    'description' => 'Segunda etapa - Zona residencial premium',
                    'totalUnits' => 52,
                    'availableUnits' => 45,
                    'status' => 'active'
                ]
            ],
            'cached_at' => now()->toDateTimeString(),
            'is_mock' => true,
            'mock_reason' => 'Rate limit exceeded or development mode'
        ];
    }

    /**
     * Obtener datos MOCK de stock por stage para desarrollo
     * 
     * @param string $projectCode
     * @param string $stageId
     * @return array
     */
    protected function getMockStockByStage(string $projectCode, string $stageId): array
    {
        Log::info('[LogicwareAPI] 🧪 Generando datos MOCK de stock por stage');
        
        return [
            'succeeded' => true,
            'message' => 'Stock retrieved successfully (MOCK DATA)',
            'data' => [
                [
                    'id' => 'UNIT-001',
                    'code' => 'A-01',
                    'name' => 'Lote A-01',
                    'stageId' => $stageId,
                    'stageName' => 'Etapa 1 - Fase A',
                    'block' => 'A',
                    'lotNumber' => '01',
                    'area' => 120.50,
                    'frontage' => 8.5,
                    'depth' => 15.0,
                    'price' => 150000.00,
                    'currency' => 'PEN',
                    'status' => 'disponible',
                    'features' => [
                        'Esquina',
                        'Vista a parque',
                        'Servicios básicos'
                    ]
                ],
                [
                    'id' => 'UNIT-002',
                    'code' => 'A-02',
                    'name' => 'Lote A-02',
                    'stageId' => $stageId,
                    'stageName' => 'Etapa 1 - Fase A',
                    'block' => 'A',
                    'lotNumber' => '02',
                    'area' => 135.75,
                    'frontage' => 9.0,
                    'depth' => 15.0,
                    'price' => 165000.00,
                    'currency' => 'PEN',
                    'status' => 'disponible',
                    'features' => [
                        'Lote interior',
                        'Servicios básicos'
                    ]
                ],
                [
                    'id' => 'UNIT-003',
                    'code' => 'A-03',
                    'name' => 'Lote A-03',
                    'stageId' => $stageId,
                    'stageName' => 'Etapa 1 - Fase A',
                    'block' => 'A',
                    'lotNumber' => '03',
                    'area' => 110.00,
                    'frontage' => 8.0,
                    'depth' => 14.0,
                    'price' => 140000.00,
                    'currency' => 'PEN',
                    'status' => 'vendido',
                    'features' => [
                        'Lote regular',
                        'Servicios básicos'
                    ]
                ]
            ],
            'cached_at' => now()->toDateTimeString(),
            'is_mock' => true,
            'mock_reason' => 'Rate limit exceeded or development mode',
            'projectCode' => $projectCode,
            'stageId' => $stageId
        ];
    }
}
