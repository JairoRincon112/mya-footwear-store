@echo off
REM Cierra el tunel publico (doble clic).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0detener-servidor-publico.ps1"
pause
