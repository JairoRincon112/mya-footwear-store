@echo off
REM Arma el .zip para subir la tienda a Kyra Cloud (doble clic).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0empaquetar-despliegue.ps1"
pause
