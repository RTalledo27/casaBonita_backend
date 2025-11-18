# 📧 Guía de Prueba - Sistema de Email de Bienvenida

**Fecha:** 13 de Noviembre de 2025  
**Estado:** ✅ Implementado - Listo para Probar

---

## 🚀 Inicio Rápido

### 1️⃣ **Iniciar Servicios Necesarios**

```powershell
# MySQL debe estar corriendo
# Verificar en XAMPP Control Panel o Laragon

# Backend (Laravel)
cd casaBonita_api
php artisan serve
# ✅ Servidor en: http://127.0.0.1:8000

# Frontend (Angular) - En otra terminal
cd casaBonita_frontend
npm start
# ✅ Aplicación en: http://localhost:4200
```

---

## 🧪 Pruebas del Sistema de Email

### **Opción 1: Comando Artisan (Prueba Directa)**

```bash
# Probar con un usuario existente en la BD
php artisan email:test-welcome usuario@casabonita.pe

# Probar con usuario ficticio (para testing)
php artisan email:test-welcome test@example.com
# El comando preguntará si deseas usar datos de prueba
```

**Resultado Esperado:**
```
📧 Enviando email de bienvenida...
   Destinatario: usuario@casabonita.pe
   Nombre: Juan Pérez
   URL Login: http://localhost:4200

✅ ¡Email enviado exitosamente!

┌─────────────────────┬───────────────────────────┐
│ Campo               │ Valor                     │
├─────────────────────┼───────────────────────────┤
│ Destinatario        │ usuario@casabonita.pe     │
│ Contraseña Temporal │ 123456                    │
│ URL de Acceso       │ http://localhost:4200     │
└─────────────────────┴───────────────────────────┘
```

---

### **Opción 2: Importación desde el Frontend**

1. **Abrir el navegador:** http://localhost:4200
2. **Iniciar sesión** con un usuario administrador
3. **Ir a:** Recursos Humanos > Empleados
4. **Clic en:** "Importar Empleados"
5. **Descargar plantilla** Excel
6. **Completar datos** de prueba (usar tu email real para recibir el correo)
7. **Subir archivo** y hacer clic en "Importar"

**Resultado Esperado:**
```json
{
  "success": true,
  "message": "Se importaron 3 empleados. Se enviaron 3 correos de bienvenida.",
  "data": {
    "imported": 3,
    "created_users": [1, 2, 3],
    "created_employees": [1, 2, 3],
    "emails_sent": 3,
    "emails_failed": []
  }
}
```

---

### **Opción 3: Testing Manual con Tinker**

```bash
php artisan tinker

# Crear usuario de prueba
$user = new \Modules\Security\Models\User([
    'username' => 'testuser',
    'email' => 'tu_email@gmail.com',
    'password_hash' => bcrypt('123456'),
    'first_name' => 'Usuario',
    'last_name' => 'De Prueba',
    'dni' => '12345678',
    'position' => 'Tester',
    'must_change_password' => true,
    'status' => 'active'
]);

# Enviar email
\Illuminate\Support\Facades\Mail::to($user->email)->send(
    new \App\Mail\NewUserCredentialsMail(
        $user, 
        '123456', 
        'http://localhost:4200'
    )
);

# Verificar
echo "✅ Email enviado a: " . $user->email;
```

---

## 📝 Checklist de Verificación

### **Antes de Probar:**

- [ ] MySQL está corriendo (XAMPP/Laragon)
- [ ] Backend Laravel corriendo en puerto 8000
- [ ] Base de datos `casa_bonita` existe y tiene migraciones
- [ ] Configuración de email en `.env` es correcta
- [ ] `FRONTEND_URL` en `.env` apunta a http://localhost:4200

### **Configuración de Email (.env):**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=romaim.talledo@casabonita.pe
MAIL_PASSWORD="nnog niqg icox lhgw"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=romaim.talledo@casabonita.pe
MAIL_FROM_NAME="Casa Bonita Residencial"

FRONTEND_URL=http://localhost:4200
```

### **Verificar Email Enviado:**

- [ ] Correo llegó a la bandeja de entrada
- [ ] Asunto: "¡Bienvenido a Casa Bonita Residencial! - Tus Credenciales de Acceso"
- [ ] Muestra el nombre completo del usuario
- [ ] Contiene las credenciales (email y contraseña)
- [ ] Botón "Acceder al Sistema" funciona
- [ ] Diseño se ve correctamente (responsive)
- [ ] No fue a SPAM

---

## 🎨 Vista Previa del Email

El correo que recibirán los empleados incluye:

```
┌─────────────────────────────────────────┐
│  🎉                                     │
│  [CASA BONITA RESIDENCIAL]             │
│  ¡Bienvenido al Equipo!                │
└─────────────────────────────────────────┘

¡Hola Juan Pérez! 👋

Nos complace darte la bienvenida a Casa Bonita...

┌───────────────────────────────────────┐
│ 🔐 TUS CREDENCIALES DE ACCESO         │
├───────────────────────────────────────┤
│ 📧 Usuario: juan.perez@casabonita.pe │
│ 🔑 Contraseña: 123456                 │
└───────────────────────────────────────┘

        [🚀 Acceder al Sistema]

⚠️ IMPORTANTE: Deberás cambiar tu contraseña...

✨ ¿Qué puedes hacer en el sistema?
💰 Consultar tus comisiones en tiempo real
📊 Ver tus ventas y metas del mes
📈 Acceder a reportes personalizados
...
```

---

## 🐛 Troubleshooting

### **Error: "No se puede establecer una conexión"**

**Causa:** MySQL no está corriendo

**Solución:**
```bash
# Iniciar MySQL en XAMPP o Laragon
# O verificar con:
mysql -u root -p
```

---

### **Error: "Connection timeout" o "Connection refused"**

**Causa:** Problemas con servidor SMTP

**Soluciones:**

1. **Verificar credenciales Gmail:**
   ```bash
   # Asegúrate de usar "App Password" no la contraseña regular
   # Generar en: https://myaccount.google.com/apppasswords
   ```

2. **Probar conexión SMTP:**
   ```bash
   telnet smtp.gmail.com 587
   # Debe conectarse
   ```

3. **Verificar firewall:**
   ```bash
   # Permitir puerto 587 saliente
   ```

---

### **Error: "Class NewUserCredentialsMail not found"**

**Solución:**
```bash
# Regenerar autoload
composer dump-autoload

# Limpiar cache
php artisan config:clear
php artisan cache:clear
```

---

### **Los correos van a SPAM**

**Soluciones:**

1. Marcar como "No es spam" manualmente
2. Agregar remitente a contactos
3. En producción, configurar SPF/DKIM records

---

### **Error: "Unable to read file new-user-credentials.blade.php"**

**Solución:**
```bash
# Verificar que el archivo existe
ls resources/views/emails/new-user-credentials.blade.php

# Si no existe, crearlo manualmente o copiar del template
```

---

## 📊 Logs y Debugging

### **Ver logs en tiempo real:**

```bash
# Windows PowerShell
Get-Content storage/logs/laravel.log -Tail 50 -Wait

# Filtrar solo emails
Get-Content storage/logs/laravel.log | Select-String -Pattern "email|mail" -CaseSensitive:$false
```

### **Buscar emails enviados:**

```bash
grep "Email enviado exitosamente" storage/logs/laravel.log
```

### **Buscar errores:**

```bash
grep -i "error.*email\|error.*mail" storage/logs/laravel.log
```

---

## ✅ Criterios de Éxito

La prueba es exitosa si:

1. ✅ El comando artisan envía el email sin errores
2. ✅ El correo llega a la bandeja de entrada
3. ✅ El diseño se ve correctamente
4. ✅ Las credenciales son legibles
5. ✅ El botón redirige a http://localhost:4200
6. ✅ Los logs muestran "Email enviado exitosamente"

---

## 🎯 Siguiente Paso

Una vez confirmado que funciona en desarrollo:

1. **Actualizar .env de producción** con FRONTEND_URL correcto
2. **Probar en el droplet** con datos reales
3. **Importar empleados reales** y verificar que reciban correos
4. **Configurar queue workers** para envíos asíncronos (opcional)

---

## 📞 Soporte

Si encuentras problemas:

1. Revisar logs: `storage/logs/laravel.log`
2. Verificar configuración: `php artisan config:show mail`
3. Probar comando de prueba: `php artisan email:test-welcome`

---

**¡Todo listo para probar! 🚀**

*Última actualización: 13 de Noviembre de 2025*
