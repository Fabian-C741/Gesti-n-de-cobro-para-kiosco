# Sistema de Gestión de Cobros

Sistema POS completo para kioscos y supermercados con gestión de ventas, productos, usuarios y reportes.

## 🚀 Características

- 📱 **Responsive**: Compatible con móviles, tablets y escritorio
- 🔐 **Seguro**: Protección XSS, CSRF, SQL Injection
- 👥 **Multi-rol**: Administrador, Vendedor, Cajero
- 📊 **Reportes**: Estadísticas y ranking de empleados
- 🖼️ **Imágenes**: Subida de productos y personalización
- 🧾 **Tickets**: Impresión térmica con código de barras

## 📋 Requisitos

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+
- Extensiones: PDO, PDO_MySQL, GD, mbstring

## 🔧 Instalación

### 1. Clonar Repositorio

```bash
git clone https://github.com/tuusuario/gestion-cobros.git
cd gestion-cobros
```

### 2. Configurar Base de Datos

1. Crea una base de datos MySQL
2. Importa `database.sql` y `sql/configuracion_avanzada.sql`
3. Edita `config/config.php` con tus credenciales

### 3. Configurar Permisos

```bash
chmod -R 755 uploads/
```

### 4. Acceso Inicial

Accede al sistema y crea tu usuario administrador siguiendo las instrucciones en pantalla.

## 📁 Estructura

```
gestion-de-cobros/
├── admin/          # Panel administrativo
├── vendedor/       # Panel vendedor
├── cajero/         # Panel cajero (POS)
├── api/            # Endpoints API
├── config/         # Configuración
├── includes/       # Funciones compartidas
├── uploads/        # Archivos subidos
└── sql/            # Scripts SQL
```

## 📝 Configuración

Revisa `config/config.php` para ajustar:
- Tiempo de sesión
- Tamaño máximo de archivos
- Formatos de imagen permitidos
- Configuraciones de seguridad

## 🔐 Seguridad

- Protección contra XSS, CSRF, SQL Injection
- Rate limiting y bloqueo de IPs
- Sesiones seguras con HTTPOnly
- Validación de archivos subidos
- Headers de seguridad configurados

## 📄 Licencia

Código abierto disponible para uso personal y comercial.

---

**Sistema POS para Kioscos y Supermercados** 🏪
