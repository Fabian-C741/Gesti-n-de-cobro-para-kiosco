@echo off
echo ========================================
echo   LIMPIEZA DE HISTORIAL GIT - SEGURIDAD
echo ========================================
echo.
echo Este script creara un repositorio limpio sin credenciales
echo en el historial.
echo.
echo IMPORTANTE: 
echo 1. Ya debes haber cambiado la contrasena en Hostinger
echo 2. Ya debes haber actualizado el archivo .env
echo 3. Se creara una copia de respaldo en gestion-de-cobros-backup
echo.
pause

echo.
echo [1/5] Creando copia de respaldo...
xcopy /E /I /Y . "..\gestion-de-cobros-backup" >nul 2>&1
if errorlevel 1 (
    echo ERROR: No se pudo crear backup
    pause
    exit /b 1
)
echo OK - Backup creado en gestion-de-cobros-backup

echo.
echo [2/5] Eliminando historial de Git local...
rmdir /S /Q .git
echo OK - Historial local eliminado

echo.
echo [3/5] Inicializando repositorio limpio...
git init
git add .
git commit -m "Initial commit - Sistema de Gestion de Cobros v1.0 (credenciales protegidas en .env)"
echo OK - Nuevo repositorio creado

echo.
echo [4/5] Conectando con GitHub...
git remote add origin https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco.git
echo OK - Remoto configurado

echo.
echo [5/5] Subiendo version limpia (FORCE PUSH)...
echo.
echo ATENCION: Se va a REEMPLAZAR todo el historial en GitHub
echo Presiona Ctrl+C para cancelar, o
pause

git push -u origin main --force
if errorlevel 1 (
    echo.
    echo ERROR: Fallo el push. Verifica tu conexion y permisos.
    echo.
    echo Si dice "rejected", ejecuta:
    echo git push -u origin main --force --allow-unrelated-histories
    pause
    exit /b 1
)

echo.
echo ========================================
echo   LIMPIEZA COMPLETADA EXITOSAMENTE
echo ========================================
echo.
echo VERIFICACION:
echo 1. Ve a GitHub y verifica que solo hay 1 commit
echo 2. Busca "GestionVentas987" en el repositorio - NO debe aparecer
echo 3. Elimina la carpeta gestion-de-cobros-backup cuando estes seguro
echo.
echo PROXIMOS PASOS:
echo 1. Actualizar servidor: git pull (va a requerir --force)
echo 2. Crear .env en servidor con nueva contrasena
echo 3. Revisar logs de Hostinger por accesos sospechosos
echo.
pause
