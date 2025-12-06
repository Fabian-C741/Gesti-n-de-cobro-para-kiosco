# Panel Super Admin - Sistema Multi-Tenant SaaS

## 🎯 Descripción

Panel de administración para gestionar múltiples clientes (tenants) en el sistema de gestión de ventas. Permite crear clientes automáticamente con su propia base de datos aislada.

## 📋 Características

- ✅ **Gestión de Clientes (Tenants)**
  - Crear, editar, suspender y eliminar clientes
  - Provisión automática de base de datos para cada cliente
  - Creación automática de usuario administrador
  - Configuración de planes (Básico, Estándar, Premium)

- ✅ **Dashboard con Estadísticas**
  - Total de clientes activos/suspendidos
  - Ingresos mensuales
  - Pagos pendientes
  - Actividad reciente

- ✅ **Sistema de Planes**
  - **Básico:** $20/mes - 2 usuarios, 1000 productos, 1 PV
  - **Estándar:** $40/mes - 5 usuarios, 5000 productos, 3 PV
  - **Premium:** $80/mes - Ilimitado usuarios, productos, 10 PV

- ✅ **Gestión de Pagos**
  - Registro de pagos por cliente
  - Historial de transacciones
  - Estados: Pendiente, Aprobado, Rechazado

- ✅ **Logs de Actividad**
  - Seguimiento de todas las acciones
  - Registro de cambios de estado
  - Auditoría completa

## 🚀 Instalación

### Paso 1: Ejecutar SQL de Tablas

Ejecuta el archivo `private/create_saas_tables.sql` en tu base de datos maestra (la que ya tienes):

```bash
# Desde phpMyAdmin o consola MySQL
mysql -u tu_usuario -p tu_base_datos < private/create_saas_tables.sql
```

Este script crea:
- Tabla `tenants` (clientes)
- Tabla `super_admins` (administradores del SaaS)
- Tabla `tenant_logs` (logs de actividad)
- Tabla `tenant_pagos` (historial de pagos)
- Usuario super admin por defecto

### Paso 2: Acceder al Panel

URL: `https://tudominio.com/superadmin/login.php`

**Credenciales por defecto:**
- Usuario: `superadmin`
- Contraseña: `Admin123`

⚠️ **IMPORTANTE:** Cambia la contraseña inmediatamente después del primer acceso.

### Paso 3: Configurar Credenciales

Edita el archivo `superadmin/config_superadmin.php`:

```php
define('DB_HOST_MASTER', 'localhost');
define('DB_USER_MASTER', 'tu_usuario');
define('DB_PASS_MASTER', 'tu_contraseña');
define('DB_NAME_MASTER', 'tu_base_datos');
```

## 📖 Uso

### Crear un Nuevo Cliente

1. Ve a **Clientes** → **Nuevo Cliente**
2. Completa los datos:
   - Nombre comercial
   - Dominio (ej: `cliente1` → cliente1.tudominio.com)
   - Selecciona un plan
   - Datos del administrador
3. Haz clic en **Crear Cliente**

**¿Qué sucede al crear un cliente?**
1. Se crea un registro en la tabla `tenants`
2. Se crea una base de datos nueva (ej: `tenant_cliente1_abc123`)
3. Se importa el schema completo (tablas, índices, etc.)
4. Se crea un usuario administrador en la BD del cliente
5. Se crean sucursal y punto de venta por defecto
6. Se insertan categorías base
7. El cliente puede acceder inmediatamente a su sistema

### Gestionar Clientes

- **Ver Detalles:** Clic en el ícono de ojo 👁️
- **Editar:** Clic en el ícono de lápiz ✏️
- **Suspender/Activar:** Clic en el botón correspondiente
- **Filtrar:** Usa los filtros por estado, plan o búsqueda

### Registrar Pagos

1. Ve a **Pagos** → **Registrar Pago**
2. Selecciona el cliente
3. Ingresa monto, método de pago y período
4. El sistema extiende automáticamente la fecha de vencimiento

## 🔒 Seguridad

### Aislamiento de Datos

Cada cliente tiene su propia base de datos completamente aislada:
- ✅ No hay riesgo de mezcla de datos
- ✅ Backups independientes
- ✅ Restauración selectiva
- ✅ Eliminación limpia

### Niveles de Acceso

1. **Super Admin:** Acceso total al panel de administración
2. **Admin Cliente:** Administrador de su propio tenant
3. **Vendedor:** Usuario del sistema del cliente
4. **Cajero:** Usuario limitado del cliente

## 🔧 Próximas Funcionalidades

- [ ] Detección automática de tenant por subdominio
- [ ] Sistema de suscripciones con renovación automática
- [ ] Integración con MercadoPago/Stripe
- [ ] Backups automáticos por tenant
- [ ] Dashboard con gráficos avanzados
- [ ] Notificaciones por email
- [ ] API REST para gestión

## 📁 Estructura de Archivos

```
superadmin/
├── config_superadmin.php    # Configuración y funciones globales
├── login.php                # Login del super admin
├── logout.php               # Cerrar sesión
├── dashboard.php            # Dashboard principal
├── tenants.php              # Lista de clientes
├── crear_tenant.php         # Crear nuevo cliente
├── ver_tenant.php           # Detalles del cliente
├── editar_tenant.php        # Editar cliente
├── cambiar_estado_tenant.php # Suspender/Activar
├── pagos.php                # Gestión de pagos
└── logs.php                 # Logs de actividad

private/
└── create_saas_tables.sql   # Script SQL para crear tablas
```

## ❓ Preguntas Frecuentes

**¿Qué pasa si elimino un tenant?**
La base de datos del tenant NO se elimina automáticamente por seguridad. Debes eliminarla manualmente si estás seguro.

**¿Puedo migrar tenants entre servidores?**
Sí, solo necesitas exportar la BD del tenant e importarla en el nuevo servidor, luego actualizar el registro en la tabla `tenants`.

**¿Cómo cambio el plan de un cliente?**
Edita el tenant y selecciona el nuevo plan. Los límites se actualizan automáticamente.

**¿Los clientes comparten usuarios?**
No, cada tenant tiene su propia tabla de usuarios completamente independiente.

## 🆘 Soporte

Si encuentras algún problema, revisa:
1. Los logs de actividad en el panel
2. Los errores de PHP en `error_log`
3. Los permisos de la carpeta de subidas

## 📝 Changelog

### v1.0.0 (2024-12-03)
- ✅ Panel super admin completo
- ✅ Gestión de tenants (CRUD)
- ✅ Provisión automática de BD
- ✅ Sistema de planes
- ✅ Dashboard con estadísticas
- ✅ Logs de actividad
- ✅ Gestión de pagos

---

**Desarrollado para:** Sistema de Gestión de Ventas Multi-Tenant  
**Versión:** 1.0.0  
**Fecha:** Diciembre 2024
