# Deploy Script para Casa Bonita Backend API (PowerShell)
# Uso: .\deploy.ps1 [production|staging]

param(
    [Parameter(Position=0)]
    [ValidateSet("production", "staging")]
    [string]$Environment = "production"
)

# Configuración de colores
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

function Test-Command {
    param([string]$Command)
    try {
        Get-Command $Command -ErrorAction Stop | Out-Null
        return $true
    }
    catch {
        return $false
    }
}

function Test-EnvironmentVariables {
    Write-Log "Verificando variables de entorno en Vercel..."
    
    $requiredVars = @(
        "APP_NAME",
        "APP_ENV",
        "APP_KEY",
        "DB_CONNECTION",
        "DB_HOST",
        "DB_DATABASE",
        "DB_USERNAME",
        "DB_PASSWORD"
    )
    
    try {
        $envList = vercel env ls 2>$null
        $missingVars = @()
        
        foreach ($var in $requiredVars) {
            if ($envList -notmatch $var) {
                $missingVars += $var
            }
        }
        
        if ($missingVars.Count -gt 0) {
            Write-Log "Variables de entorno faltantes:" "ERROR"
            foreach ($var in $missingVars) {
                Write-Host "  - $var" -ForegroundColor Red
            }
            Write-Host ""
            Write-Host "Configúralas con: vercel env add <VARIABLE_NAME>" -ForegroundColor Yellow
            throw "Variables de entorno faltantes"
        }
        
        Write-Log "Variables de entorno verificadas" "SUCCESS"
    }
    catch {
        Write-Log "Error verificando variables de entorno: $($_.Exception.Message)" "ERROR"
        throw
    }
}

function Invoke-PrepareCode {
    Write-Log "Preparando código para deploy..."
    
    # Verificar si hay cambios sin commitear (solo si es un repo git)
    if (Test-Path ".git") {
        $gitStatus = git status --porcelain 2>$null
        if ($gitStatus) {
            Write-Log "Hay cambios sin commitear. ¿Continuar? (y/N)" "WARNING"
            $response = Read-Host
            if ($response -notmatch "^[Yy]$") {
                Write-Log "Deploy cancelado" "ERROR"
                exit 1
            }
        }
    }
    
    # Instalar dependencias de producción
    Write-Log "Instalando dependencias de producción..."
    try {
        composer install --no-dev --optimize-autoloader --no-scripts
        if ($LASTEXITCODE -ne 0) { throw "Error en composer install" }
    }
    catch {
        Write-Log "Error instalando dependencias: $($_.Exception.Message)" "ERROR"
        throw
    }
    
    # Generar autoload optimizado
    Write-Log "Optimizando autoload..."
    try {
        composer dump-autoload --optimize --classmap-authoritative
        if ($LASTEXITCODE -ne 0) { throw "Error en dump-autoload" }
    }
    catch {
        Write-Log "Error optimizando autoload: $($_.Exception.Message)" "ERROR"
        throw
    }
    
    Write-Log "Código preparado" "SUCCESS"
}

function Invoke-Tests {
    if (Test-Path "phpunit.xml") {
        Write-Log "¿Ejecutar tests antes del deploy? (y/N)" "WARNING"
        $response = Read-Host
        if ($response -match "^[Yy]$") {
            Write-Log "Ejecutando tests..."
            try {
                composer test
                if ($LASTEXITCODE -ne 0) { throw "Tests fallaron" }
                Write-Log "Tests pasaron" "SUCCESS"
            }
            catch {
                Write-Log "Tests fallaron. Deploy cancelado." "ERROR"
                throw
            }
        }
    }
}

function Invoke-BackupDatabase {
    if ($Environment -eq "production") {
        Write-Log "¿Crear backup de la base de datos antes del deploy? (Y/n)" "WARNING"
        $response = Read-Host
        if ($response -notmatch "^[Nn]$") {
            Write-Log "Creando backup de la base de datos..."
            # Aquí puedes agregar tu lógica de backup específica
            Write-Log "Implementa la lógica de backup específica para tu base de datos" "WARNING"
        }
    }
}

function Invoke-Deploy {
    Write-Log "Iniciando deploy en Vercel..."
    
    try {
        if ($Environment -eq "production") {
            vercel --prod --yes
        } else {
            vercel --yes
        }
        
        if ($LASTEXITCODE -ne 0) { throw "Error en deploy" }
        Write-Log "Deploy completado" "SUCCESS"
    }
    catch {
        Write-Log "Error en deploy: $($_.Exception.Message)" "ERROR"
        throw
    }
}

function Test-Deploy {
    Write-Log "Verificando deploy..."
    
    try {
        # Obtener información del proyecto
        $projectInfo = vercel ls --scope=$(vercel whoami) 2>$null
        $currentDir = Split-Path -Leaf (Get-Location)
        
        # Buscar la URL del proyecto actual
        $projectLine = $projectInfo | Where-Object { $_ -match $currentDir -and $_ -match "READY" } | Select-Object -First 1
        
        if ($projectLine) {
            $url = ($projectLine -split "\s+")[1]
            Write-Log "Verificando que la API responde en: https://$url"
            
            # Verificar que la API responde
            try {
                $response = Invoke-WebRequest -Uri "https://$url/api/health" -Method GET -TimeoutSec 30 -ErrorAction Stop
                if ($response.StatusCode -eq 200) {
                    Write-Log "API responde correctamente" "SUCCESS"
                } else {
                    Write-Log "La API respondió con código: $($response.StatusCode)" "WARNING"
                }
            }
            catch {
                Write-Log "La API no responde en /api/health. Verifica manualmente." "WARNING"
            }
            
            Write-Host ""
            Write-Host "🚀 Deploy completado!" -ForegroundColor Green
            Write-Host "📍 URL: https://$url" -ForegroundColor Cyan
            Write-Host "📊 Dashboard: https://vercel.com/dashboard" -ForegroundColor Cyan
            Write-Host "📝 Logs: vercel logs $currentDir" -ForegroundColor Cyan
        } else {
            Write-Log "No se pudo obtener la URL del deploy automáticamente" "WARNING"
            Write-Host "📊 Verifica el deploy en: https://vercel.com/dashboard" -ForegroundColor Cyan
        }
    }
    catch {
        Write-Log "Error verificando deploy: $($_.Exception.Message)" "WARNING"
    }
}

function Show-RollbackInfo {
    Write-Log "¿Algo salió mal? Puedes hacer rollback con:" "ERROR"
    Write-Host "  vercel rollback" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "O desde el dashboard de Vercel:" -ForegroundColor Yellow
    Write-Host "  https://vercel.com/dashboard" -ForegroundColor Cyan
}

# Función principal
function Main {
    Write-Host "🏠 Casa Bonita Backend Deploy Script" -ForegroundColor Green
    Write-Host "===================================" -ForegroundColor Green
    Write-Host ""
    
    Write-Log "Iniciando deploy para ambiente: $Environment"
    
    # Verificar que estamos en el directorio correcto
    if (-not (Test-Path "composer.json")) {
        Write-Log "No se encontró composer.json. Asegúrate de estar en el directorio del backend." "ERROR"
        exit 1
    }
    
    # Verificar herramientas requeridas
    if (-not (Test-Command "vercel")) {
        Write-Log "Vercel CLI no está instalado. Instálalo con: npm i -g vercel" "ERROR"
        exit 1
    }
    
    if (-not (Test-Command "composer")) {
        Write-Log "Composer no está instalado. Instálalo desde: https://getcomposer.org/" "ERROR"
        exit 1
    }
    
    # Verificar login en Vercel
    try {
        $whoami = vercel whoami 2>$null
        if ($LASTEXITCODE -ne 0) {
            Write-Log "No estás logueado en Vercel. Ejecutando login..."
            vercel login
        }
    }
    catch {
        Write-Log "Error verificando login de Vercel" "ERROR"
        exit 1
    }
    
    try {
        # Ejecutar pasos del deploy
        Test-EnvironmentVariables
        Invoke-PrepareCode
        Invoke-Tests
        Invoke-BackupDatabase
        
        # Confirmación final
        if ($Environment -eq "production") {
            Write-Log "¿Estás seguro de que quieres hacer deploy a PRODUCCIÓN? (y/N)" "WARNING"
            $response = Read-Host
            if ($response -notmatch "^[Yy]$") {
                Write-Log "Deploy cancelado" "ERROR"
                exit 1
            }
        }
        
        Invoke-Deploy
        Test-Deploy
        
        Write-Log "¡Deploy completado exitosamente!" "SUCCESS"
    }
    catch {
        Write-Log "Deploy falló: $($_.Exception.Message)" "ERROR"
        Show-RollbackInfo
        exit 1
    }
}

# Ejecutar función principal
Main