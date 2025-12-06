<?php
/**
 * Cargador de variables de entorno desde archivo .env
 * Uso: require_once 'config/env.php';
 */

class Env {
    private static $loaded = false;
    private static $vars = [];

    /**
     * Cargar archivo .env
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path)) {
            throw new Exception("Archivo .env no encontrado en: {$path}. Copia .env.example a .env y configúralo.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parsear línea KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover comillas si existen
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                self::$vars[$key] = $value;
                
                // También definir como constante si no existe
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtener valor de variable de entorno
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return self::$vars[$key] ?? $default;
    }

    /**
     * Verificar si una variable existe
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$vars[$key]);
    }
}

// Auto-cargar al incluir este archivo
Env::load();
