# =====================================================================
# Publica la tienda en internet con un túnel de Cloudflare (gratis, sin cuenta).
#
#   1. Enciende MySQL y Apache de XAMPP si están apagados.
#   2. Abre el túnel hacia http://127.0.0.1:8081, el puerto de Apache que solo
#      sirve la tienda (/mya) y la API (/mya-api). phpMyAdmin y el resto de
#      XAMPP NO quedan expuestos (ver C:\xampp\apache\conf\extra\httpd-vhosts.conf).
#   3. Escribe la dirección pública en backend/.env (FRONTEND_URL) para que los
#      enlaces de los correos apunten a ella, y la muestra en pantalla.
#
# La dirección (https://....trycloudflare.com) cambia cada vez que se inicia.
# El túnel funciona mientras este computador esté encendido.
# Para detenerlo: detener-servidor-publico.bat
# =====================================================================
$ErrorActionPreference = 'Stop'

$proyecto = Split-Path -Parent $PSScriptRoot
$cloudflared = Join-Path $env:USERPROFILE 'cloudflared\cloudflared.exe'
$log = Join-Path $env:TEMP 'mya-tunel.log'
$xampp = 'C:\xampp'

if (-not (Test-Path $cloudflared)) {
    Write-Host 'No se encontró cloudflared en' $cloudflared -ForegroundColor Red
    Write-Host 'Descárgalo de https://github.com/cloudflare/cloudflared/releases (cloudflared-windows-amd64.exe)'
    exit 1
}

# 1. MySQL y Apache
if (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) {
    Write-Host 'Encendiendo MySQL...'
    Start-Process -FilePath "$xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=$xampp\mysql\bin\my.ini", '--standalone' -WindowStyle Hidden
}
if (-not (Get-Process httpd -ErrorAction SilentlyContinue)) {
    Write-Host 'Encendiendo Apache...'
    Start-Process -FilePath "$xampp\apache\bin\httpd.exe" -WorkingDirectory "$xampp\apache" -WindowStyle Hidden
}
Start-Sleep -Seconds 3

try {
    $salud = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8081/mya-api/api/salud' -TimeoutSec 10
} catch {
    Write-Host 'La API no responde en http://127.0.0.1:8081. Revisa que Apache esté encendido.' -ForegroundColor Red
    exit 1
}

# 2. Túnel (si ya había uno abierto, se cierra para no duplicarlo)
Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force
Remove-Item $log -ErrorAction SilentlyContinue
Write-Host 'Abriendo el túnel de Cloudflare...'
Start-Process -FilePath $cloudflared -ArgumentList 'tunnel', '--no-autoupdate', '--url', 'http://127.0.0.1:8081', '--logfile', $log -WindowStyle Hidden

$url = $null
for ($i = 0; $i -lt 40 -and -not $url; $i++) {
    Start-Sleep -Seconds 1
    if (Test-Path $log) {
        $coincidencia = Select-String -Path $log -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1
        if ($coincidencia) { $url = $coincidencia.Matches[0].Value }
    }
}
if (-not $url) {
    Write-Host "No se obtuvo la dirección del túnel. Revisa el registro: $log" -ForegroundColor Red
    exit 1
}

# 3. FRONTEND_URL en backend/.env (enlaces de recuperación de contraseña)
$env_archivo = Join-Path $proyecto 'backend\.env'
$contenido = [IO.File]::ReadAllText($env_archivo)
$contenido = [regex]::Replace($contenido, '(?m)^FRONTEND_URL=.*$', "FRONTEND_URL=$url/mya")
[IO.File]::WriteAllText($env_archivo, $contenido, (New-Object Text.UTF8Encoding $false))

Write-Host ''
Write-Host '  Tienda publicada en internet:' -ForegroundColor Green
Write-Host "    $url/mya/" -ForegroundColor Yellow
Write-Host ''
Write-Host "  API:            $url/mya-api/api/salud"
Write-Host "  Webhook Wompi:  $url/mya-api/api/pagos/wompi/eventos"
Write-Host ''
Write-Host '  La dirección cambia cada vez que se inicia el túnel.'
Write-Host '  Para cerrarlo: detener-servidor-publico.bat'
Set-Content -Path (Join-Path $PSScriptRoot 'direccion-publica.txt') -Value "$url/mya/" -Encoding utf8
