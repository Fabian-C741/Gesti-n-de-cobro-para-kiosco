# 🔒 Configuración de Seguridad

## ⚠️ IMPORTANTE - Variables de Entorno

Este proyecto usa variables de entorno para proteger credenciales sensibles. **NUNCA** subas el archivo `.env` a Git.

### Instalación Inicial

1. **Copia el archivo de ejemplo:**
   ```bash
   cp .env.example .env
   ```

2. **Edita `.env` con tus credenciales reales:**
   ```bash
   nano .env
   # o usa tu editor favorito
   ```

3. **Configura las siguientes variables:**
   - `DB_HOST_MASTER`: Host de la base de datos master
   - `DB_USER_MASTER`: Usuario de la base de datos master
   - `DB_PASS_MASTER`: **Contraseña de la base de datos master**
   - `DB_NAME_MASTER`: Nombre de la base de datos master
   - `APP_ENV`: `local` o `production`
   - `APP_URL`: URL de tu aplicación

### Archivos Protegidos

Los siguientes archivos están en `.gitignore` y **NO deben subirse a Git**:

- `.env` - Credenciales de producción
- `.env.local` - Credenciales de desarrollo local
- `.env.production` - Credenciales de producción alternativas
- `config/` - Toda la carpeta de configuración (excepto ejemplos)

### Despliegue en Producción

1. **En el servidor, crea el archivo `.env` manualmente:**
   ```bash
   nano .env
   ```

2. **Copia las credenciales de producción** (nunca del repositorio Git)

3. **Verifica permisos del archivo:**
   ```bash
   chmod 600 .env
   chown www-data:www-data .env
   ```

### ⚠️ Si Ya Subiste Credenciales a Git

Si ya subiste credenciales al repositorio, sigue estos pasos:

1. **Cambia TODAS las contraseñas inmediatamente**
2. **Limpia el historial de Git** (contacta al administrador)
3. **Revisa los logs del servidor** por accesos sospechosos

### Buenas Prácticas

✅ **SÍ hacer:**
- Usar variables de entorno para credenciales
- Subir `.env.example` con valores de ejemplo
- Documentar variables necesarias
- Usar contraseñas fuertes y únicas

❌ **NO hacer:**
- Hardcodear credenciales en código PHP
- Subir `.env` a Git
- Compartir credenciales por chat/email
- Usar la misma contraseña en dev y producción

### Soporte

Si tienes dudas sobre la configuración de seguridad, contacta al administrador del sistema.
