<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificación de contacto</title>
  <style>
    body { font-family: Arial, sans-serif; color: #111827; }
    .container { max-width: 640px; margin: 0 auto; padding: 24px; }
    .code { font-size: 24px; font-weight: bold; letter-spacing: 4px; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Verificación de información</h2>
    <p>Hola {{ $client_name ?? 'cliente' }},</p>
    <p>Para confirmar el cambio de tu {{ $type === 'email' ? 'correo electrónico' : 'número de teléfono' }}, utiliza el siguiente código:</p>
    <p class="code">{{ $code }}</p>
    <p>Este código expira el {{ $expires_at }}.</p>
    <p>Si no solicitaste este cambio, ignora este mensaje.</p>
  </div>
</body>
</html>

