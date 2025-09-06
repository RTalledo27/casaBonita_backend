# Deploy Instructions - Casa Bonita Backend API

## Configuración Actual

El backend está configurado como una aplicación Laravel con PHP que se puede desplegar en Vercel usando el runtime `vercel-php@0.7.3`.

## Deploy en Vercel

### Opción 1: Deploy Automático desde GitHub

1. **Conectar repositorio:**
   - Ve a [Vercel Dashboard](https://vercel.com/dashboard)
   - Haz clic en "New Project"
   - Conecta tu repositorio de GitHub
   - Selecciona la carpeta `casaBonita_api`

2. **Configuración automática:**
   - Vercel detectará automáticamente el archivo `vercel.json`
   - **Framework Preset**: Other
   - **Root Directory**: `casaBonita_api`
   - **Build Command**: (dejar vacío)
   - **Output Directory**: (dejar vacío)
   - **Install Command**: `composer install --no-dev --optimize-autoloader`

### Opción 2: Deploy Manual con Vercel CLI

```bash
# Instalar Vercel CLI (si no lo tienes)
npm i -g vercel

# Navegar al directorio del backend
cd casaBonita_api

# Login en Vercel
vercel login

# Deploy
vercel --prod
```

### Opción 3: Deploy desde archivos locales

```bash
# Navegar al directorio del backend
cd casaBonita_api

# Instalar dependencias de producción
composer install --no-dev --optimize-autoloader

# Deploy
vercel --prod
```

## Configuración de Variables de Entorno

En Vercel Dashboard, configura las siguientes variables de entorno:

### Variables Requeridas:
```
APP_NAME="Casa Bonita API"
APP_ENV=production
APP_KEY=base64:TU_APP_KEY_AQUI
APP_DEBUG=false
APP_URL=https://tu-dominio-api.vercel.app

DB_CONNECTION=mysql
DB_HOST=tu-host-db
DB_PORT=3306
DB_DATABASE=tu-database
DB_USERNAME=tu-usuario
DB_PASSWORD=tu-password

CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

CORS_ALLOWED_ORIGINS="https://tu-frontend.vercel.app,http://localhost:4200"
```

### Variables Opcionales:
```
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

## Configuración de Base de Datos

### Opciones Recomendadas:

1. **PlanetScale** (MySQL compatible)
   - Gratis hasta cierto límite
   - Excelente integración con Vercel
   - Escalabilidad automática

2. **Railway** (PostgreSQL/MySQL)
   - Plan gratuito disponible
   - Fácil configuración

3. **Supabase** (PostgreSQL)
   - Plan gratuito generoso
   - Incluye autenticación y storage

### Configuración de PlanetScale:

```bash
# Instalar PlanetScale CLI
curl -fsSL https://get.planetscale.com/psql | sh

# Login
pscale auth login

# Crear base de datos
pscale database create casa-bonita-db

# Crear branch de producción
pscale branch create casa-bonita-db main

# Obtener string de conexión
pscale connect casa-bonita-db main --port 3309
```

## Migraciones y Seeders

### Ejecutar migraciones en producción:

```bash
# Opción 1: Usando Vercel CLI
vercel env pull .env.production
php artisan migrate --force

# Opción 2: Crear un endpoint para migraciones (NO RECOMENDADO EN PRODUCCIÓN)
# Solo para desarrollo/testing
```

### Script de inicialización (crear en routes/web.php):

```php
// Solo para desarrollo - REMOVER EN PRODUCCIÓN
Route::get('/migrate', function () {
    if (app()->environment('production')) {
        abort(403, 'No permitido en producción');
    }
    
    Artisan::call('migrate', ['--force' => true]);
    return 'Migraciones ejecutadas';
});
```

## Configuración de CORS

Asegúrate de que el archivo `config/cors.php` esté configurado correctamente:

```php
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200')),
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'allowed_methods' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

## Verificación del Deploy

### Endpoints de prueba:

1. **Health Check**: `GET /api/health`
2. **API Info**: `GET /api`
3. **Auth Test**: `POST /api/auth/login`

### Comandos de verificación:

```bash
# Verificar que la API responde
curl https://tu-api.vercel.app/api/health

# Verificar CORS
curl -H "Origin: https://tu-frontend.vercel.app" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: X-Requested-With" \
     -X OPTIONS \
     https://tu-api.vercel.app/api/auth/login
```

## Troubleshooting

### Problemas Comunes:

1. **Error 500 - Internal Server Error**
   - Verificar variables de entorno
   - Revisar logs en Vercel Dashboard
   - Verificar permisos de archivos

2. **Error de CORS**
   - Verificar `CORS_ALLOWED_ORIGINS`
   - Revisar configuración en `config/cors.php`

3. **Error de Base de Datos**
   - Verificar credenciales de DB
   - Verificar que las migraciones se ejecutaron
   - Revisar conexión de red

4. **Error 404 en rutas API**
   - Verificar configuración de `vercel.json`
   - Revisar que las rutas estén en `routes/api.php`

### Logs y Debugging:

```bash
# Ver logs en tiempo real
vercel logs tu-proyecto-api

# Ver logs específicos de una función
vercel logs tu-proyecto-api --since=1h
```

## Optimizaciones para Producción

### 1. Optimización de Composer:

```bash
composer install --no-dev --optimize-autoloader --no-scripts
composer dump-autoload --optimize --classmap-authoritative
```

### 2. Configuración de Cache:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Optimización de Vercel:

En `vercel.json`, asegúrate de tener:

```json
{
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.7.3",
      "maxDuration": 30
    }
  }
}
```

## Monitoreo y Mantenimiento

### Herramientas Recomendadas:

1. **Vercel Analytics** - Métricas de rendimiento
2. **Sentry** - Monitoreo de errores
3. **New Relic** - APM y monitoreo
4. **Uptime Robot** - Monitoreo de disponibilidad

### Configuración de Sentry:

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN_HERE
```

## Notas Importantes

- ⚠️ **Nunca** commitees archivos `.env` al repositorio
- 🔒 Usa variables de entorno para todas las credenciales
- 📊 Configura monitoreo desde el primer día
- 🚀 Prueba el deploy en un ambiente de staging primero
- 💾 Configura backups automáticos de la base de datos
- 🔄 Implementa CI/CD para deploys automáticos

## Enlaces Útiles

- [Vercel PHP Runtime](https://vercel.com/docs/runtimes/php)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [PlanetScale Laravel](https://planetscale.com/docs/tutorials/laravel-quickstart)
- [Vercel Environment Variables](https://vercel.com/docs/projects/environment-variables)