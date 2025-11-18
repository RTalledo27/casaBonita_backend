# 🚀 Workers 24/7 con Supervisor - Casa Bonita

## 📋 Instalación en el Droplet

### Opción 1: Script Automático (Recomendado)

```bash
# 1. Conectarte al droplet
ssh root@tu-droplet-ip

# 2. Ir al directorio del proyecto
cd /var/www/html/casaBonita_api

# 3. Hacer ejecutable el script
chmod +x install_workers.sh

# 4. Ejecutar el script
./install_workers.sh
```

### Opción 2: Manual

```bash
# 1. Instalar Supervisor
sudo apt-get update
sudo apt-get install -y supervisor

# 2. Copiar configuración
sudo cp supervisor_workers.conf /etc/supervisor/conf.d/casabonita-workers.conf

# 3. Crear directorios de logs
sudo mkdir -p storage/logs
sudo chown -R www-data:www-data storage

# 4. Recargar y iniciar
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start casabonita-worker:*
sudo supervisorctl start casabonita-bonus-worker:*
sudo supervisorctl start casabonita-scheduler:*
```

## 🔧 Comandos Útiles

### Ver Estado
```bash
sudo supervisorctl status
```

### Reiniciar Workers
```bash
# Todos los workers
sudo supervisorctl restart all

# Solo workers generales
sudo supervisorctl restart casabonita-worker:*

# Solo worker de bonos
sudo supervisorctl restart casabonita-bonus-worker:*

# Solo scheduler
sudo supervisorctl restart casabonita-scheduler:*
```

### Ver Logs en Tiempo Real
```bash
# Worker general
tail -f storage/logs/worker.log

# Worker de bonos
tail -f storage/logs/bonus-worker.log

# Scheduler
tail -f storage/logs/scheduler.log

# Laravel general
tail -f storage/logs/laravel.log
```

### Detener Workers
```bash
# Todos
sudo supervisorctl stop all

# Específico
sudo supervisorctl stop casabonita-worker:*
```

### Recargar Configuración (después de cambios)
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all
```

## 📊 Workers Configurados

### 1. Worker General (casabonita-worker)
- **Procesos:** 2 workers en paralelo
- **Cola:** default
- **Reintentos:** 3 intentos
- **Max tiempo:** 1 hora
- **Log:** `storage/logs/worker.log`

### 2. Worker de Bonos (casabonita-bonus-worker)
- **Procesos:** 1 worker dedicado
- **Cola:** bonus (alta prioridad)
- **Reintentos:** 3 intentos
- **Log:** `storage/logs/bonus-worker.log`

### 3. Scheduler (casabonita-scheduler)
- **Función:** Ejecuta tareas programadas cada minuto
- **Tareas:**
  - Cálculo de bonos mensuales (día 1 a las 00:00)
  - Sincronización con LogicWare (si está configurado)
  - Limpieza de caché antiguo
- **Log:** `storage/logs/scheduler.log`

## 🔥 Después de Deploy

Cada vez que hagas `git pull` en el droplet:

```bash
# 1. Actualizar código
cd /var/www/html/casaBonita_api
git pull origin main

# 2. Actualizar dependencias (si hay cambios en composer.json)
composer install --no-dev --optimize-autoloader

# 3. Correr migraciones (si hay nuevas)
php artisan migrate --force

# 4. Limpiar cachés
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reiniciar workers para aplicar cambios
sudo supervisorctl restart all

# 6. Verificar que todo esté corriendo
sudo supervisorctl status
```

## ⚠️ Troubleshooting

### Workers no inician
```bash
# Ver errores en logs de Supervisor
sudo tail -f /var/log/supervisor/supervisord.log

# Ver configuración actual
sudo supervisorctl status

# Recargar completamente Supervisor
sudo systemctl restart supervisor
```

### Queue estancada
```bash
# Ver trabajos fallidos
php artisan queue:failed

# Reintentar trabajos fallidos
php artisan queue:retry all

# Limpiar trabajos fallidos antiguos
php artisan queue:flush
```

### Alto consumo de memoria
```bash
# Ver uso de memoria
top -u www-data

# Reducir número de workers en supervisor_workers.conf:
# numprocs=1  (en lugar de 2)

# Luego recargar
sudo supervisorctl reread
sudo supervisorctl update
```

## 📝 Logs Importantes

```bash
# Workers
/var/www/html/casaBonita_api/storage/logs/worker.log
/var/www/html/casaBonita_api/storage/logs/bonus-worker.log
/var/www/html/casaBonita_api/storage/logs/scheduler.log

# Laravel
/var/www/html/casaBonita_api/storage/logs/laravel.log

# Supervisor
/var/log/supervisor/supervisord.log

# Nginx (si hay errores de permisos)
/var/log/nginx/error.log
```

## 🎯 Verificar que Todo Funciona

```bash
# 1. Ver que los workers estén RUNNING
sudo supervisorctl status

# Salida esperada:
# casabonita-worker:casabonita-worker_00   RUNNING   pid 12345, uptime 0:01:23
# casabonita-worker:casabonita-worker_01   RUNNING   pid 12346, uptime 0:01:23
# casabonita-bonus-worker:casabonita-bonus-worker_00 RUNNING pid 12347, uptime 0:01:23
# casabonita-scheduler                     RUNNING   pid 12348, uptime 0:01:23

# 2. Probar que procesen jobs
php artisan tinker
>>> dispatch(function() { \Log::info('Worker test OK!'); });
>>> exit

# 3. Ver el log
tail storage/logs/worker.log
# Debería ver: [timestamp] local.INFO: Worker test OK!
```

## 🔄 Auto-reinicio

Supervisor está configurado con `autorestart=true`, lo que significa:
- ✅ Si un worker crashea, se reinicia automáticamente
- ✅ Si el servidor se reinicia, los workers se inician automáticamente
- ✅ Los workers se mantienen corriendo 24/7 sin intervención

## 📞 Soporte

Si tienes problemas:
1. Revisa los logs en `storage/logs/`
2. Verifica permisos: `sudo chown -R www-data:www-data storage`
3. Asegúrate que `.env` tenga `QUEUE_CONNECTION=database`
4. Verifica que la tabla `jobs` exista: `php artisan queue:table && php artisan migrate`
