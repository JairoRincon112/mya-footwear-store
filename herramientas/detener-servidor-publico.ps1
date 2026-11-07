# Cierra el túnel de Cloudflare: la tienda deja de estar en internet.
# Apache y MySQL siguen encendidos para trabajar en local (http://localhost/mya).
$proyecto = Split-Path -Parent $PSScriptRoot

Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force

# Los enlaces de los correos vuelven a apuntar a la dirección local
$env_archivo = Join-Path $proyecto 'backend\.env'
$contenido = [IO.File]::ReadAllText($env_archivo)
$contenido = [regex]::Replace($contenido, '(?m)^FRONTEND_URL=.*$', 'FRONTEND_URL=http://localhost/mya')
[IO.File]::WriteAllText($env_archivo, $contenido, (New-Object Text.UTF8Encoding $false))
Remove-Item (Join-Path $PSScriptRoot 'direccion-publica.txt') -ErrorAction SilentlyContinue

Write-Host 'Túnel cerrado. La tienda ya no está en internet.' -ForegroundColor Green
