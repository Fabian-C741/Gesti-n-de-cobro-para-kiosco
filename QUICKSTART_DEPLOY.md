# 🚀 GUÍA RÁPIDA: Despliegue Automático

## ¿Qué archivos debo subir manualmente la primera vez?

### 📦 Primera Instalación en Hostinger:

Sube estos archivos **UNA SOLA VEZ** a `public_html/`:

```
✅ deploy.php (copia de deploy.php.example, con tu token)
✅ config/config.php (ya lo tienes, con credenciales de BD)
```

**¡Eso es todo!** El resto se actualiza automáticamente.

---

## 🔧 Configuración Rápida (5 minutos)

### **Paso 1: Crear deploy.php en Hostinger**

1. **Descarga** `deploy.php.example` de GitHub
2. **Renombra** a `deploy.php`
3. **Edita** línea 11:
   ```php
   define('SECRET_TOKEN', 'tu_token_secreto_123456');
   ```
4. **Sube** a Hostinger: `public_html/deploy.php`

### **Paso 2: Configurar Webhook en GitHub**

1. Ve a: https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco/settings/hooks
2. Click **Add webhook**
3. Pega esto:

```
Payload URL: https://gestion-de-ventaspos.kcrsf.com/deploy.php?token=tu_token_secreto_123456
Content type: application/json
Secret: tu_token_secreto_123456
Events: ☑️ Just the push event
Active: ☑️ Marcar
```

4. Click **Add webhook**
5. ✅ Listo!

---

## ✨ Cómo Funciona Ahora

### **ANTES (Manual):**
```
1. Editas código en VS Code
2. git push a GitHub  
3. 👉 Abres FileZilla/cPanel
4. 👉 Subes CADA archivo manualmente
5. 👉 Esperas 10-15 minutos
```

### **AHORA (Automático):**
```
1. Editas código en VS Code
2. git push a GitHub
3. ✨ ¡Listo! Hostinger se actualiza SOLO en 5 segundos
```

---

## 📋 Flujo de Trabajo Diario

```bash
# 1. Haces cambios en tus archivos
# 2. Guardas y subes a Git:

git add .
git commit -m "Descripción del cambio"
git push origin main

# 3. ¡Espera 5 segundos!
# 4. Tu sitio ya está actualizado: https://gestion-de-ventaspos.kcrsf.com
```

---

## 🔍 Ver si Funcionó

### Opción 1: Ver logs
```
https://gestion-de-ventaspos.kcrsf.com/deploy.log
```

### Opción 2: GitHub
1. Ve a: https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco/settings/hooks
2. Click en tu webhook
3. Tab **Recent Deliveries**
4. Verás:
   - ✅ Verde = Exitoso
   - ❌ Rojo = Error

---

## ⚠️ Archivos que NUNCA se actualizan automáticamente

Estos archivos están en `.gitignore` y se mantienen en Hostinger:

```
❌ config/config.php        (credenciales de BD)
❌ uploads/*                (imágenes subidas por usuarios)
❌ deploy.php               (script con tu token)
❌ *.log                    (logs del sistema)
```

**¿Por qué?** Porque contienen datos específicos del servidor y no deben estar en Git.

---

## 🆘 Solución de Problemas

### "El webhook no funciona"
1. Verifica que el token en `deploy.php` coincida con el webhook
2. Verifica que `deploy.php` tenga permisos 755
3. Mira los logs en GitHub: Settings → Webhooks → Recent Deliveries

### "Permission denied"
```bash
# Conecta por SSH a Hostinger:
ssh u464516792@gestion-de-ventaspos.kcrsf.com
cd public_html
chmod 755 deploy.php
```

### "No se actualizó el CSS personalizado"
El deploy.php regenera automáticamente `assets/css/custom.css`

---

## 🎯 Resumen

| Archivo | ¿Dónde está? | ¿Se actualiza? | ¿Subirlo manualmente? |
|---------|--------------|----------------|----------------------|
| `*.php` (código) | GitHub + Hostinger | ✅ Auto | ❌ No |
| `config/config.php` | Solo Hostinger | ❌ Nunca | ✅ Una vez |
| `deploy.php` | Solo Hostinger | ❌ Nunca | ✅ Una vez |
| `uploads/*` | Solo Hostinger | ❌ Nunca | ❌ No (usuarios suben) |
| `database.sql` | GitHub | ❌ Solo lectura | ❌ No |

---

## 🎉 Beneficios

✅ **Actualización en 5 segundos** vs 15 minutos manual
✅ **Sin errores** de olvidar archivos
✅ **Logs automáticos** de cada despliegue
✅ **Rollback fácil** si algo falla (git revert)
✅ **Trabajo en equipo** simplificado

---

**¿Preguntas?** Lee el archivo completo: `README_DEPLOY.md`
