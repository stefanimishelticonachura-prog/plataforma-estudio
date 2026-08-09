<?php
$page_title = 'Gestión de Usuarios';
$page_icon = 'users';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

// Procesar acciones
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? null;
    
    if ($action == 'editar' && $id) {
        // Mostrar formulario de edición
        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $usuario_editar = $stmt->fetch();
        
        if (!$usuario_editar) {
            $_SESSION['error'] = 'Usuario no encontrado';
            header('Location: usuarios.php');
            exit();
        }
    }
}

// Procesar eliminación
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0 && $id != $_SESSION['usuario_id']) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Usuario eliminado correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al eliminar usuario: ' . $e->getMessage();
        }
        header('Location: usuarios.php');
        exit();
    } else {
        $_SESSION['error'] = 'No puedes eliminar tu propio usuario';
        header('Location: usuarios.php');
        exit();
    }
}

// Obtener lista de usuarios
try {
    $stmt = $pdo->query("
        SELECT u.*, r.nombre as rol_nombre 
        FROM Usuarios u
        JOIN Roles r ON u.id_rol = r.id
        ORDER BY u.fecha_registro DESC
    ");
    $usuarios = $stmt->fetchAll();
    
    // Obtener roles para el formulario
    $stmt_roles = $pdo->query("SELECT * FROM Roles ORDER BY nombre");
    $roles = $stmt_roles->fetchAll();
    
} catch (PDOException $e) {
    $usuarios = [];
    $roles = [];
    $_SESSION['error'] = 'Error al cargar datos';
}
?>

<style>
    .table-container {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #e0e0e0;
    }
    td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
    }
    tr:hover {
        background: #f8f9fa;
    }
    .btn-actions {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 12px;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-edit {
        background: #3498db;
        color: white;
    }
    .btn-edit:hover {
        background: #2980b9;
    }
    .btn-delete {
        background: #e74c3c;
        color: white;
    }
    .btn-delete:hover {
        background: #c0392b;
    }
    .btn-toggle {
        background: #f39c12;
        color: white;
    }
    .btn-toggle:hover {
        background: #e67e22;
    }
    .badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .badge-activo {
        background: #d4edda;
        color: #155724;
    }
    .badge-inactivo {
        background: #f8d7da;
        color: #721c24;
    }
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .modal.show {
        display: flex;
    }
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-content h3 {
        margin-bottom: 20px;
        color: #2c3e50;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none;
        border-color: #3498db;
    }
    .btn-submit {
        background: #2ecc71;
        color: white;
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
    }
    .btn-submit:hover {
        background: #27ae60;
    }
    .btn-cancel {
        background: #95a5a6;
        color: white;
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        margin-top: 10px;
    }
    .btn-cancel:hover {
        background: #7f8c8d;
    }
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    .form-actions button {
        flex: 1;
    }
</style>

<div class="table-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3><i class="fas fa-users"></i> Lista de Usuarios</h3>
        <button onclick="openModal('crear')" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </button>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($usuarios)): ?>
        <p style="text-align: center; color: #999; padding: 30px;">No hay usuarios registrados</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo $usuario['id']; ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['correo']); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo $usuario['rol_nombre'] == 'admin' ? '#fbe9e7' : ($usuario['rol_nombre'] == 'profesor' ? '#f3e5f5' : '#e3f2fd'); ?>; color: <?php echo $usuario['rol_nombre'] == 'admin' ? '#c62828' : ($usuario['rol_nombre'] == 'profesor' ? '#7b1fa2' : '#1976d2'); ?>;">
                                <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $usuario['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-actions">
                                <button onclick="openModal('editar', <?php echo $usuario['id']; ?>)" class="btn-sm btn-edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="toggleUsuario(<?php echo $usuario['id']; ?>, <?php echo $usuario['activo']; ?>)" class="btn-sm btn-toggle">
                                    <i class="fas <?php echo $usuario['activo'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                                <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                    <button onclick="confirmDelete(<?php echo $usuario['id']; ?>)" class="btn-sm btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal para Crear/Editar Usuario -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Crear Usuario</h3>
        <form id="userForm" method="POST" action="actions/crear_usuario.php">
            <input type="hidden" name="action" id="formAction" value="crear">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" id="editNombre" required>
            </div>
            
            <div class="form-group">
                <label>Apellido *</label>
                <input type="text" name="apellido" id="editApellido" required>
            </div>
            
            <div class="form-group">
                <label>Correo *</label>
                <input type="email" name="correo" id="editCorreo" required>
            </div>
            
            <div class="form-group" id="passwordGroup">
                <label>Contraseña *</label>
                <input type="password" name="password" id="editPassword" required>
            </div>
            
            <div class="form-group">
                <label>Rol *</label>
                <select name="id_rol" id="editRol" required>
                    <option value="">Seleccionar rol...</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?php echo $rol['id']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Estado</label>
                <select name="activo" id="editActivo">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
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
function openModal(action, id = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const title = document.getElementById('modalTitle');
    const passwordGroup = document.getElementById('passwordGroup');
    
    modal.classList.add('show');
    
    if (action === 'editar' && id) {
        title.innerHTML = '<i class="fas fa-edit"></i> Editar Usuario';
        document.getElementById('formAction').value = 'editar';
        document.getElementById('formId').value = id;
        form.action = 'actions/editar_usuario.php';
        passwordGroup.style.display = 'none';
        
        // Cargar datos del usuario
        fetch(`get_usuario.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editNombre').value = data.usuario.nombre;
                    document.getElementById('editApellido').value = data.usuario.apellido;
                    document.getElementById('editCorreo').value = data.usuario.correo;
                    document.getElementById('editRol').value = data.usuario.id_rol;
                    document.getElementById('editActivo').value = data.usuario.activo;
                    document.getElementById('editNombre').disabled = false;
                    document.getElementById('editApellido').disabled = false;
                    document.getElementById('editCorreo').disabled = false;
                    document.getElementById('editRol').disabled = false;
                    document.getElementById('editActivo').disabled = false;
                }
            })
            .catch(error => console.error('Error:', error));
    } else {
        title.innerHTML = '<i class="fas fa-user-plus"></i> Crear Usuario';
        document.getElementById('formAction').value = 'crear';
        document.getElementById('formId').value = '';
        form.action = 'actions/crear_usuario.php';
        passwordGroup.style.display = 'block';
        form.reset();
        document.getElementById('editNombre').disabled = false;
        document.getElementById('editApellido').disabled = false;
        document.getElementById('editCorreo').disabled = false;
        document.getElementById('editRol').disabled = false;
        document.getElementById('editActivo').disabled = false;
    }
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
}

function toggleUsuario(id, estado) {
    if (confirm(`¿Estás seguro de ${estado ? 'desactivar' : 'activar'} este usuario?`)) {
        window.location.href = `actions/toggle_usuario.php?id=${id}&estado=${estado ? 0 : 1}`;
    }
}

function confirmDelete(id) {
    if (confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')) {
        window.location.href = `usuarios.php?delete=confirm&id=${id}`;
    }
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>