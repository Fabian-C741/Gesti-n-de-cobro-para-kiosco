<?php
require_once '../config/config.php';
require_once '../includes/Database.php';
session_start();
require_once '../includes/functions.php';

require_admin();

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'generar') {
            $cantidad = intval($_POST['cantidad'] ?? 1);
            $token_custom = trim($_POST['token_custom'] ?? '');
            
            if ($cantidad < 1 || $cantidad > 50) {
                $error = 'Puede generar entre 1 y 50 tokens a la vez';
            } else {
                try {
                    $db->beginTransaction();
                    $tokens_generados = [];
                    
                    // Si hay un token custom, usarlo
                    if (!empty($token_custom)) {
                        // Verificar que el token no exista
                        $stmt = $db->prepare("SELECT id FROM tokens_acceso WHERE token = ?");
                        $stmt->execute([$token_custom]);
                        if ($stmt->fetch()) {
                            $db->rollBack();
                            $error = 'Este token ya existe. Por favor genera uno nuevo.';
                        } else {
                            $stmt = $db->prepare("
                                INSERT INTO tokens_acceso (token, generado_por) 
                                VALUES (?, ?)
                            ");
                            $stmt->execute([$token_custom, $_SESSION['user_id']]);
                            $tokens_generados[] = $token_custom;
                            
                            $db->commit();
                            log_activity($db, $_SESSION['user_id'], 'generar_tokens', "Generado 1 token personalizado");
                            $success = "Token generado exitosamente";
                            $_SESSION['tokens_generados'] = $tokens_generados;
                        }
                    } else {
                        // Generar tokens aleatorios automáticamente
                        for ($i = 0; $i < $cantidad; $i++) {
                            $intentos = 0;
                            $max_intentos = 10;
                            
                            do {
                                $token = generate_token(TOKEN_LENGTH);
                                
                                // Verificar que el token no exista
                                $stmt = $db->prepare("SELECT id FROM tokens_acceso WHERE token = ?");
                                $stmt->execute([$token]);
                                $existe = $stmt->fetch();
                                
                                $intentos++;
                            } while ($existe && $intentos < $max_intentos);
                            
                            if ($intentos >= $max_intentos) {
                                throw new Exception('No se pudo generar un token único. Intente nuevamente.');
                            }
                            
                            $stmt = $db->prepare("
                                INSERT INTO tokens_acceso (token, generado_por) 
                                VALUES (?, ?)
                            ");
                            $stmt->execute([$token, $_SESSION['user_id']]);
                            
                            $tokens_generados[] = $token;
                        }
                        
                        $db->commit();
                        
                        log_activity($db, $_SESSION['user_id'], 'generar_tokens', "Generados $cantidad tokens");
                        $success = "Se generaron $cantidad token(s) exitosamente";
                        
                        // Guardar tokens en sesión para mostrarlos
                        $_SESSION['tokens_generados'] = $tokens_generados;
                    }
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = 'Error al generar los tokens: ' . $e->getMessage();
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = $e->getMessage();
                }
            }
        } elseif ($action === 'eliminar') {
            $id = intval($_POST['id'] ?? 0);
            
            try {
                $stmt = $db->prepare("DELETE FROM tokens_acceso WHERE id = ? AND usado = 0");
                $stmt->execute([$id]);
                
                log_activity($db, $_SESSION['user_id'], 'eliminar_token', "Token eliminado ID: $id");
                $success = 'Token eliminado exitosamente';
            } catch (PDOException $e) {
                $error = 'Error al eliminar el token';
            }
        }
    }
}

// Obtener tokens generados de la sesión
$tokens_nuevos = $_SESSION['tokens_generados'] ?? [];
unset($_SESSION['tokens_generados']);

// Obtener lista de tokens
$stmt = $db->query("
    SELECT t.*, 
           u.nombre as usuario_nombre,
           ug.nombre as generado_por_nombre
    FROM tokens_acceso t
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    LEFT JOIN usuarios ug ON t.generado_por = ug.id
    ORDER BY t.fecha_generacion DESC
    LIMIT 100
");
$tokens = $stmt->fetchAll();

// Estadísticas
$stmt = $db->query("SELECT COUNT(*) as total FROM tokens_acceso WHERE usado = 0");
$tokens_disponibles = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tokens_acceso WHERE usado = 1");
$tokens_usados = $stmt->fetch()['total'];

$page_title = 'Tokens de Acceso';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-key me-2"></i>Tokens de Acceso</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalGenerar">
            <i class="bi bi-plus-circle me-1"></i> Generar Tokens
        </button>
    </div>

    <?php if ($error): ?>
        <?php echo show_alert($error, 'error'); ?>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <?php echo show_alert($success, 'success'); ?>
    <?php endif; ?>

    <!-- Mostrar tokens recién generados -->
    <?php if (!empty($tokens_nuevos)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <h5><i class="bi bi-check-circle me-2"></i>Tokens Generados</h5>
            <p class="mb-2">Copie estos tokens y compártalos con los usuarios. <strong>No podrá verlos nuevamente.</strong></p>
            <div class="list-group">
                <?php foreach ($tokens_nuevos as $token): ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center">
                            <code class="flex-grow-1"><?php echo htmlspecialchars($token); ?></code>
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="copiarTexto('<?php echo $token; ?>')">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Tokens Disponibles</h6>
                            <h3 class="mb-0"><?php echo $tokens_disponibles; ?></h3>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-key"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Tokens Usados</h6>
                            <h3 class="mb-0"><?php echo $tokens_usados; ?></h3>
                        </div>
                        <div class="stat-icon bg-white bg-opacity-25">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de tokens -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Token</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th>Generado Por</th>
                            <th>Fecha Generación</th>
                            <th>Fecha Uso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token): ?>
                            <tr>
                                <td>
                                    <code class="small"><?php echo substr(htmlspecialchars($token['token']), 0, 20); ?>...</code>
                                    <button class="btn btn-sm btn-link p-0 ms-1" onclick="copiarTexto('<?php echo htmlspecialchars($token['token']); ?>')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </td>
                                <td>
                                    <?php if ($token['usado']): ?>
                                        <span class="badge bg-success">Usado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Disponible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($token['usuario_nombre']): ?>
                                        <?php echo htmlspecialchars($token['usuario_nombre']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($token['generado_por_nombre'] ?? '-'); ?></td>
                                <td><?php echo format_date($token['fecha_generacion']); ?></td>
                                <td>
                                    <?php if ($token['fecha_uso']): ?>
                                        <?php echo format_date($token['fecha_uso']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$token['usado']): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="verToken(<?php echo $token['id']; ?>, '<?php echo htmlspecialchars($token['token']); ?>')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="eliminarToken(<?php echo $token['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Generar Tokens -->
<div class="modal fade" id="modalGenerar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Generar Tokens</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="generar">
                    
                    <div class="mb-3">
                        <label class="form-label">Cantidad de Tokens</label>
                        <div class="input-group">
                            <input type="number" name="cantidad" id="cantidad_tokens" class="form-control" min="1" max="50" value="1" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="generarTokenAleatorio()" title="Generar token único">
                                <i class="bi bi-shuffle"></i> Aleatorio
                            </button>
                        </div>
                        <small class="text-muted">Puede generar entre 1 y 50 tokens o usar el botón "Aleatorio" para generar uno único</small>
                    </div>
                    <div class="mb-3" id="token_preview_container" style="display: none;">
                        <label class="form-label">Token Generado (Vista Previa)</label>
                        <div class="input-group">
                            <input type="text" id="token_preview" class="form-control" readonly>
                            <button type="button" class="btn btn-outline-primary" onclick="copiarTexto(document.getElementById('token_preview').value)">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Este es un token de ejemplo. Se guardará al hacer clic en "Generar"</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Los tokens generados son únicos y seguros. Compártalos con usuarios nuevos para que puedan registrarse.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Generar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Token Completo -->
<div class="modal fade" id="modalVerToken" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Token Completo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Token:</label>
                    <div class="input-group">
                        <input type="text" id="token_completo" class="form-control" readonly>
                        <button class="btn btn-outline-primary" onclick="copiarTokenCompleto()">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Comparta este token de forma segura con el usuario
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar Token</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p>¿Está seguro de eliminar este token?</p>
                    <div class="alert alert-warning">
                        Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generarTokenAleatorio() {
    // Generar token único de 32 caracteres
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < 32; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    // Mostrar preview
    document.getElementById('token_preview').value = token;
    document.getElementById('token_preview_container').style.display = 'block';
    document.getElementById('cantidad_tokens').value = 1;
    
    // Guardar token en un campo oculto para enviarlo
    let hiddenField = document.getElementById('token_custom');
    if (!hiddenField) {
        hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'token_custom';
        hiddenField.id = 'token_custom';
        document.querySelector('form[method="POST"]').appendChild(hiddenField);
    }
    hiddenField.value = token;
}

function copiarTexto(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        alert('Token copiado al portapapeles');
    });
}

function verToken(id, token) {
    document.getElementById('token_completo').value = token;
    new bootstrap.Modal(document.getElementById('modalVerToken')).show();
}

function copiarTokenCompleto() {
    const input = document.getElementById('token_completo');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Token copiado al portapapeles');
    });
}

function eliminarToken(id) {
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
