<?php
// Funciones de seguridad y utilidades

/**
 * Sanitizar entrada de usuario
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar token aleatorio seguro
 */
function generate_token($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generar hash de contraseña
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generar token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token(32);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirigir a una URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Verificar si el usuario está autenticado
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verificar si el usuario es administrador
 */
function is_admin() {
    return isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin';
}

/**
 * Verificar autenticación y redirigir si no está autenticado
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('../login.php?error=no_autenticado');
    }
}

/**
 * Verificar que sea administrador y redirigir si no lo es
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect('../index.php?error=acceso_denegado');
    }
}

/**
 * Formatear precio
 */
function format_price($price) {
    return '$' . number_format($price, 2, ',', '.');
}

/**
 * Formatear fecha
 */
function format_date($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Obtener extensión de archivo
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validar extensión de imagen
 */
function validate_image_extension($filename) {
    $ext = get_file_extension($filename);
    return in_array($ext, ALLOWED_EXTENSIONS);
}

/**
 * Generar nombre único para archivo
 */
function generate_unique_filename($original_filename) {
    $ext = get_file_extension($original_filename);
    return uniqid('img_', true) . '.' . $ext;
}

/**
 * Limpiar nombre de archivo
 */
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

/**
 * Registrar actividad en logs
 */
function log_activity($db, $usuario_id, $accion, $descripcion = '') {
    try {
        $stmt = $db->prepare("
            INSERT INTO logs_actividad (usuario_id, accion, descripcion, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->execute([$usuario_id, $accion, $descripcion, $ip]);
    } catch (PDOException $e) {
        // Si falla el log, no detener la operación principal
        if (DEBUG_MODE) {
            error_log("Error al registrar actividad: " . $e->getMessage());
        }
    }
}

/**
 * Obtener configuración del sistema
 */
function get_config($db, $clave, $default = null) {
    try {
        $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $result = $stmt->fetch();
        return $result ? $result['valor'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Establecer configuración del sistema
 */
function set_config($db, $clave, $valor, $descripcion = '') {
    try {
        $stmt = $db->prepare("
            INSERT INTO configuracion (clave, valor, descripcion) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE valor = ?, fecha_modificacion = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$clave, $valor, $descripcion, $valor]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Limpiar sesiones expiradas
 */
function clean_expired_sessions($db) {
    try {
        $stmt = $db->prepare("DELETE FROM sesiones WHERE fecha_expiracion < NOW() OR activa = 0");
        $stmt->execute();
    } catch (PDOException $e) {
        // Ignorar errores de limpieza
    }
}

/**
 * Mostrar alerta/mensaje
 */
function show_alert($message, $type = 'info') {
    $types = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    $class = $types[$type] ?? $types['info'];
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">' . 
           htmlspecialchars($message) . 
           '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Generar número de venta único
 */
function generate_sale_number($db) {
    $prefix = 'V';
    $date = date('Ymd');
    
    // Obtener el último número del día
    $stmt = $db->prepare("
        SELECT numero_venta FROM ventas 
        WHERE numero_venta LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$prefix . $date . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $last_number = intval(substr($last['numero_venta'], -4));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return $prefix . $date . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

/**
 * Calcular estadísticas de ventas
 */
function get_sales_stats($db, $usuario_id = null, $fecha_inicio = null, $fecha_fin = null) {
    try {
        $conditions = ["estado = 'completada'"];
        $params = [];
        
        if ($usuario_id) {
            $conditions[] = "usuario_id = ?";
            $params[] = $usuario_id;
        }
        
        if ($fecha_inicio) {
            $conditions[] = "fecha_venta >= ?";
            $params[] = $fecha_inicio;
        }
        
        if ($fecha_fin) {
            $conditions[] = "fecha_venta <= ?";
            $params[] = $fecha_fin;
        }
        
        $where = implode(' AND ', $conditions);
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(total), 0) as total_monto,
                COALESCE(AVG(total), 0) as promedio_venta
            FROM ventas 
            WHERE $where
        ");
        $stmt->execute($params);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        return [
            'total_ventas' => 0,
            'total_monto' => 0,
            'promedio_venta' => 0
        ];
    }
}

/**
 * Validar y actualizar stock
 */
function update_stock($db, $producto_id, $cantidad, $operacion = 'restar') {
    try {
        $db->beginTransaction();
        
        // Obtener stock actual
        $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ? FOR UPDATE");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch();
        
        if (!$producto) {
            $db->rollBack();
            return false;
        }
        
        $nuevo_stock = $operacion === 'restar' 
            ? $producto['stock'] - $cantidad 
            : $producto['stock'] + $cantidad;
        
        if ($nuevo_stock < 0) {
            $db->rollBack();
            return false;
        }
        
        // Actualizar stock
        $stmt = $db->prepare("UPDATE productos SET stock = ? WHERE id = ?");
        $stmt->execute([$nuevo_stock, $producto_id]);
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        return false;
    }
}
/**
 * Obtener el punto_venta_id del usuario actual
 * Retorna null si no tiene (ve todo) o el ID si tiene asignado
 */
function get_user_punto_venta_id() {
    return $_SESSION['punto_venta_id'] ?? null;
}

/**
 * Construir condición SQL para filtrar por punto de venta
 * Compatible hacia atrás: si usuario no tiene punto_venta_id, ve todo
 * @param string $tabla_alias Alias de la tabla (ej: 'p' para productos)
 * @return array ['sql' => string, 'params' => array]
 */
function get_punto_venta_filter($tabla_alias = '') {
    $punto_venta_id = get_user_punto_venta_id();
    $prefix = $tabla_alias ? $tabla_alias . '.' : '';
    
    if ($punto_venta_id === null) {
        // Usuario sin punto de venta asignado = ve todo
        return ['sql' => '1=1', 'params' => []];
    }
    
    // Usuario con punto de venta = ve solo sus datos + datos globales (NULL)
    return [
        'sql' => "({$prefix}punto_venta_id = ? OR {$prefix}punto_venta_id IS NULL)",
        'params' => [$punto_venta_id]
    ];
}

/**
 * Verificar si una columna existe en una tabla
 */
function column_exists($db, $table, $column) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}