# Sistema de Migraciones Automáticas

## 📋 ¿Qué es esto?

Un sistema automático que ejecuta cambios en la base de datos cada vez que haces deploy. Ya no necesitas acordarte de ejecutar SQLs manualmente.

## 🚀 Uso Básico

### Hacer Deploy (RECOMENDADO)

```bash
.\deploy.bat
```

Esto automáticamente:
1. ✅ Ejecuta migraciones locales
2. ✅ Sube código a Git
3. ✅ Actualiza servidor Hostinger
4. ✅ Ejecuta migraciones en servidor

### Ver Estado de Migraciones

```bash
php database/migrate.php status
```

### Ejecutar Solo Migraciones

```bash
php database/migrate.php
```

## 📁 Estructura

```
database/
├── migrations/              # Archivos SQL de migraciones
│   ├── 001_add_user_rol_column.sql
│   └── 002_verificar_columna_rol.sql
├── migrate.php             # Sistema ejecutor
├── schema.sql              # Tabla de control
└── verificar_rol.php       # Verificador de columna
```

## 📝 Crear Nueva Migración

1. Crea un archivo en `database/migrations/`
2. Nómbralo con formato: `XXX_descripcion.sql`
3. Escribe tu SQL:

```sql
-- Migración: Descripción clara
-- Fecha: 2025-12-04

ALTER TABLE productos ADD COLUMN nuevo_campo VARCHAR(100);
```

4. Ejecuta deploy y se aplicará automáticamente

## 🔍 Verificar Problema Actual

Para verificar el problema de la columna 'rol':

```bash
php database/verificar_rol.php
```

Te dirá exactamente qué SQL ejecutar.

## ⚠️ Importante

- ✅ Las migraciones se ejecutan UNA SOLA VEZ
- ✅ Se ejecutan en ORDEN alfabético
- ✅ Si una falla, el proceso se detiene
- ✅ Cada migración es una TRANSACCIÓN (se revierte si falla)

## 🛠️ Soluciones Rápidas

### Si los cajeros siguen teniendo acceso:

1. Ejecuta: `php database/verificar_rol.php`
2. Copia el SQL que te muestra
3. Ejecútalo en phpMyAdmin
4. Haz deploy: `.\deploy.bat`

### Si una migración falla:

1. Revisa el error mostrado
2. Corrige el archivo SQL
3. Ejecuta deploy nuevamente

## 📊 Logs

El sistema muestra en pantalla:
- ✓ Migraciones ejecutadas exitosamente
- ✗ Errores con detalles
- 📊 Resumen de estado

## 🎯 Próximas Mejoras

- [ ] Sistema de rollback (deshacer migraciones)
- [ ] Migraciones con PHP (no solo SQL)
- [ ] Backup automático antes de migrar
- [ ] Notificaciones por email si falla
