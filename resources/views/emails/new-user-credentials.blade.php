<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a Casa Bonita</title>
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
        
        .header-emoji {
            font-size: 48px;
            margin-bottom: 10px;
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
            margin-bottom: 20px;
        }
        
        .credentials-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            box-shadow: 0 4px 6px rgba(251, 191, 36, 0.2);
        }
        
        .credentials-title {
            font-size: 16px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #ffffff;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .credential-label {
            font-size: 13px;
            color: #78716c;
            font-weight: 600;
        }
        
        .credential-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .login-button {
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
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(251, 191, 36, 0.4);
        }
        
        .info-box {
            background-color: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        
        .info-box p {
            color: #1e40af;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .warning-box {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        
        .warning-box p {
            color: #991b1b;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .features-list {
            background-color: #f8fafc;
            padding: 25px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .features-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }
        
        .feature-item {
            display: flex;
            align-items: start;
            margin-bottom: 12px;
            color: #475569;
            font-size: 14px;
        }
        
        .feature-icon {
            margin-right: 10px;
            font-size: 18px;
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
        
        .footer-contact {
            color: #fbbf24;
            font-size: 14px;
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
            
            .login-button {
                display: block;
                width: 100%;
            }
            
            .credential-row {
                flex-direction: column;
                align-items: start;
            }
            
            .credential-value {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="header-emoji">🎉</div>
            <div class="logo-container">
                <div class="logo-box">
                    <div class="logo-text">CASA BONITA</div>
                </div>
                <div class="logo-subtitle">R E S I D E N C I A L</div>
            </div>
            <h1 class="header-title">¡Bienvenido al Equipo!</h1>
        </div>
        
        <!-- Content -->
        <div class="content">
            <p class="greeting">
                ¡Hola <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>! 👋
            </p>
            
            <p class="message">
                Nos complace darte la bienvenida a <strong>Casa Bonita Residencial</strong>. Tu cuenta ha sido creada exitosamente y ya puedes acceder a nuestro sistema de gestión.
            </p>
            
            <!-- Credentials Box -->
            <div class="credentials-box">
                <div class="credentials-title">🔐 TUS CREDENCIALES DE ACCESO</div>
                
                <div class="credential-row">
                    <span class="credential-label">📧 Usuario / Email:</span>
                    <span class="credential-value">{{ $user->email }}</span>
                </div>
                
                <div class="credential-row">
                    <span class="credential-label">🔑 Contraseña Temporal:</span>
                    <span class="credential-value">{{ $temporaryPassword }}</span>
                </div>
            </div>
            
            <!-- Call to Action Button -->
            <div class="button-container">
                <a href="{{ $loginUrl }}" class="login-button">
                    🚀 Acceder al Sistema
                </a>
            </div>
            
            <!-- Important Notice -->
            <div class="warning-box">
                <p><strong>⚠️ IMPORTANTE:</strong> Por seguridad, deberás cambiar tu contraseña la primera vez que inicies sesión. Asegúrate de crear una contraseña segura y única.</p>
            </div>
            
            <div class="divider"></div>
            
            <!-- Features List -->
            <div class="features-list">
                <div class="features-title">✨ ¿Qué puedes hacer en el sistema?</div>
                
                <div class="feature-item">
                    <span class="feature-icon">💰</span>
                    <span>Consultar tus comisiones y bonos en tiempo real</span>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">📊</span>
                    <span>Ver tus ventas y metas del mes</span>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">📈</span>
                    <span>Acceder a reportes y estadísticas personalizadas</span>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">🔔</span>
                    <span>Recibir notificaciones de pagos y actualizaciones</span>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">👤</span>
                    <span>Actualizar tu información personal</span>
                </div>
                
                <div class="feature-item">
                    <span class="feature-icon">📱</span>
                    <span>Acceder desde cualquier dispositivo</span>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <p><strong>📌 Datos de tu perfil:</strong></p>
                <p>• <strong>Nombre:</strong> {{ $user->first_name }} {{ $user->last_name }}</p>
                @if($user->dni)
                <p>• <strong>DNI:</strong> {{ $user->dni }}</p>
                @endif
                @if($user->position)
                <p>• <strong>Cargo:</strong> {{ $user->position }}</p>
                @endif
                @if($user->hire_date)
                <p>• <strong>Fecha de Ingreso:</strong> {{ \Carbon\Carbon::parse($user->hire_date)->format('d/m/Y') }}</p>
                @endif
            </div>
            
            <div class="divider"></div>
            
            <p class="message">
                Si tienes alguna pregunta o necesitas ayuda para acceder al sistema, no dudes en contactarnos.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                <strong>Casa Bonita Residencial</strong><br>
                Sistema de Gestión Integral
            </p>
            <p class="footer-contact">
                📧 Soporte: romaim.talledo@casabonita.pe
            </p>
            <p class="footer-text" style="margin-top: 15px;">
                © {{ date('Y') }} Casa Bonita Residencial. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>
