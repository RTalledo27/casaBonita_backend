<x-mail::message>
# Recuperación de Contraseña

Hola **{{ $user->first_name ?? $user->username }}**,

Recibimos una solicitud para restablecer la contraseña de tu cuenta en **Casa Bonita Residencial**.

Si fuiste tú quien solicitó este cambio, haz clic en el siguiente botón para crear una nueva contraseña:

<x-mail::button :url="$resetUrl" color="success">
Restablecer Contraseña
</x-mail::button>

Este enlace expirará en **60 minutos** por razones de seguridad.

Si no solicitaste el cambio de contraseña, puedes ignorar este correo. Tu contraseña actual seguirá siendo válida.

---

**Por seguridad, nunca compartas este correo con nadie.**

Si tienes problemas para hacer clic en el botón, copia y pega el siguiente enlace en tu navegador:

{{ $resetUrl }}

Saludos,<br>
**Equipo de Casa Bonita Residencial**

<x-mail::subcopy>
Si no reconoces esta actividad, por favor contacta inmediatamente con nuestro equipo de soporte.
</x-mail::subcopy>
</x-mail::message>
