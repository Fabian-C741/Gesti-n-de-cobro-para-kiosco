<?php
/**
 * Headers de Seguridad Avanzados
 * Protección contra clickjacking, XSS, CSRF y otros ataques
 */

class SecurityHeaders {
    
    /**
     * Aplicar headers de seguridad básicos
     */
    public static function setBasicHeaders() {
        // Prevenir clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Protección XSS del navegador
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevenir MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Política de referrer restrictiva
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Remover header de servidor
        header_remove('X-Powered-By');
        header_remove('Server');
        
        // Prevenir cache de páginas sensibles
        if (self::isSensitivePage()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    
    /**
     * Content Security Policy avanzado
     */
    public static function setCSP($strict = false) {
        if ($strict) {
            // CSP estricto para páginas críticas
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
                   "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' https://cdn.jsdelivr.net; " .
                   "connect-src 'self'; " .
                   "frame-src 'none'; " .
                   "object-src 'none'; " .
                   "base-uri 'self';";
        } else {
            // CSP más permisivo para compatibilidad
            $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:; " .
                   "frame-ancestors 'self'; " .
                   "form-action 'self';";
        }
        
        header("Content-Security-Policy: " . $csp);
    }
    
    /**
     * Headers HTTPS/HSTS
     */
    public static function setHTTPSHeaders($includeSubdomains = false, $maxAge = 31536000) {
        if (self::isHTTPS()) {
            $hsts = "max-age={$maxAge}";
            if ($includeSubdomains) {
                $hsts .= "; includeSubDomains";
            }
            $hsts .= "; preload";
            
            header("Strict-Transport-Security: " . $hsts);
        }
    }
    
    /**
     * Headers de privacidad
     */
    public static function setPrivacyHeaders() {
        // Controlar políticas de permisos
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
        
        // Cross-Origin Resource Policy
        header('Cross-Origin-Resource-Policy: same-origin');
        
        // Cross-Origin Embedder Policy
        header('Cross-Origin-Embedder-Policy: require-corp');
        
        // Cross-Origin Opener Policy
        header('Cross-Origin-Opener-Policy: same-origin');
    }
    
    /**
     * Headers anti-bot/scraping
     */
    public static function setAntiScrapingHeaders() {
        // Detectar user agents de bots comunes
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_patterns = [
            '/googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|facebookexternalhit/i',
            '/curl|wget|scrapy|python-requests|postman/i'
        ];
        
        foreach ($bot_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                http_response_code(403);
                header('X-Robots-Tag: noindex, nofollow');
                die('Access denied for automated requests');
            }
        }
    }
    
    /**
     * Rate limiting headers
     */
    public static function setRateLimitHeaders($limit, $window, $remaining) {
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Window: {$window}");
        header("X-RateLimit-Remaining: {$remaining}");
        
        if ($remaining <= 0) {
            header("Retry-After: {$window}");
        }
    }
    
    /**
     * Headers de depuración (solo en desarrollo)
     */
    public static function setDebugHeaders() {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            header('X-Debug-Mode: Enabled');
            header('X-Debug-PHP-Version: ' . phpversion());
            header('X-Debug-Server-Time: ' . date('c'));
        }
    }
    
    /**
     * Verificar si la conexión es HTTPS
     */
    private static function isHTTPS() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               ($_SERVER['SERVER_PORT'] ?? 80) == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    }
    
    /**
     * Verificar si es una página sensible
     */
    private static function isSensitivePage() {
        $sensitive_paths = [
            '/admin/',
            '/superadmin/',
            '/login.php',
            '/configuracion',
            '/usuarios.php',
            '/reportes.php'
        ];
        
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        
        foreach ($sensitive_paths as $path) {
            if (strpos($current_path, $path) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Aplicar todos los headers de seguridad
     */
    public static function setAllSecurityHeaders($strict_csp = false) {
        self::setBasicHeaders();
        self::setCSP($strict_csp);
        self::setHTTPSHeaders();
        self::setPrivacyHeaders();
        
        // Solo en producción
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            self::setAntiScrapingHeaders();
        } else {
            self::setDebugHeaders();
        }
    }
}

/**
 * Middleware de seguridad automático
 */
class SecurityMiddleware {
    
    /**
     * Verificar origen de petición
     */
    public static function validateRequestOrigin() {
        $allowed_origins = [
            'https://gestion-de-ventaspos.kcrsf.com',
            'http://localhost',
            'https://localhost'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        
        if (!empty($origin)) {
            $origin_domain = parse_url($origin, PHP_URL_HOST);
            $allowed = false;
            
            foreach ($allowed_origins as $allowed_origin) {
                $allowed_domain = parse_url($allowed_origin, PHP_URL_HOST);
                if ($origin_domain === $allowed_domain) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                http_response_code(403);
                log_security_event('INVALID_ORIGIN', "Petición desde origen no permitido: {$origin}", 'HIGH');
                die(json_encode(['error' => 'Origin not allowed']));
            }
        }
    }
    
    /**
     * Verificar método HTTP
     */
    public static function validateHTTPMethod($allowed_methods = ['GET', 'POST']) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($method, $allowed_methods)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowed_methods));
            log_security_event('INVALID_METHOD', "Método HTTP no permitido: {$method}", 'MEDIUM');
            die(json_encode(['error' => 'Method not allowed']));
        }
    }
    
    /**
     * Aplicar middleware completo
     */
    public static function apply() {
        self::validateRequestOrigin();
        self::validateHTTPMethod();
        SecurityHeaders::setAllSecurityHeaders();
    }
}

/**
 * Auto-aplicar headers básicos solo si no se han enviado
 */
if (!headers_sent() && !defined('DISABLE_AUTO_SECURITY_HEADERS')) {
    SecurityHeaders::setBasicHeaders();
}
?>