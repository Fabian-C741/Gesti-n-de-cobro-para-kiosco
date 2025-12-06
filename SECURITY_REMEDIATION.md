# 🚨 PLAN DE REMEDIACIÓN DE SEGURIDAD

## ⚠️ PROBLEMA DETECTADO
Las credenciales de la base de datos están expuestas en el historial completo de Git del repositorio público.

**Credenciales comprometidas:**
- Usuario: `u464516792_gestion`
- Contraseña: `GestionVentas987#`
- Base de datos: `u464516792_produccion`

## 📋 PASOS OBLIGATORIOS (EN ORDEN)

### 1. CAMBIAR CONTRASEÑAS INMEDIATAMENTE ⚡

**En Hostinger:**
1. Ir a Panel de Control → Bases de Datos → Usuarios
2. Cambiar contraseña del usuario `u464516792_gestion`
3. Usar contraseña fuerte (mínimo 20 caracteres, símbolos, números)
4. Ejemplo: `K9$mP#vL2&xQ8@nR4wT7yU1zA3`

**Actualizar archivo `.env` local:**
```bash
DB_PASS_MASTER=NUEVA_CONTRASEÑA_AQUI
DB_PASS=NUEVA_CONTRASEÑA_AQUI
```

### 2. LIMPIAR HISTORIAL DE GIT 🧹

**Opción A: BFG Repo-Cleaner (RECOMENDADO)**
```bash
# Instalar BFG
# Descargar de: https://rtyley.github.io/bfg-repo-cleaner/

# Hacer backup
git clone --mirror https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco.git repo-backup

# Limpiar credenciales
java -jar bfg.jar --replace-text passwords.txt Gesti-n-de-cobro-para-kiosco.git

# Force push
cd Gesti-n-de-cobro-para-kiosco.git
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push --force
```

**Opción B: git filter-repo (alternativa)**
```bash
pip install git-filter-repo

git filter-repo --invert-paths --path config/config.php --path superadmin/config_superadmin.php --path tenant_login.php --force

git push --force origin main
```

**Opción C: EMPEZAR DE CERO (más simple)**
```bash
# 1. Renombrar repositorio actual en GitHub a "Gestion-cobro-OLD"
# 2. Crear nuevo repositorio vacío "Gestion-de-cobro-para-kiosco"
# 3. Subir SOLO el último commit:

cd 'd:\Proyectos 2\gestion-de-cobros'
rm -rf .git
git init
git add .
git commit -m "Initial commit - Sistema de Gestión de Cobros (credenciales protegidas)"
git remote add origin https://github.com/Fabian-C741/Gesti-n-de-cobro-para-kiosco.git
git push -u origin main --force
```

### 3. VERIFICAR SEGURIDAD ✅

**Verificar que `.env` NO esté en Git:**
```bash
git log --all --full-history -- .env
# Debe decir: "No hay commits"
```

**Buscar credenciales en historial:**
```bash
git log -p | grep -i "GestionVentas987"
# Debe estar vacío
```

**Verificar .gitignore:**
```bash
cat .gitignore | grep ".env"
# Debe mostrar: .env
```

### 4. ACTUALIZAR SERVIDOR DE PRODUCCIÓN 🚀

**En Hostinger via SSH:**
```bash
cd public_html
git pull origin main
cp .env.example .env
nano .env  # Editar con credenciales NUEVAS
chmod 600 .env
```

### 5. MONITOREO POST-INCIDENTE 🔍

**Revisar logs de acceso sospechoso:**
- Panel Hostinger → Logs → Acceso a Base de Datos
- Buscar IPs desconocidas
- Revisar queries sospechosas en últimas 24-48 horas

**Cambiar también:**
- Contraseña de Super Admin
- Tokens de acceso activos
- Cualquier API key del sistema

## 📝 CHECKLIST FINAL

- [ ] Contraseña de BD cambiada en Hostinger
- [ ] Archivo `.env` actualizado localmente
- [ ] Historial de Git limpiado (una de las 3 opciones)
- [ ] Force push realizado
- [ ] Verificado que credenciales no aparecen en GitHub
- [ ] Servidor de producción actualizado con nueva contraseña
- [ ] Logs revisados por actividad sospechosa
- [ ] Super Admin password cambiado
- [ ] Tokens regenerados

## ⏱️ TIEMPO ESTIMADO
- **Urgente:** 15-30 minutos
- **Completo:** 1-2 horas

## 🆘 SOPORTE
Si tienes dudas, consulta:
- SECURITY.md - Guía de configuración segura
- GitHub: Limpiar historial - https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository

---
**Fecha de detección:** 6 de diciembre de 2025
**Severidad:** CRÍTICA 🔴
**Estado:** PENDIENTE REMEDIACIÓN
