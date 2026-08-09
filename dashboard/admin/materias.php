<?php
$page_title = 'Gestión de Materias';
$page_icon = 'book-open';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

// Procesar eliminación
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            // Verificar si tiene inscripciones
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Inscripciones WHERE id_materia = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'No se puede eliminar la materia porque tiene estudiantes inscritos';
            } else {
                $stmt = $pdo->prepare("DELETE FROM Materias WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Materia eliminada correctamente';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al eliminar materia: ' . $e->getMessage();
        }
        header('Location: materias.php');
        exit();
    }
}

// Obtener lista de materias
try {
    $stmt = $pdo->query("
        SELECT m.*, u.nombre as profesor_nombre, u.apellido as profesor_apellido,
               (SELECT COUNT(*) FROM Inscripciones WHERE id_materia = m.id) as estudiantes_inscritos
        FROM Materias m
        LEFT JOIN Usuarios u ON m.id_profesor = u.id
        ORDER BY m.nombre
    ");
    $materias = $stmt->fetchAll();
    
    // Obtener profesores para el formulario
    $stmt_profesores = $pdo->query("SELECT id, nombre, apellido FROM Usuarios WHERE id_rol = 2 AND activo = 1 ORDER BY nombre");
    $profesores = $stmt_profesores->fetchAll();
    
} catch (PDOException $e) {
    $materias = [];
    $profesores = [];
    $_SESSION['error'] = 'Error al cargar datos';
}
?>

<style>
    .materia-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        border-left: 4px solid #3498db;
        transition: transform 0.2s;
    }
    .materia-card:hover {
        transform: translateX(5px);
    }
    .materia-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .materia-card .header h4 {
        margin: 0;
        color: #2c3e50;
    }
    .materia-card .info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin: 10px 0;
    }
    .materia-card .info-item {
        font-size: 14px;
        color: #666;
    }
    .materia-card .info-item strong {
        color: #2c3e50;
    }
    .materia-card .actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .materia-estado {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .materia-estado.activo {
        background: #d4edda;
        color: #155724;
    }
    .materia-estado.inactivo {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h3><i class="fas fa-book-open"></i> Lista de Materias</h3>
    <button onclick="openModal()" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i> Nueva Materia
    </button>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if (empty($materias)): ?>
    <p style="text-align: center; color: #999; padding: 30px;">No hay materias registradas</p>
<?php else: ?>
    <?php foreach ($materias as $materia): ?>
        <div class="materia-card">
            <div class="header">
                <h4><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                <span class="materia-estado <?php echo $materia['estado']; ?>">
                    <?php echo ucfirst($materia['estado']); ?>
                </span>
            </div>
            <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars($materia['descripcion']); ?></p>
            <div class="info">
                <div class="info-item">
                    <strong>Profesor:</strong> 
                    <?php echo htmlspecialchars($materia['profesor_nombre'] . ' ' . $materia['profesor_apellido']); ?>
                </div>
                <div class="info-item">
                    <strong>Estudiantes:</strong> <?php echo $materia['estudiantes_inscritos']; ?>
                </div>
                <div class="info-item">
                    <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($materia['fecha_creacion'])); ?>
                </div>
            </div>
            <div class="actions">
                <button onclick="editarMateria(<?php echo $materia['id']; ?>)" class="btn-sm btn-edit">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button onclick="toggleMateria(<?php echo $materia['id']; ?>, '<?php echo $materia['estado']; ?>')" class="btn-sm btn-toggle">
                    <i class="fas <?php echo $materia['estado'] == 'activo' ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                    <?php echo $materia['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>
                </button>
                <button onclick="confirmDelete(<?php echo $materia['id']; ?>)" class="btn-sm btn-delete">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Modal para Crear/Editar Materia -->
<div id="materiaModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Nueva Materia</h3>
        <form id="materiaForm" method="POST" action="actions/crear_materia.php">
            <input type="hidden" name="action" id="formAction" value="crear">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" id="editNombreMateria" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" id="editDescripcionMateria" rows="3" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
            </div>
            
            <div class="form-group">
                <label>Profesor *</label>
                <select name="id_profesor" id="editProfesor" required>
                    <option value="">Seleccionar profesor...</option>
                    <?php foreach ($profesores as $profesor): ?>
                        <option value="<?php echo $profesor['id']; ?>">
                            <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Estado</label>
                <select name="estado" id="editEstadoMateria">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
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
function openModal() {
    const modal = document.getElementById('materiaModal');
    const form = document.getElementById('materiaForm');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nueva Materia';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('formId').value = '';
    form.action = 'actions/crear_materia.php';
    form.reset();
    modal.classList.add('show');
}

function editarMateria(id) {
    const modal = document.getElementById('materiaModal');
    const form = document.getElementById('materiaForm');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Materia';
    document.getElementById('formAction').value = 'editar';
    document.getElementById('formId').value = id;
    form.action = 'actions/editar_materia.php';
    
    // Cargar datos de la materia
    fetch(`get_materia.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editNombreMateria').value = data.materia.nombre;
                document.getElementById('editDescripcionMateria').value = data.materia.descripcion;
                document.getElementById('editProfesor').value = data.materia.id_profesor;
                document.getElementById('editEstadoMateria').value = data.materia.estado;
                modal.classList.add('show');
            }
        })
        .catch(error => console.error('Error:', error));
}

function closeModal() {
    document.getElementById('materiaModal').classList.remove('show');
}

function toggleMateria(id, estado) {
    const nuevoEstado = estado === 'activo' ? 'inactivo' : 'activo';
    if (confirm(`¿Estás seguro de ${estado === 'activo' ? 'desactivar' : 'activar'} esta materia?`)) {
        window.location.href = `actions/toggle_materia.php?id=${id}&estado=${nuevoEstado}`;
    }
}

function confirmDelete(id) {
    if (confirm('¿Estás seguro de eliminar esta materia? Esta acción no se puede deshacer.')) {
        window.location.href = `materias.php?delete=confirm&id=${id}`;
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('materiaModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>