<?php
// Función para generar CSS personalizado dinámicamente
function generate_custom_css($db) {
    try {
        $stmt = $db->query("SELECT clave, valor FROM personalizacion");
        $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $css = "/* CSS Generado Automáticamente - No Editar Manualmente */\n\n";
        $css .= ":root {\n";
        $css .= "    --primary-color: " . ($config['primary_color'] ?? '#0d6efd') . ";\n";
        $css .= "    --secondary-color: " . ($config['secondary_color'] ?? '#6c757d') . ";\n";
        $css .= "    --success-color: " . ($config['success_color'] ?? '#198754') . ";\n";
        $css .= "    --danger-color: " . ($config['danger_color'] ?? '#dc3545') . ";\n";
        $css .= "    --btn-radius: " . ($config['btn_border_radius'] ?? '8') . "px;\n";
        $css .= "    --card-radius: " . ($config['card_border_radius'] ?? '12') . "px;\n";
        $css .= "}\n\n";
        
        // Sidebar gradient
        $css .= ".sidebar {\n";
        $css .= "    background: linear-gradient(180deg, ";
        $css .= ($config['sidebar_bg_start'] ?? '#0d6efd') . " 0%, ";
        $css .= ($config['sidebar_bg_end'] ?? '#0a58ca') . " 100%);\n";
        $css .= "}\n\n";
        
        // Login background
        if (!empty($config['login_bg_image'])) {
            $css .= ".login-page {\n";
            $css .= "    background-image: url('../uploads/backgrounds/" . $config['login_bg_image'] . "');\n";
            $css .= "    background-size: cover;\n";
            $css .= "    background-position: center;\n";
            $css .= "    background-attachment: fixed;\n";
            $css .= "}\n\n";
        }
        
        // Aplicar border radius
        $css .= ".btn { border-radius: var(--btn-radius); }\n";
        $css .= ".card { border-radius: var(--card-radius); }\n";
        $css .= ".form-control, .form-select { border-radius: var(--btn-radius); }\n\n";
        
        // Aplicar colores primarios
        $css .= ".btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }\n";
        $css .= ".btn-primary:hover { background-color: var(--primary-color); filter: brightness(0.9); }\n";
        $css .= ".btn-secondary { background-color: var(--secondary-color); border-color: var(--secondary-color); }\n";
        $css .= ".btn-success { background-color: var(--success-color); border-color: var(--success-color); }\n";
        $css .= ".btn-danger { background-color: var(--danger-color); border-color: var(--danger-color); }\n\n";
        
        $css .= ".bg-primary { background-color: var(--primary-color) !important; }\n";
        $css .= ".bg-secondary { background-color: var(--secondary-color) !important; }\n";
        $css .= ".bg-success { background-color: var(--success-color) !important; }\n";
        $css .= ".bg-danger { background-color: var(--danger-color) !important; }\n\n";
        
        $css .= ".text-primary { color: var(--primary-color) !important; }\n";
        $css .= ".badge.bg-primary { background-color: var(--primary-color) !important; }\n";
        
        // Guardar archivo
        $css_path = __DIR__ . '/../assets/css/custom.css';
        file_put_contents($css_path, $css);
        
        return true;
    } catch (Exception $e) {
        error_log("Error generando CSS: " . $e->getMessage());
        return false;
    }
}

// Función para obtener valor de personalización
function get_personalization($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT valor FROM personalizacion WHERE clave = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['valor'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}
?>
