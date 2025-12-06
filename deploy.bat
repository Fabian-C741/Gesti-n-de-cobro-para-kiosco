@echo off
echo ========================================
echo   ACTUALIZANDO SITIO EN HOSTINGER
echo ========================================
echo.

echo [1/4] Ejecutando migraciones locales...
php database\migrate.php
if %errorlevel% neq 0 (
    echo.
    echo ADVERTENCIA: Migraciones locales fallaron
    echo El deploy continuara pero revisa la base de datos
    echo.
    pause
)

REM Reemplaza PUERTO y SERVIDOR con tus datos SSH
set SSH_PORT=65002
set SSH_USER=u464516792
set SSH_HOST=srv1885.hstgr.io
set DEPLOY_PATH=/home/u464516792/domains/gestion-de-ventaspos.kcrsf.com/public_html

echo.
echo [2/4] Subiendo cambios a Git...
git add .
git commit -m "Deploy: %date% %time%"
git push origin main

echo.
echo [3/4] Conectando a Hostinger y ejecutando deploy completo...
ssh -p %SSH_PORT% -i "%USERPROFILE%\.ssh\hostinger_key" %SSH_USER%@%SSH_HOST% "cd %DEPLOY_PATH% && git fetch --depth=1 origin main && git reset --hard origin/main && echo '=== Codigo actualizado ===' && git log -1 --oneline && echo '' && echo 'Ejecutando migraciones...' && php database/migrate.php"

echo.
echo ========================================
echo   SITIO ACTUALIZADO EXITOSAMENTE
echo ========================================
pause
