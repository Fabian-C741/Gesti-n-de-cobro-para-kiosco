# Guía de Despliegue Automático desde GitHub a Hostinger

## 🚀 Opciones de Despliegue

Tienes 3 opciones para mantener tu sitio actualizado:

---

## **OPCIÓN 1: Despliegue Automático con Webhooks (Recomendado)** ⚡

### Ventajas:
- ✅ Actualización automática al hacer `git push`
- ✅ No requiere acceso manual a Hostinger
- ✅ Logs de cada despliegue
- ✅ Verificación de seguridad con token

### Configuración:

#### **Paso 1: Configurar el Token Secreto**

1. Abre `deploy.php`
2. Cambia esta línea:
```php
define('SECRET_TOKEN', 'CAMBIAR_ESTE_TOKEN_SECRETO_12345');
```
Por algo como:
```php
define('SECRET_TOKEN', 'mi_token_super_secreto_2025_xyz');
```

#### **Paso 2: Subir deploy.php a Hostinger**

Subir el archivo a:
```
public_html/deploy.php
```

#### **Paso 3: Dar Permisos**

En **File Manager** de Hostinger o por SSH:
```bash
chmod 755 public_html/deploy.php
```

#### **Paso 4: Configurar Webhook en GitHub**

1. Ve a tu repositorio: https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco
2. Click en **Settings** (Configuración)
3. Click en **Webhooks** (menú izquierdo)
4. Click en **Add webhook**
5. Configurar:

```
Payload URL: https://gestion-de-ventaspos.kcrsf.com/deploy.php?token=mi_token_super_secreto_2025_xyz
Content type: application/json
Secret: mi_token_super_secreto_2025_xyz
SSL verification: Enable
Events: Just the push event
Active: ✓ Marcar
```

6. Click en **Add webhook**

#### **Paso 5: Probar**

1. Haz un cambio en cualquier archivo
2. Ejecuta:
```bash
git add .
git commit -m "Prueba de deploy automático"
git push origin main
```

3. Ve a: https://gestion-de-ventaspos.kcrsf.com/deploy.php
4. Verás el log de despliegue

5. En GitHub, ve a **Settings → Webhooks**, verás:
   - ✓ Verde = Exitoso
   - ✗ Rojo = Error

#### **Ver Logs de Despliegue:**

Abre en navegador:
```
https://gestion-de-ventaspos.kcrsf.com/deploy.log
```

---

## **OPCIÓN 2: Git Pull Manual desde Hostinger SSH** 🖥️

### Ventajas:
- ✅ Control total sobre cuándo actualizar
- ✅ No requiere webhooks

### Configuración:

#### **Paso 1: Conectar por SSH a Hostinger**

Desde tu terminal local:
```bash
ssh u464516792@gestion-de-ventaspos.kcrsf.com
```

#### **Paso 2: Clonar el repositorio (primera vez)**

```bash
cd public_html
git clone https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco.git temp
mv temp/* .
mv temp/.* .
rm -rf temp
```

#### **Paso 3: Actualizar cuando quieras**

Cada vez que hagas cambios en GitHub:

```bash
ssh u464516792@gestion-de-ventaspos.kcrsf.com
cd public_html
git pull origin main
```

---

## **OPCIÓN 3: Subir Archivos Manualmente (Actual)** 📁

### Lo que haces ahora:

1. Editas archivos localmente
2. Haces `git push` a GitHub
3. **Manualmente** subes archivos por FTP/File Manager a Hostinger

### Desventaja:
- ❌ Debes subir CADA archivo modificado manualmente

---

## **¿Cuál elegir?**

| Opción | Velocidad | Automático | Dificultad | Recomendado |
|--------|-----------|------------|------------|-------------|
| **Webhook (Opción 1)** | ⚡ Instantáneo | ✅ Sí | 🟡 Media | ⭐⭐⭐⭐⭐ |
| **SSH Git Pull (Opción 2)** | ⚡ Rápido | ❌ No | 🟢 Fácil | ⭐⭐⭐ |
| **Manual (Opción 3)** | 🐌 Lento | ❌ No | 🟢 Fácil | ⭐ |

---

## **Mi Recomendación: OPCIÓN 1 (Webhook)**

### Flujo de trabajo con Webhook:

1. **Editas código localmente** en VS Code
2. **Git commit + push:**
   ```bash
   git add .
   git commit -m "Mejora X"
   git push origin main
   ```
3. **🎉 ¡GitHub notifica a Hostinger automáticamente!**
4. **Hostinger ejecuta `deploy.php`** que hace:
   - `git pull` del código nuevo
   - Actualiza permisos de carpetas
   - Regenera CSS personalizado
   - Guarda log

**¡Tu sitio se actualiza solo en segundos! ⚡**

---

## **Archivos que NO se suben a Git (en .gitignore)**

```
config/          # Credenciales de BD (nunca subir)
uploads/         # Imágenes subidas por usuarios
*.log           # Logs
.env            # Variables de entorno
```

Estos archivos ya están en Hostinger y NO se sobrescriben.

---

## **Solución de Problemas**

### Error: "Permission denied"
```bash
chmod 755 deploy.php
chmod 755 -R uploads/
```

### Error: "git command not found"
Contactar a Hostinger para habilitar Git en SSH.

### Webhook no funciona
1. Verificar token en URL y en código
2. Ver logs en GitHub: Settings → Webhooks → Recent Deliveries
3. Ver deploy.log en Hostinger

---

## **Seguridad del deploy.php**

- ✅ Requiere token secreto
- ✅ Verifica firma de GitHub (SHA256)
- ✅ Solo acepta POST
- ✅ Solo actualiza rama 'main'
- ✅ Guarda logs de auditoría

**Importante:** ¡Nunca subas el token a Git! Está en deploy.php que NO debe ir a GitHub.

---

## **Resumen de Archivos**

### Para GitHub (ya están):
```
✅ Todos los .php (excepto config/)
✅ .gitignore
✅ database.sql
✅ README.md
```

### Solo en Hostinger (NO en Git):
```
❌ config/config.php (credenciales)
❌ deploy.php (script de despliegue)
❌ uploads/* (imágenes)
❌ *.log (logs)
```

---

¿Prefieres que te ayude a configurar la **Opción 1 (Webhook)** paso a paso?
