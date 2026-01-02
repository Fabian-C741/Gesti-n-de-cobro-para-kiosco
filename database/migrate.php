<?php
/**
 * Sistema de Migraciones de Base de Datos
 * Ejecuta automáticamente las migraciones pendientes
 * 
 * Uso: php database/migrate.php
 * O incluir en el deploy: require_once 'database/migrate.php';
 */

// Definir que estamos en modo migración para evitar problemas de seguridad
define('MIGRATION_MODE', true);
define('DISABLE_AUTO_SECURITY_HEADERS', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

class MigrationRunner {
    private $db;
    private $migrations_dir;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->migrations_dir = __DIR__ . '/migrations';
        $this->ensureMigrationsTable();
    }
    
    /**
     * Crear tabla de migraciones si no existe
     */
    private function ensureMigrationsTable() {
        $schema = file_get_contents(__DIR__ . '/schema.sql');
        $this->db->exec($schema);
        echo "✓ Tabla de migraciones verificada\n";
    }
    
    /**
     * Obtener migraciones ya ejecutadas
     */
    private function getExecutedMigrations() {
        $stmt = $this->db->query("SELECT migration_name FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Obtener archivos de migración disponibles
     */
    private function getAvailableMigrations() {
        $files = glob($this->migrations_dir . '/*.sql');
        sort($files); // Ejecutar en orden alfabético
        
        $migrations = [];
        foreach ($files as $file) {
            $migrations[] = [
                'file' => $file,
                'name' => basename($file, '.sql')
            ];
        }
        return $migrations;
    }
    
    /**
     * Ejecutar una migración
     */
    private function executeMigration($migration) {
        try {
            $sql = file_get_contents($migration['file']);
            
            // Dividir por punto y coma para ejecutar múltiples sentencias
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt);
                }
            );
            
            $this->db->beginTransaction();
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }
            
            // Registrar migración como ejecutada
            $stmt = $this->db->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->execute([$migration['name']]);
            
            $this->db->commit();
            
            echo "✓ Migración ejecutada: {$migration['name']}\n";
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            echo "✗ Error en migración {$migration['name']}: {$e->getMessage()}\n";
            return false;
        }
    }
    
    /**
     * Ejecutar todas las migraciones pendientes
     */
    public function run() {
        echo "========================================\n";
        echo "   SISTEMA DE MIGRACIONES\n";
        echo "========================================\n\n";
        
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();
        
        $pending = array_filter($available, function($migration) use ($executed) {
            return !in_array($migration['name'], $executed);
        });
        
        if (empty($pending)) {
            echo "✓ No hay migraciones pendientes\n";
            echo "  Total ejecutadas: " . count($executed) . "\n";
            return true;
        }
        
        echo "Migraciones pendientes: " . count($pending) . "\n\n";
        
        foreach ($pending as $migration) {
            if (!$this->executeMigration($migration)) {
                echo "\n✗ Proceso detenido debido a error\n";
                return false;
            }
        }
        
        echo "\n✓ Todas las migraciones ejecutadas exitosamente\n";
        echo "  Total: " . count($available) . "\n";
        return true;
    }
    
    /**
     * Mostrar estado de migraciones
     */
    public function status() {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();
        
        echo "========================================\n";
        echo "   ESTADO DE MIGRACIONES\n";
        echo "========================================\n\n";
        
        foreach ($available as $migration) {
            $status = in_array($migration['name'], $executed) ? '✓' : '✗';
            echo "$status {$migration['name']}\n";
        }
        
        echo "\nTotal: " . count($available) . " | Ejecutadas: " . count($executed) . "\n";
    }
}

// Si se ejecuta desde línea de comandos
if (php_sapi_name() === 'cli') {
    $runner = new MigrationRunner();
    
    $command = $argv[1] ?? 'run';
    
    switch ($command) {
        case 'status':
            $runner->status();
            break;
        case 'run':
        default:
            $runner->run();
            break;
    }
}
