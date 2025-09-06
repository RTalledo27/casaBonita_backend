# 📋 Pre-Deploy Checklist - Casa Bonita Backend

Antes de hacer deploy a producción, asegúrate de completar todos estos pasos:

## ✅ Configuración Básica

- [ ] **Vercel CLI instalado**: `npm install -g vercel`
- [ ] **Composer instalado**: Verificar con `composer --version`
- [ ] **Login en Vercel**: `vercel login`
- [ ] **Proyecto vinculado**: `vercel link` (si es la primera vez)

## ✅ Variables de Entorno

### Variables Requeridas en Vercel:
- [ ] `APP_NAME` - Nombre de la aplicación
- [ ] `APP_ENV` - Debe ser "production"
- [ ] `APP_KEY` - Clave de encriptación de Laravel (generar con `php artisan key:generate --show`)
- [ ] `APP_URL` - URL de producción
- [ ] `DB_CONNECTION` - Tipo de base de datos (mysql/pgsql)
- [ ] `DB_HOST` - Host de la base de datos
- [ ] `DB_PORT` - Puerto de la base de datos
- [ ] `DB_DATABASE` - Nombre de la base de datos
- [ ] `DB_USERNAME` - Usuario de la base de datos
- [ ] `DB_PASSWORD` - Contraseña de la base de datos

### Configurar variables con:
```bash
vercel env add APP_NAME
vercel env add APP_ENV
vercel env add APP_KEY
# ... continuar con todas las variables
```

### Variables Opcionales pero Recomendadas:
- [ ] `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, etc. (para emails)
- [ ] `CORS_ALLOWED_ORIGINS` (URLs del frontend)
- [ ] `JWT_SECRET` (si usas JWT)
- [ ] `LOG_LEVEL` (error para producción)

## ✅ Base de Datos

- [ ] **Base de datos creada** en el proveedor (PlanetScale, Railway, etc.)
- [ ] **Migraciones ejecutadas** en producción
- [ ] **Seeders ejecutados** (si es necesario)
- [ ] **Backup de datos** (si actualizas una DB existente)
- [ ] **Conexión probada** desde local con credenciales de producción

## ✅ Código y Dependencias

- [ ] **Código commiteado** en Git
- [ ] **Tests pasando** (si los tienes): `composer test`
- [ ] **Dependencias actualizadas**: `composer update`
- [ ] **Autoload optimizado**: `composer dump-autoload --optimize`
- [ ] **Configuración cacheada**: `php artisan config:cache`
- [ ] **Rutas cacheadas**: `php artisan route:cache`

## ✅ Configuración de Producción

- [ ] **APP_DEBUG=false** en variables de entorno
- [ ] **LOG_LEVEL=error** para reducir logs
- [ ] **CACHE_DRIVER** configurado (array para Vercel)
- [ ] **SESSION_DRIVER=cookie** para serverless
- [ ] **QUEUE_CONNECTION=sync** para serverless

## ✅ Seguridad

- [ ] **CORS configurado** correctamente
- [ ] **Rate limiting** habilitado
- [ ] **Headers de seguridad** configurados
- [ ] **HTTPS forzado** en producción
- [ ] **Secrets no expuestos** en el código

## ✅ Archivos de Deploy

- [ ] **vercel.json** configurado correctamente
- [ ] **composer.json** con scripts de producción
- [ ] **.vercelignore** para excluir archivos innecesarios
- [ ] **index.php** en la raíz del proyecto

## ✅ Testing Pre-Deploy

- [ ] **API endpoints** funcionando en local
- [ ] **Autenticación** funcionando
- [ ] **Base de datos** conectando correctamente
- [ ] **CORS** permitiendo requests del frontend
- [ ] **Logs** configurados para producción

## ✅ Post-Deploy

- [ ] **API responde** en la URL de Vercel
- [ ] **Health check** endpoint funcionando
- [ ] **Frontend conecta** correctamente al backend
- [ ] **Logs monitoreados** en Vercel dashboard
- [ ] **Performance** aceptable (< 2s response time)

## 🚀 Comandos de Deploy

### Deploy a Staging:
```bash
.\deploy.ps1 staging
```

### Deploy a Production:
```bash
.\deploy.ps1 production
```

### Deploy Manual:
```bash
# Staging
vercel

# Production
vercel --prod
```

## 🆘 Rollback

Si algo sale mal:
```bash
# Ver deployments
vercel ls

# Rollback al deployment anterior
vercel rollback

# O desde el dashboard
# https://vercel.com/dashboard
```

## 📊 Monitoreo

- **Dashboard**: https://vercel.com/dashboard
- **Logs**: `vercel logs [project-name]`
- **Analytics**: Panel de Vercel
- **Uptime**: Configurar monitoring externo

## 📞 Contactos de Emergencia

- **DevOps**: [tu-email@domain.com]
- **Database Admin**: [db-admin@domain.com]
- **Project Manager**: [pm@domain.com]

---

**Nota**: Este checklist debe completarse antes de cada deploy a producción. Guarda una copia de este archivo y márcalo cada vez que hagas deploy.