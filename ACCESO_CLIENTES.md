# 🏢 Acceso Multi-Tenant - Guía para Clientes

## 📌 ¿Cómo acceder a mi sistema POS?

### 🔐 Paso 1: Ir al Login de Clientes

Accede a: `https://gestion-de-ventaspos.kcrsf.com/tenant_login.php`

O desde la página principal: `https://gestion-de-ventaspos.kcrsf.com/`

### 📝 Paso 2: Ingresar tus credenciales

Necesitarás **3 datos**:

1. **Dominio de tu negocio**: El identificador único que te dio el administrador
   - Ejemplo: `mikiosco`, `almacencentral`, `despensalucia`
   - Solo minúsculas y sin espacios

2. **Usuario o Email**: Tu nombre de usuario o email registrado
   - Ejemplo: `admin@minegocio.com` o `administrador`

3. **Contraseña**: La que configuraste al crear tu cuenta

---

## 🎯 Ejemplo de Acceso

Si creaste un cliente de prueba con dominio **`clienteprueba`**:

```
┌─────────────────────────────────────┐
│  Dominio: clienteprueba             │
│  Usuario: admin@clienteprueba.com   │
│  Contraseña: [tu contraseña]        │
└─────────────────────────────────────┘
```

---

## 👥 Usuarios que pueden acceder

Cada cliente tiene usuarios con diferentes roles:

| Rol | Descripción | Acceso |
|-----|-------------|--------|
| **Admin** | Administrador del negocio | Panel completo de administración |
| **Vendedor** | Empleado de ventas | Punto de venta y reportes |
| **Cajero** | Cajero | Solo punto de venta |

---

## 🔧 Proceso de Creación de Cliente (Para Super Admin)

Cuando creas un cliente desde el panel Super Admin:

1. **Se crea automáticamente**:
   - Base de datos: `[dominio]_pos`
   - Usuario admin con email y contraseña
   - Todas las tablas del sistema

2. **El cliente recibe**:
   - Dominio de acceso
   - Email del administrador
   - Contraseña temporal (debe cambiarla)

3. **Para acceder**:
   - URL: `https://gestion-de-ventaspos.kcrsf.com/tenant_login.php`
   - Ingresa dominio + credenciales

---

## 🌐 Estructura del Sistema SaaS

```
┌─────────────────────────────────────────────┐
│  SISTEMA MAESTRO                            │
│  Base de Datos: u464516792_produccion      │
│  ┌─────────────────────────────────────┐   │
│  │ Tabla: tenants                      │   │
│  │ - ID, Nombre, Dominio, Plan         │   │
│  │ - BD Config, Estado, Fechas         │   │
│  └─────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
              │
              │ Provisiona
              ↓
┌─────────────────────────────────────────────┐
│  CLIENTE 1: "mikiosco"                      │
│  BD: mikiosco_pos                           │
│  └─ Usuarios, Productos, Ventas, etc.       │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  CLIENTE 2: "almacencentral"                │
│  BD: almacencentral_pos                     │
│  └─ Usuarios, Productos, Ventas, etc.       │
└─────────────────────────────────────────────┘
```

---

## ❓ Preguntas Frecuentes

### ¿Qué es el "dominio"?
Es tu identificador único en el sistema. Ejemplo: si tu negocio es "Kiosco La Esquina", tu dominio podría ser `kioscolaesquina`

### ¿Puedo cambiar mi contraseña?
Sí, desde tu panel de perfil una vez que inicies sesión

### ¿Qué pasa si olvido mi contraseña?
Contacta al administrador del sistema para restablecerla

### ¿Puedo tener varios usuarios?
Sí, el administrador de tu negocio puede crear usuarios adicionales (vendedores, cajeros) desde el panel

### ¿Mi información está aislada de otros clientes?
Sí, cada cliente tiene su propia base de datos completamente aislada

---

## 🚀 Funcionalidades Disponibles

Una vez dentro, tendrás acceso a:

✅ **Gestión de Productos** - Agregar, editar, eliminar productos
✅ **Punto de Venta** - Realizar ventas rápidamente
✅ **Control de Inventario** - Stock en tiempo real
✅ **Reportes** - Ventas diarias, por periodo, por producto
✅ **Gestión de Usuarios** - Crear vendedores y cajeros
✅ **Categorías** - Organizar productos
✅ **Puntos de Venta** - Múltiples cajas

---

## 📞 Soporte

Si tienes problemas para acceder:
- Verifica que tu cuenta esté activa (estado: ACTIVO)
- Confirma que tu dominio sea correcto
- Contacta al administrador del sistema

---

**Sistema POS Multi-Tenant SaaS** 🏪
Plataforma escalable para gestión de negocios
