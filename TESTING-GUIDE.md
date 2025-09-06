# Guía de Testing - Casa Bonita Backend

## Estado Actual del Deployment

✅ **Deploy exitoso en Vercel**
- URL Principal: https://casa-bonita-backend-7d25.vercel.app
- Runtime: vercel-php@0.7.3
- Variables de entorno configuradas

❌ **Error 500 detectado**
- Todas las rutas devuelven error interno del servidor
- Posible problema con dependencias de Laravel o configuración

## Métodos de Testing

### 1. Testing Local (Recomendado)

```powershell
# Iniciar servidor local
php artisan serve --host=0.0.0.0 --port=8000

# Probar endpoints básicos
Invoke-WebRequest -Uri "http://localhost:8000/" -Method GET
Invoke-WebRequest -Uri "http://localhost:8000/health" -Method GET
Invoke-WebRequest -Uri "http://localhost:8000/api/health" -Method GET
```

### 2. Testing con cURL (Alternativo)

```bash
# Si tienes WSL o Git Bash
curl -X GET http://localhost:8000/ -H "Accept: application/json"
curl -X GET http://localhost:8000/health -H "Accept: application/json"
```

### 3. Testing con Postman/Insomnia

1. Crear nueva colección
2. Agregar requests:
   - GET http://localhost:8000/
   - GET http://localhost:8000/health
   - GET http://localhost:8000/api/health

## Diagnóstico de Errores

### Verificar Logs Locales

```powershell
# Ver logs de Laravel
Get-Content storage/logs/laravel.log -Tail 50

# Ver logs en tiempo real
Get-Content storage/logs/laravel.log -Wait
```

### Verificar Configuración

```powershell
# Verificar variables de entorno
php artisan config:show

# Verificar rutas
php artisan route:list

# Verificar estado de la aplicación
php artisan about
```

### Comandos de Mantenimiento

```powershell
# Limpiar caché
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Endpoints Disponibles

### Rutas Web (routes/web.php)

- **GET /** - Estado de la API
  ```json
  {
    "status": "success",
    "message": "Casa Bonita API is running",
    "timestamp": "2025-01-06T...",
    "version": "1.0.0"
  }
  ```

- **GET /health** - Health check
  ```json
  {
    "status": "healthy",
    "service": "Casa Bonita Backend",
    "timestamp": "2025-01-06T..."
  }
  ```

### Rutas API (routes/api.php)

- **GET /api/health** - API health check
- Otros endpoints de módulos (requieren autenticación)

## Solución de Problemas Comunes

### Error 500 - Internal Server Error

1. **Verificar APP_KEY**
   ```powershell
   php artisan key:generate
   ```

2. **Verificar permisos de storage**
   ```powershell
   # En Windows, asegurar que storage/ sea escribible
   icacls storage /grant Everyone:F /T
   ```

3. **Verificar dependencias**
   ```powershell
   composer install --no-dev --optimize-autoloader
   ```

### Error de Base de Datos

1. **Verificar conexión**
   ```powershell
   php artisan migrate:status
   ```

2. **Configurar SQLite para testing**
   ```powershell
   # Crear base de datos SQLite
   touch database/database.sqlite
   
   # Actualizar .env
   DB_CONNECTION=sqlite
   DB_DATABASE=database/database.sqlite
   ```

### Error de Composer

```powershell
# Reinstalar dependencias
composer clear-cache
composer install --no-dev
```

## Testing de Módulos Específicos

### Autenticación

```powershell
# Crear usuario de prueba
php artisan tinker
# En tinker:
User::create(['name' => 'Test', 'email' => 'test@test.com', 'password' => Hash::make('password')]);
```

### API Endpoints

```powershell
# Login
Invoke-WebRequest -Uri "http://localhost:8000/api/login" -Method POST -Body '{"email":"test@test.com","password":"password"}' -ContentType "application/json"

# Usar token en requests subsecuentes
$token = "Bearer your-token-here"
Invoke-WebRequest -Uri "http://localhost:8000/api/user" -Method GET -Headers @{"Authorization"=$token}
```

## Checklist de Funcionalidad

- [ ] Servidor local inicia correctamente
- [ ] Ruta raíz (/) responde con JSON
- [ ] Ruta /health responde correctamente
- [ ] Rutas API responden (con/sin autenticación)
- [ ] Base de datos conecta correctamente
- [ ] Logs no muestran errores críticos
- [ ] Variables de entorno cargadas
- [ ] Composer dependencies instaladas

## Próximos Pasos

1. **Resolver error 500 en Vercel**
   - Revisar logs detallados
   - Verificar compatibilidad PHP/Laravel
   - Ajustar configuración vercel.json

2. **Implementar monitoring**
   - Configurar alertas de error
   - Implementar health checks automáticos

3. **Optimizar performance**
   - Configurar caché Redis
   - Optimizar queries de base de datos

## Comandos Útiles

```powershell
# Deploy a Vercel
vercel --prod

# Ver logs de Vercel
vercel logs https://casa-bonita-backend-7d25.vercel.app

# Configurar variables de entorno
vercel env add VARIABLE_NAME

# Vincular proyecto local
vercel link
```

---

**Nota**: Si persisten los errores 500 en Vercel, considera usar el servidor local para desarrollo y testing hasta resolver los problemas de configuración en producción.