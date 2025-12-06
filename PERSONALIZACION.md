# Sistema de Personalización

## Características Implementadas

### 1. Botón de Cerrar Sesión ✅
- Ya existe en el sidebar (parte inferior)
- Ubicación: `admin/includes/header.php` línea 53
- Ruta: `../includes/logout.php`

### 2. Editor de Personalización 🎨

Ubicado en: **Admin → Personalización**

#### Opciones disponibles:

**Colores:**
- Color Primario
- Color Secundario
- Color Éxito
- Color Peligro
- Sidebar - Color Inicio (gradiente)
- Sidebar - Color Fin (gradiente)

**Estilos:**
- Radio de bordes de botones (0-50px)
- Radio de bordes de tarjetas (0-50px)

**Imágenes:**
- Fondo del Login (1920x1080px recomendado)
- Logo del Sistema (200x200px PNG transparente recomendado)

**Vista Previa:**
- Los cambios se visualizan en tiempo real
- Botón "Restaurar Valores" para resetear

## Instalación en Hostinger

### 1. Crear tabla de personalización

Ejecutar en **phpMyAdmin**:

```sql
-- Copiar contenido de: sql/add_personalization_table.sql
```

### 2. Subir archivos nuevos

Subir a `public_html/`:

```
admin/personalizacion.php
includes/personalization.php
assets/css/custom.css
uploads/backgrounds/
uploads/logos/
```

### 3. Actualizar archivos existentes

Reemplazar:
```
admin/includes/header.php
login.php
```

### 4. Permisos de carpetas

Asegurar permisos de escritura (755 o 775):
```
uploads/backgrounds/
uploads/logos/
assets/css/
```

## Uso

### Cambiar Colores
1. Ir a **Admin → Personalización**
2. Click en el selector de color
3. Elegir color deseado
4. Ver preview en tiempo real
5. Guardar cambios

### Subir Fondo de Login
1. Click en "Fondo del Login"
2. Seleccionar imagen (JPG, PNG)
3. Guardar cambios
4. El fondo aparecerá en la página de login

### Restaurar Valores
1. Click en "Restaurar Valores"
2. Confirmar
3. Todos los valores vuelven al diseño original

## Archivos Creados

```
sql/add_personalization_table.sql    - Script SQL
admin/personalizacion.php             - Panel de personalización
includes/personalization.php          - Funciones helper
assets/css/custom.css                 - CSS generado dinámicamente
uploads/backgrounds/.gitkeep          - Carpeta de fondos
uploads/logos/.gitkeep                - Carpeta de logos
```

## Archivos Modificados

```
admin/includes/header.php             - Agregado enlace + CSS custom
login.php                             - Agregado fondo personalizado
```

## Notas Técnicas

- Los cambios se guardan en la tabla `personalizacion`
- El CSS se genera dinámicamente en `assets/css/custom.css`
- Las imágenes se suben a `uploads/backgrounds/` y `uploads/logos/`
- Validaciones: formatos JPG/PNG, tamaño máximo según config
- Logs de actividad registran todos los cambios

## Seguridad

- Solo administradores tienen acceso
- Protección CSRF en formularios
- Validación de tipos de archivo
- Sanitización de valores de color
- Logs de auditoría de cambios
