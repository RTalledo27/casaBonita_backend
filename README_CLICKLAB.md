Configuración de Clicklab (Infobip)

1. Variables de entorno

Agrega estas variables en `.env`:

CLICKLAB_PROVIDER=infobip
CLICKLAB_BASE_URL=https://69qmxz.api.infobip.com
CLICKLAB_API_KEY=tu_api_key
CLICKLAB_SMS_ENDPOINT=/sms/2/text/advanced
CLICKLAB_WHATSAPP_ENDPOINT=/whatsapp/1/message/text
CLICKLAB_EMAIL_ENDPOINT=/email/1/send
CLICKLAB_SMS_SENDER=+519XXXXXXXX
CLICKLAB_WHATSAPP_SENDER=+519XXXXXXXX
CLICKLAB_EMAIL_SENDER=notificaciones@casabonita.pe
CLICKLAB_NOTIFY_ON_USER_CREATE=true
CLICKLAB_NOTIFY_ON_USER_UPDATE_EMAIL=true
CLICKLAB_CHANNELS=email,whatsapp,sms

2. Endpoints

- Infobip (por defecto):
  - SMS: `/sms/2/text/advanced`
  - WhatsApp: `/whatsapp/1/message/text`
  - Email: `/email/1/send`
  - Header: `Authorization: App <CLICKLAB_API_KEY>`
  
- Genérico: si Clicklab expone otras rutas/headers, cambia `CLICKLAB_PROVIDER` y los `*_ENDPOINT`.

3. Uso

El flujo de creación y edición de usuarios envía credenciales por correo y, si está habilitado, por SMS y WhatsApp usando Clicklab.

4. Pruebas

Registra un usuario con teléfono en formato E.164. Verifica recepción en los canales habilitados.
