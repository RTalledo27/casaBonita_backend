<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo-box {
            display: inline-block;
            border: 3px solid #fbbf24;
            border-radius: 8px;
            padding: 15px 25px;
            background-color: rgba(251, 191, 36, 0.1);
        }
        
        .logo-text {
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 0;
        }
        
        .logo-subtitle {
            color: #cbd5e1;
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .header-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 20px;
        }
        
        .message {
            color: #475569;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .reset-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1e293b;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
            box-shadow: 0 4px 6px rgba(251, 191, 36, 0.3);
        }
        
        .reset-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(251, 191, 36, 0.4);
        }
        
        .info-box {
            background-color: #fef3c7;
            border-left: 4px solid #fbbf24;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        
        .info-box p {
            color: #92400e;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .security-notice {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        
        .security-notice p {
            color: #991b1b;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .link-section {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .link-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 10px;
        }
        
        .link-text {
            font-size: 12px;
            color: #475569;
            word-break: break-all;
            background-color: #ffffff;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
        .footer {
            background-color: #1e293b;
            padding: 30px;
            text-align: center;
        }
        
        .footer-text {
            color: #94a3b8;
            font-size: 13px;
            margin: 5px 0;
        }
        
        .footer-warning {
            color: #fbbf24;
            font-size: 12px;
            margin-top: 15px;
            font-weight: 500;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 25px 0;
        }
        
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header-title {
                font-size: 24px;
            }
            
            .reset-button {
                display: block;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-container">
                <div class="logo-box">
                    <div class="logo-text">CASA BONITA</div>
                </div>
                <div class="logo-subtitle">R E S I D E N C I A L</div>
            </div>
            <h1 class="header-title">Recuperación de Contraseña</h1>
        </div>
        
        <!-- Content -->
        <div class="content">
            <p class="greeting">
                Hola <strong>{{ $user->first_name ?? $user->username }}</strong>,
            </p>
            
            <p class="message">
                Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>Casa Bonita Residencial</strong>.
            </p>
            
            <p class="message">
                Si fuiste tú quien solicitó este cambio, haz clic en el siguiente botón para crear una nueva contraseña:
            </p>
            
            <!-- Call to Action Button -->
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    🔒 Restablecer Contraseña
                </a>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <p><strong>⏰ Este enlace expirará en 60 minutos</strong> por razones de seguridad.</p>
            </div>
            
            <div class="divider"></div>
            
            <p class="message">
                Si no solicitaste el cambio de contraseña, puedes ignorar este correo con toda seguridad. Tu contraseña actual seguirá siendo válida y no se realizará ningún cambio.
            </p>
            
            <!-- Security Notice -->
            <div class="security-notice">
                <p><strong>⚠️ Por seguridad:</strong> Nunca compartas este correo con nadie. Nuestro equipo nunca te pedirá tu contraseña por correo electrónico o teléfono.</p>
            </div>
            
            <!-- Alternative Link -->
            <div class="link-section">
                <p class="link-label">Si tienes problemas con el botón, copia y pega este enlace en tu navegador:</p>
                <div class="link-text">{{ $resetUrl }}</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                <strong>Casa Bonita Residencial</strong><br>
                Sistema de Gestión Integral
            </p>
            <p class="footer-text" style="margin-top: 15px;">
                © {{ date('Y') }} Casa Bonita Residencial. Todos los derechos reservados.
            </p>
            <p class="footer-warning">
                Si no reconoces esta actividad, contacta inmediatamente con nuestro equipo de soporte.
            </p>
        </div>
    </div>
</body>
</html>
