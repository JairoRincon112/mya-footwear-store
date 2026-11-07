# Arma el .zip para subir la tienda a Kyra Cloud (pestaña Archivos del servidor).
#
# Pasos que hace:
#   1. Compila el frontend (npm run build) en public/mya.
#   2. Revisa que no queden cambios sin commit: el zip sale del último commit,
#      así lo que se publica es lo mismo que está en GitHub.
#   3. Crea mya-footwear-store.zip en esta carpeta (herramientas/).
#
# Después: subir el zip en Kyra -> Archivos (en la raíz, /var/www/html),
# descomprimirlo y correr en la Terminal:
#   cd /var/www/html && unzip -oq mya-footwear-store.zip && rm mya-footwear-store.zip
#   cd backend && composer install --no-dev --optimize-autoloader --no-interaction

$ErrorActionPreference = 'Stop'
$raiz = Split-Path $PSScriptRoot -Parent
$zip = Join-Path $PSScriptRoot 'mya-footwear-store.zip'

Write-Host 'Compilando el frontend...' -ForegroundColor Cyan
Push-Location (Join-Path $raiz 'frontend')
try {
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Fallo npm run build.' }
} finally {
    Pop-Location
}

Push-Location $raiz
try {
    $pendientes = git status --porcelain
    if ($pendientes) {
        Write-Host ''
        Write-Host 'Hay cambios sin commit (incluida la compilacion nueva):' -ForegroundColor Yellow
        Write-Host ($pendientes -join "`n")
        Write-Host ''
        Write-Host 'Haz commit y git push, y vuelve a ejecutar este script.' -ForegroundColor Yellow
        exit 1
    }

    git archive --format=zip -o $zip HEAD
    if ($LASTEXITCODE -ne 0) { throw 'Fallo git archive.' }
} finally {
    Pop-Location
}

$tamano = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host ''
Write-Host "Listo: $zip ($tamano MB)" -ForegroundColor Green
Write-Host 'Subelo en Kyra Cloud -> servidor mya-footwear-store -> Archivos.'
