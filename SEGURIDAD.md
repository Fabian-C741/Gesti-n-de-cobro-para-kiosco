# Guía de Seguridad - Sistema de Gestión de Cobros

## 🔐 Protecciones Implementadas

### 1. Protección contra Inyección SQL
✅ **Implementado**: PDO con prepared statements en todas las consultas
- Todas las consultas usan `$db->prepare()` y `->execute()`
- Sanitización de entradas con `sanitize_input()`
- Validación de tipos de datos antes de consultas

**Ejemplo de protección:**
```php
$stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
```

### 2. Protección contra XSS (Cross-Site Scripting)
✅ **Implementado**: Sanitización de salidas
- `htmlspecialchars()` en todas las salidas HTML
- Validación de entrada con `sanitize_input()`
- Content Security Policy (CSP) en headers
- Detección de patrones de ataque

**Archivos clave:**
- `includes/functions.php` - Función `sanitize_input()`
- `.htaccess` - Headers de seguridad CSP
- `includes/security.php` - Detección de patrones

### 3. Protección contra CSRF (Cross-Site Request Forgery)
✅ **Implementado**: Tokens CSRF en formularios
- Generación de tokens únicos por sesión
- Verificación en cada petición POST
- Tokens regenerados periódicamente

**Implementación:**
```php
// Generar token
$csrf_token = generate_csrf_token();

// Verificar token
verify_csrf_token($_POST['csrf_token'])
```

### 4. Protección Anti-Brute Force
✅ **Implementado**: Límite de intentos de login
- **Máximo de intentos**: 5 intentos fallidos
- **Tiempo de bloqueo**: 15 minutos
- **Tracking por email**: Cada email tiene su contador
- **Logs de seguridad**: Registra intentos sospechosos

**Configuración** (`config/config.php`):
```php
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
```

### 5. Rate Limiting (Anti-DDoS Básico)
✅ **Implementado**: Límite de peticiones por IP
- **Límite**: 100 peticiones por minuto por IP
- **Aplicado en**: login.php y páginas críticas
- **Tabla en BD**: `rate_limit` trackea peticiones
- **Auto-limpieza**: Registros antiguos se eliminan automáticamente

**Funciones principales** (`includes/security.php`):
- `check_rate_limit()` - Verifica límite de peticiones
- `log_security_event()` - Registra eventos sospechosos

### 6. Lista Negra de IPs
✅ **Implementado**: Bloqueo automático de IPs maliciosas
- **Tabla**: `ip_blacklist`
- **Bloqueo temporal**: Configurable (default 24 horas)
- **Bloqueo permanente**: Disponible para IPs persistentes
- **Auto-desbloqu**: IPs se desbloquean automáticamente

**Uso:**
```php
// Bloquear IP por 24 horas
add_to_blacklist($db, $ip, 'Múltiples intentos de ataque', 24);

// Verificar si IP está bloqueada
check_ip_blacklist($db, $ip);
```

### 7. Protección de Archivos Sensibles
✅ **Implementado**: .htaccess bloquea accesos directos
- Archivos de configuración protegidos
- Carpeta `.git` bloqueada
- Archivos `.sql`, `.log`, `.ini` no accesibles
- Listado de directorios deshabilitado

**Archivos protegidos:**
- `config/config.php`
- `database.sql`
- `.env` (si lo usas)
- `.git/`
- `*.log`, `*.bak`, `*.backup`

### 8. Subida Segura de Archivos
✅ **Implementado**: Validación múltiple de imágenes
- **Validación de extensión**: Solo JPG, PNG, GIF, WebP
- **Validación de tamaño**: Máximo 5MB
- **Validación MIME**: `getimagesize()` verifica que sea imagen real
- **Nombres únicos**: `uniqid()` previene sobrescritura
- **Sanitización**: Nombres de archivo limpiados

**Código** (`vendedor/productos.php`):
```php
validate_image_extension($archivo['name']);
getimagesize($archivo['tmp_name']);
generate_unique_filename($archivo['name']);
```

### 9. Sesiones Seguras
✅ **Implementado**: Configuración robusta de sesiones
- **HTTPOnly cookies**: No accesibles desde JavaScript
- **SameSite**: Strict para prevenir CSRF
- **Expiración**: 8 horas (configurable)
- **IDs largos**: 48 caracteres, 6 bits por carácter
- **Regeneración**: ID se regenera después de login
- **Tracking**: IP y User-Agent almacenados

**Configuración** (`config/config.php`):
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.sid_length', 48);
```

### 10. Headers de Seguridad HTTP
✅ **Implementado**: Headers protectores en `.htaccess`

**Headers configurados:**
- `X-Frame-Options: SAMEORIGIN` - Previene clickjacking
- `X-Content-Type-Options: nosniff` - Previene MIME sniffing
- `X-XSS-Protection: 1; mode=block` - Protección XSS del navegador
- `Content-Security-Policy` - Limita recursos cargables
- `Referrer-Policy` - Controla información de referencia
- `Permissions-Policy` - Deshabilita APIs no necesarias

### 11. Protección contra Path Traversal
✅ **Implementado**: Validación de rutas y detección
- Detección de `../` y `..\` en inputs
- Validación de rutas de archivos
- Bloqueo en `.htaccess` para query strings sospechosos

### 12. Logs de Seguridad
✅ **Implementado**: Sistema completo de auditoría

**Tablas de logs:**
- `security_logs` - Eventos de seguridad
- `logs_actividad` - Acciones de usuarios
- `login_attempts` - Intentos de login
- `rate_limit` - Control de peticiones

**Eventos registrados:**
- Intentos de login fallidos
- Patrones de ataque detectados
- IPs bloqueadas
- Rate limit excedido
- Login exitoso
- Modificaciones importantes

### 13. Protección en .htaccess
✅ **Implementado**: Múltiples reglas de seguridad

**Reglas activas:**
- Bloqueo de bots maliciosos
- Bloqueo de métodos HTTP peligrosos (TRACE, DELETE, etc.)
- Protección contra SQL injection en URLs
- Límite de tamaño de peticiones
- Timeout de lectura configurado
- Compresión GZIP para rendimiento

### 14. Detección de Patrones de Ataque
✅ **Implementado**: `detect_attack_patterns()`

**Detecta:**
- Inyección de `<script>` tags
- SQL injection (union, select, insert, drop, etc.)
- Path traversal (`../`)
- JavaScript URI (`javascript:`)
- Event handlers inline (`onclick=`, etc.)
- Embedding malicioso (`<iframe>`, `<object>`)

## 🛡️ Protecciones Anti-DDoS

### Nivel 1: Rate Limiting por IP
- 100 peticiones/minuto máximo por IP
- Tabla `rate_limit` en base de datos
- Auto-limpieza de registros antiguos

### Nivel 2: Bloqueo Temporal
- Bloqueo automático después de múltiples infracciones
- Tabla `ip_blacklist`
- Desbloqueo automático configurable

### Nivel 3: .htaccess
- Timeout de peticiones (mod_reqtimeout)
- Límite de tamaño de body (10MB)
- Bloqueo de bots conocidos

### Nivel 4: Cloudflare (Recomendado)
Para protección DDoS profesional, usa Cloudflare:
1. Crea cuenta en Cloudflare.com
2. Agrega tu dominio
3. Cambia DNS a Cloudflare
4. Activa "Under Attack Mode" si es necesario

## 📋 Checklist de Seguridad Post-Instalación

### Configuración Inicial
- [ ] Cambiar contraseña de admin por defecto
- [ ] Configurar credenciales de BD en `config/config.php`
- [ ] Establecer `DEBUG_MODE = false` en producción
- [ ] Activar HTTPS y configurar `session.cookie_secure = 1`
- [ ] Descomentar forzado HTTPS en `.htaccess`
- [ ] Configurar permisos de carpetas (755 para uploads/)

### Base de Datos
- [ ] Usar contraseña fuerte para usuario de BD
- [ ] NO usar usuario 'root' en producción
- [ ] Limitar privilegios del usuario de BD (solo lo necesario)
- [ ] Hacer backups regulares
- [ ] Eliminar usuarios de BD no necesarios

### Archivos
- [ ] Eliminar archivos de prueba/desarrollo
- [ ] Verificar que `database.sql` no sea accesible vía web
- [ ] Revisar permisos: 644 para archivos, 755 para directorios
- [ ] Carpeta `config/` no debe ser accesible vía web
- [ ] Verificar que `.git/` no sea público

### Monitoreo
- [ ] Revisar logs de seguridad semanalmente
- [ ] Configurar alertas para intentos de login fallidos
- [ ] Monitorear tabla `ip_blacklist`
- [ ] Revisar `security_logs` para patrones
- [ ] Limpiar logs antiguos (función `cleanup_old_logs()`)

### Hardening Adicional
- [ ] Usar contraseñas de mínimo 12 caracteres (cambiar `PASSWORD_MIN_LENGTH`)
- [ ] Implementar autenticación de dos factores (2FA) - futuro
- [ ] Limitar acceso admin a IPs específicas (opcional)
- [ ] Configurar firewall del servidor
- [ ] Mantener PHP y MySQL actualizados

## 🚨 Respuesta a Incidentes

### Si detectas ataque DDoS:
1. Revisa `security_logs` para identificar patrón
2. Agrega IPs atacantes a blacklist manualmente:
   ```sql
   INSERT INTO ip_blacklist (ip_address, reason) VALUES ('1.2.3.4', 'Ataque DDoS');
   ```
3. Activa modo "Under Attack" en Cloudflare si lo usas
4. Reduce `MAX_REQUESTS_PER_IP` temporalmente
5. Contacta a tu hosting si persiste

### Si detectas intentos de SQL Injection:
1. Revisa `security_logs` para ver la query
2. Verifica que `detect_attack_patterns()` esté bloqueando
3. La IP debe estar en blacklist automáticamente
4. Revisa logs del servidor para más detalles

### Si detectas múltiples logins fallidos:
1. Verifica tabla `login_attempts`
2. Cuenta debe estar bloqueada automáticamente
3. Usuario recibirá mensaje de bloqueo temporal
4. Revisar si es ataque dirigido o contraseña olvidada

## 🔄 Mantenimiento Regular

### Diario
- Revisar intentos de login fallidos inusuales
- Verificar IPs bloqueadas nuevas

### Semanal
- Limpiar logs antiguos: `cleanup_old_logs($db, 30)`
- Revisar patrones en `security_logs`
- Verificar espacio en disco para logs

### Mensual
- Auditoría completa de usuarios
- Revisar y actualizar tokens de acceso
- Backup de base de datos
- Actualizar contraseñas de cuentas administrativas

### Trimestral
- Actualizar dependencias (Bootstrap, Chart.js)
- Revisar y actualizar PHP
- Pruebas de penetración básicas
- Revisar configuración de seguridad

## 📚 Recursos Adicionales

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [Content Security Policy Guide](https://content-security-policy.com/)

## ⚠️ Limitaciones

Este sistema implementa seguridad robusta para hosting compartido, pero tiene limitaciones:

1. **DDoS Distribuido**: Para ataques masivos, necesitas Cloudflare o similar
2. **WAF**: No incluye Web Application Firewall dedicado
3. **Rate Limiting**: Es a nivel aplicación, no a nivel servidor
4. **Hosting Compartido**: Dependes de la seguridad del hosting

**Recomendación**: Para sitios de alto tráfico o críticos, considera:
- Cloudflare Pro
- VPS con ModSecurity
- Fail2Ban en el servidor
- Monitoreo profesional

---

**Sistema actualizado**: 30 de noviembre de 2025
**Versión de seguridad**: 2.0
