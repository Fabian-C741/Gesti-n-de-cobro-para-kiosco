# Configurar SSH para Deploy Automático

## Paso 1: Generar clave SSH (si no tienes una)

Abre PowerShell y ejecuta:

```powershell
ssh-keygen -t rsa -b 4096 -C "tu_email@ejemplo.com"
```

Presiona Enter 3 veces (acepta ubicación por defecto y sin contraseña)

## Paso 2: Copiar la clave al servidor

```powershell
# Mostrar tu clave pública
cat $env:USERPROFILE\.ssh\id_rsa.pub
```

Copia TODO el contenido que aparece (empieza con `ssh-rsa`)

## Paso 3: Agregar la clave al servidor

1. Conéctate a tu servidor con FileZilla o cPanel
2. Ve a la carpeta: `/home/u464516792/.ssh/`
3. Si no existe, créala con permisos 700
4. Edita el archivo `authorized_keys` (créalo si no existe)
5. Pega tu clave pública al final del archivo
6. Guarda y asegúrate de que el archivo tenga permisos 600

**Alternativa rápida con cPanel:**

1. Entra a cPanel
2. Busca "SSH Access" o "Administrador de claves SSH"
3. Click en "Import Key"
4. Pega tu clave pública
5. Autorízala

## Paso 4: Probar la conexión

```powershell
ssh -p 65002 u464516792@srv1885.hstgr.io
```

Si NO te pide contraseña, ¡está configurado correctamente!

## Paso 5: Crear el sistema de migraciones en el servidor

Ya lo hicimos, pero asegúrate de que exista:
- `/home/u464516792/domains/gestion-de-ventaspos.kcrsf.com/public_html/database/migrate.php`
- `/home/u464516792/domains/gestion-de-ventaspos.kcrsf.com/public_html/database/migrations/`

## Paso 6: Configurar Git en el servidor (una sola vez)

Conéctate por SSH y ejecuta:

```bash
cd /home/u464516792/domains/gestion-de-ventaspos.kcrsf.com/public_html
git config pull.rebase false
git config user.email "tu_email@ejemplo.com"
git config user.name "Tu Nombre"
```

## ¡Listo! Ahora puedes usar deploy.bat

Cada vez que hagas cambios:

```powershell
git add .
git commit -m "Descripción de cambios"
git push
.\deploy.bat
```

El script deploy.bat hará TODO automáticamente:
1. Conectará al servidor
2. Descargará los últimos cambios
3. Ejecutará las migraciones de base de datos
4. ¡Todo listo para usar!

---

## Solución de Problemas

### "Permission denied (publickey)"
- No configuraste la clave SSH correctamente
- Vuelve al Paso 2 y 3

### "Could not resolve hostname"
- Verifica tu conexión a internet
- Verifica que el dominio sea correcto

### "php: command not found"
- Tu hosting no tiene PHP en PATH
- Usa la ruta completa: `/usr/bin/php` o `/usr/local/bin/php`
- Contacta a soporte de Hostinger para saber la ruta correcta

### Las migraciones no se ejecutan
- Asegúrate de que la carpeta `database/migrations/` tenga permisos 755
- Verifica que `migrate.php` tenga permisos 644
