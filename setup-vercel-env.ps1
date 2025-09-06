# Script para configurar variables de entorno en Vercel
# Uso: .\setup-vercel-env.ps1

param(
    [Parameter()]
    [string]$EnvFile = ".env.production.example"
)

$ErrorActionPreference = "Stop"

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    switch ($Level) {
        "SUCCESS" { Write-Host "[$timestamp] [SUCCESS] $Message" -ForegroundColor Green }
        "WARNING" { Write-Host "[$timestamp] [WARNING] $Message" -ForegroundColor Yellow }
        "ERROR" { Write-Host "[$timestamp] [ERROR] $Message" -ForegroundColor Red }
        default { Write-Host "[$timestamp] [INFO] $Message" -ForegroundColor Blue }
    }
}

function Test-VercelCLI {
    try {
        $null = vercel --version 2>$null
        return $true
    }
    catch {
        return $false
    }
}

function Get-EnvVariables {
    param([string]$FilePath)
    
    if (-not (Test-Path $FilePath)) {
        Write-Log "Archivo $FilePath no encontrado" "ERROR"
        return @()
    }
    
    $variables = @()
    $content = Get-Content $FilePath
    
    foreach ($line in $content) {
        # Ignorar comentarios y líneas vacías
        if ($line -match '^\s*#' -or $line -match '^\s*$') {
            continue
        }
        
        # Extraer nombre de variable
        if ($line -match '^([A-Z_][A-Z0-9_]*)=') {
            $variables += $matches[1]
        }
    }
    
    return $variables
}

function Set-VercelEnvVariable {
    param(
        [string]$Name,
        [string]$Value,
        [string]$Environment = "production"
    )
    
    try {
        Write-Log "Configurando $Name para $Environment..."
        
        # Usar echo para pasar el valor a vercel env add
        $process = Start-Process -FilePath "vercel" -ArgumentList "env", "add", $Name, $Environment -NoNewWindow -Wait -PassThru
        
        if ($process.ExitCode -eq 0) {
            Write-Log "$Name configurado exitosamente" "SUCCESS"
            return $true
        } else {
            Write-Log "Error configurando $Name" "ERROR"
            return $false
        }
    }
    catch {
        Write-Log "Error configurando $Name: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

function Show-RequiredVariables {
    $required = @(
        "APP_NAME",
        "APP_ENV",
        "APP_KEY",
        "APP_URL",
        "DB_CONNECTION",
        "DB_HOST",
        "DB_PORT",
        "DB_DATABASE",
        "DB_USERNAME",
        "DB_PASSWORD"
    )
    
    Write-Host ""
    Write-Host "📋 Variables de entorno REQUERIDAS:" -ForegroundColor Yellow
    Write-Host "=====================================" -ForegroundColor Yellow
    
    foreach ($var in $required) {
        Write-Host "  ✓ $var" -ForegroundColor Cyan
    }
    
    Write-Host ""
    Write-Host "💡 Consejos:" -ForegroundColor Green
    Write-Host "  • APP_KEY: Genera con 'php artisan key:generate --show'" -ForegroundColor Gray
    Write-Host "  • APP_URL: Será tu dominio de Vercel (ej: https://tu-app.vercel.app)" -ForegroundColor Gray
    Write-Host "  • DB_*: Credenciales de tu base de datos de producción" -ForegroundColor Gray
    Write-Host ""
}

function Show-OptionalVariables {
    $optional = @(
        "MAIL_MAILER",
        "MAIL_HOST",
        "MAIL_PORT",
        "MAIL_USERNAME",
        "MAIL_PASSWORD",
        "CORS_ALLOWED_ORIGINS",
        "JWT_SECRET",
        "LOG_LEVEL"
    )
    
    Write-Host "📋 Variables OPCIONALES (recomendadas):" -ForegroundColor Yellow
    Write-Host "======================================" -ForegroundColor Yellow
    
    foreach ($var in $optional) {
        Write-Host "  • $var" -ForegroundColor Gray
    }
    Write-Host ""
}

function Start-InteractiveSetup {
    Write-Host "🚀 Configuración Interactiva de Variables de Entorno" -ForegroundColor Green
    Write-Host "===================================================" -ForegroundColor Green
    Write-Host ""
    
    # Variables críticas que necesitan configuración manual
    $criticalVars = @{
        "APP_NAME" = "Casa Bonita API"
        "APP_ENV" = "production"
        "APP_KEY" = ""
        "APP_URL" = ""
        "DB_CONNECTION" = "mysql"
        "DB_HOST" = ""
        "DB_PORT" = "3306"
        "DB_DATABASE" = ""
        "DB_USERNAME" = ""
        "DB_PASSWORD" = ""
    }
    
    foreach ($var in $criticalVars.Keys) {
        $defaultValue = $criticalVars[$var]
        
        if ($defaultValue) {
            $prompt = "$var [$defaultValue]: "
        } else {
            $prompt = "$var (REQUERIDO): "
        }
        
        $value = Read-Host $prompt
        
        if (-not $value -and $defaultValue) {
            $value = $defaultValue
        }
        
        if ($value) {
            Write-Host "Configurando $var en Vercel..." -ForegroundColor Blue
            
            # Para variables sensibles, usar input seguro
            if ($var -match "PASSWORD|SECRET|KEY") {
                Write-Host "⚠️  Configura manualmente: vercel env add $var" -ForegroundColor Yellow
                Write-Host "   Valor: [OCULTO POR SEGURIDAD]" -ForegroundColor Gray
            } else {
                Write-Host "   Valor: $value" -ForegroundColor Gray
                Write-Host "⚠️  Configura manualmente: vercel env add $var" -ForegroundColor Yellow
            }
            Write-Host ""
        } else {
            Write-Log "$var es requerido pero no se proporcionó" "WARNING"
        }
    }
}

function Show-ManualCommands {
    Write-Host "📝 Comandos para configurar manualmente:" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    
    $commands = @(
        "vercel env add APP_NAME",
        "vercel env add APP_ENV",
        "vercel env add APP_KEY",
        "vercel env add APP_URL",
        "vercel env add DB_CONNECTION",
        "vercel env add DB_HOST",
        "vercel env add DB_PORT",
        "vercel env add DB_DATABASE",
        "vercel env add DB_USERNAME",
        "vercel env add DB_PASSWORD"
    )
    
    foreach ($cmd in $commands) {
        Write-Host "  $cmd" -ForegroundColor Cyan
    }
    
    Write-Host ""
    Write-Host "💡 Después de configurar las variables:" -ForegroundColor Yellow
    Write-Host "  vercel env ls    # Ver variables configuradas" -ForegroundColor Gray
    Write-Host "  vercel env pull  # Descargar variables localmente" -ForegroundColor Gray
    Write-Host ""
}

# Función principal
function Main {
    Write-Host "🏠 Casa Bonita - Configuración de Variables de Entorno" -ForegroundColor Green
    Write-Host "======================================================" -ForegroundColor Green
    Write-Host ""
    
    # Verificar Vercel CLI
    if (-not (Test-VercelCLI)) {
        Write-Log "Vercel CLI no está instalado. Instálalo con: npm i -g vercel" "ERROR"
        exit 1
    }
    
    # Verificar login
    try {
        $whoami = vercel whoami 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Log "No estás logueado en Vercel. Ejecuta: vercel login" "ERROR"
            exit 1
        }
        Write-Log "Logueado como: $whoami" "SUCCESS"
    }
    catch {
        Write-Log "Error verificando login de Vercel" "ERROR"
        exit 1
    }
    
    # Mostrar información
    Show-RequiredVariables
    Show-OptionalVariables
    
    # Preguntar modo de configuración
    Write-Host "¿Cómo quieres configurar las variables?" -ForegroundColor Yellow
    Write-Host "1. Configuración interactiva (recomendado)" -ForegroundColor Cyan
    Write-Host "2. Mostrar comandos manuales" -ForegroundColor Cyan
    Write-Host "3. Salir" -ForegroundColor Gray
    Write-Host ""
    
    $choice = Read-Host "Selecciona una opción (1-3)"
    
    switch ($choice) {
        "1" {
            Start-InteractiveSetup
        }
        "2" {
            Show-ManualCommands
        }
        "3" {
            Write-Log "Configuración cancelada" "INFO"
            exit 0
        }
        default {
            Write-Log "Opción inválida" "ERROR"
            exit 1
        }
    }
    
    Write-Host "✅ Configuración completada!" -ForegroundColor Green
    Write-Host ""
    Write-Host "🚀 Próximos pasos:" -ForegroundColor Yellow
    Write-Host "  1. Verifica las variables: vercel env ls" -ForegroundColor Gray
    Write-Host "  2. Ejecuta el deploy: .\deploy.ps1 production" -ForegroundColor Gray
    Write-Host "  3. Verifica el checklist: PRE-DEPLOY-CHECKLIST.md" -ForegroundColor Gray
}

# Ejecutar función principal
Main