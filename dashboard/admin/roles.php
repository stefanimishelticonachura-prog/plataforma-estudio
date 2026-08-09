<?php
$page_title = 'Gestión de Roles';
$page_icon = 'user-tag';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;
    
    if ($action === 'editar' && $id) {
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        
        try {
            $stmt = $pdo->prepare("UPDATE Roles SET nombre = ?, descripcion = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $id]);
            $_SESSION['success'] = 'Rol actualizado correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al actualizar rol: ' . $e->getMessage();
        }
        header('Location: roles.php');
        exit();
    }
}

// Obtener roles
try {
    $stmt = $pdo->query("SELECT * FROM Roles ORDER BY id");
    $roles = $stmt->fetchAll();
    
    // Obtener conteo de usuarios por rol
    foreach ($roles as &$rol) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuarios WHERE id_rol = ? AND activo = 1");
        $stmt->execute([$rol['id']]);
        $rol['total_usuarios'] = $stmt->fetchColumn();
    }
    
} catch (PDOException $e) {
    $roles = [];
    $_SESSION['error'] = 'Error al cargar roles';
}
?>

<style>
    .role-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        border-left: 4px solid #3498db;
    }
    .role-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .role-card .header h4 {
        margin: 0;
        color: #2c3e50;
    }
    .role-card .badge-role {
        display: inline-block;
        padding: 4px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
    }
    .role-card .info {
        margin: 10px 0;
        color: #666;
    }
    .role-card .stats {
        display: flex;
        gap: 20px;
        margin: 15px 0;
    }
    .role-card .stats .stat {
        font-size: 14px;
    }
    .role-card .stats .stat strong {
        color: #2c3e50;
    }
    .role-card .actions {
        margin-top: 15px;
    }
</style>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<h3><i class="fas fa-user-tag"></i> Roles del Sistema</h3>
<p style="color: #666; margin-bottom: 20px;">Los roles definen los permisos y accesos de los usuarios en el sistema.</p>

<?php foreach ($roles as $rol): ?>
    <div class="role-card">
        <div class="header">
            <h4>
                <?php 
                $icon = $rol['nombre'] == 'admin' ? 'fa-shield-alt' : ($rol['nombre'] == 'profesor' ? 'fa-chalkboard-teacher' : 'fa-user-graduate');
                ?>
                <i class="fas <?php echo $icon; ?>" style="color: <?php echo $rol['nombre'] == 'admin' ? '#e74c3c' : ($rol['nombre'] == 'profesor' ? '#7b1fa2' : '#1976d2'); ?>;"></i>
                <?php echo ucfirst(htmlspecialchars($rol['nombre'])); ?>
            </h4>
            <span class="badge-role" style="background: <?php echo $rol['nombre'] == 'admin' ? '#fbe9e7' : ($rol['nombre'] == 'profesor' ? '#f3e5f5' : '#e3f2fd'); ?>; color: <?php echo $rol['nombre'] == 'admin' ? '#c62828' : ($rol['nombre'] == 'profesor' ? '#7b1fa2' : '#1976d2'); ?>;">
                <?php echo $rol['total_usuarios']; ?> usuarios
            </span>
        </div>
        <div class="info">
            <?php echo htmlspecialchars($rol['descripcion']); ?>
        </div>
        <div class="stats">
            <div class="stat">
                <strong>ID:</strong> <?php echo $rol['id']; ?>
            </div>
            <div class="stat">
                <strong>Usuarios activos:</strong> <?php echo $rol['total_usuarios']; ?>
            </div>
        </div>
        <div class="actions">
            <button onclick="editarRol(<?php echo $rol['id']; ?>, '<?php echo htmlspecialchars($rol['nombre']); ?>', '<?php echo htmlspecialchars($rol['descripcion']); ?>')" class="btn-sm btn-edit">
                <i class="fas fa-edit"></i> Editar
            </button>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modal para Editar Rol -->
<div id="rolModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> Editar Rol</h3>
        <form method="POST">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="id" id="editRolId">
            
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" id="editRolNombre" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" id="editRolDescripcion" rows="3" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <button type="button" onclick="closeModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editarRol(id, nombre, descripcion) {
    const modal = document.getElementById('rolModal');
    document.getElementById('editRolId').value = id;
    document.getElementById('editRolNombre').value = nombre;
    document.getElementById('editRolDescripcion').value = descripcion;
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('rolModal').classList.remove('show');
}

window.onclick = function(event) {
    const modal = document.getElementById('rolModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>