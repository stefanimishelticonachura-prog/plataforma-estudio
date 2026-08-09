<?php
// PRIMERO: Procesar acciones ANTES del header
$page_title = 'Mis Materias';
$page_icon = 'book';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Procesar eliminación
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Inscripciones WHERE id_materia = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'No se puede eliminar la materia porque tiene estudiantes inscritos';
            } else {
                $stmt = $pdo->prepare("DELETE FROM Materias WHERE id = ? AND id_profesor = ?");
                $stmt->execute([$id, $usuario_id]);
                $_SESSION['success'] = 'Materia eliminada correctamente';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al eliminar materia: ' . $e->getMessage();
        }
        header('Location: mis-materias.php');
        exit();
    }
}

// Procesar toggle de estado
if (isset($_GET['toggle']) && isset($_GET['estado'])) {
    $id = $_GET['id'] ?? 0;
    $estado = $_GET['estado'] ?? 'activo';
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE Materias SET estado = ? WHERE id = ? AND id_profesor = ?");
            $stmt->execute([$estado, $id, $usuario_id]);
            $_SESSION['success'] = 'Materia ' . ($estado == 'activo' ? 'activada' : 'desactivada') . ' correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al cambiar estado: ' . $e->getMessage();
        }
        header('Location: mis-materias.php');
        exit();
    }
}

// Obtener materias del profesor
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               (SELECT COUNT(*) FROM Inscripciones WHERE id_materia = m.id) as estudiantes_inscritos,
               (SELECT COUNT(*) FROM Temas WHERE id_materia = m.id) as total_temas
        FROM Materias m
        WHERE m.id_profesor = ?
        ORDER BY m.fecha_creacion DESC
    ");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias = [];
    $_SESSION['error'] = 'Error al cargar materias';
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    .modal-confirm {
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
    .modal-confirm.show {
        display: flex;
    }
    .modal-confirm .modal-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        max-width: 600px;
        width: 90%;
        text-align: center;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-confirm .modal-content h3 {
        margin-bottom: 15px;
        color: #2c3e50;
    }
    .modal-confirm .modal-content p {
        color: #666;
        margin-bottom: 20px;
    }
    .modal-confirm .modal-content .btn-group {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    .modal-confirm .modal-content .btn-group button {
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
    }
    .modal-confirm .modal-content .btn-confirm {
        background: #e74c3c;
        color: white;
    }
    .modal-confirm .modal-content .btn-confirm:hover {
        background: #c0392b;
    }
    .modal-confirm .modal-content .btn-cancel-modal {
        background: #95a5a6;
        color: white;
    }
    .modal-confirm .modal-content .btn-cancel-modal:hover {
        background: #7f8c8d;
    }
    .modal-confirm .modal-content .btn-confirm-toggle {
        background: #f39c12;
        color: white;
    }
    .modal-confirm .modal-content .btn-confirm-toggle:hover {
        background: #e67e22;
    }
    .lista-temas {
        margin-top: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        border-left: 3px solid #9b59b6;
    }
    .lista-temas .titulo {
        font-weight: 600;
        color: #2c3e50;
        font-size: 13px;
        display: block;
        margin-bottom: 5px;
    }
    .tema-tag {
        display: inline-block;
        background: #e3f2fd;
        color: #1976d2;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 12px;
        margin: 2px 4px 2px 0;
    }
    .tema-tag .orden {
        color: #999;
        font-weight: normal;
    }
    .sin-temas {
        color: #999;
        font-size: 13px;
        font-style: italic;
    }
    .detalle-tema {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 8px;
        border-left: 3px solid #9b59b6;
        text-align: left;
    }
    .detalle-tema .tema-nombre {
        font-weight: 600;
        color: #2c3e50;
    }
    .detalle-tema .tema-desc {
        color: #666;
        font-size: 14px;
        margin-top: 3px;
    }
    .detalle-tema .tema-orden {
        color: #999;
        font-size: 12px;
        float: right;
    }
</style>

<h3><i class="fas fa-book"></i> Mis Materias</h3>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (empty($materias)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-book-open" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">No tienes materias creadas aún</p>
        <a href="crear-materia.php" class="btn btn-primary" style="margin-top: 10px; background: #9b59b6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">
            <i class="fas fa-plus-circle"></i> Crear Primera Materia
        </a>
    </div>
<?php else: ?>
    <?php foreach ($materias as $materia): 
        // Obtener temas de esta materia
        $temas_materia = [];
        try {
            $stmt_temas = $pdo->prepare("SELECT id, nombre, descripcion, orden FROM Temas WHERE id_materia = ? ORDER BY orden");
            $stmt_temas->execute([$materia['id']]);
            $temas_materia = $stmt_temas->fetchAll();
        } catch (PDOException $e) {
            $temas_materia = [];
        }
    ?>
        <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 4px solid #9b59b6;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap;">
                <h4 style="margin: 0; color: #2c3e50;"><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                <span class="badge <?php echo $materia['estado'] == 'activo' ? 'badge-activo' : 'badge-inactivo'; ?>">
                    <?php echo ucfirst($materia['estado']); ?>
                </span>
            </div>
            <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars($materia['descripcion']); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 10px 0;">
                <div style="font-size: 14px; color: #666;">
                    <strong><i class="fas fa-users"></i> Estudiantes:</strong> <?php echo $materia['estudiantes_inscritos']; ?>
                </div>
                <div style="font-size: 14px; color: #666;">
                    <strong><i class="fas fa-list"></i> Temas:</strong> <?php echo $materia['total_temas']; ?>
                </div>
                <div style="font-size: 14px; color: #666;">
                    <strong><i class="fas fa-calendar"></i> Creada:</strong> <?php echo date('d/m/Y', strtotime($materia['fecha_creacion'])); ?>
                </div>
            </div>

            <!-- LISTA DE TEMAS DE LA MATERIA -->
            <div class="lista-temas">
                <span class="titulo"><i class="fas fa-tag"></i> Temas de la materia:</span>
                <?php if (empty($temas_materia)): ?>
                    <span class="sin-temas">No hay temas creados aún</span>
                <?php else: ?>
                    <?php foreach ($temas_materia as $tema): ?>
                        <span class="tema-tag">
                            <span class="orden"><?php echo $tema['orden']; ?>.</span>
                            <?php echo htmlspecialchars($tema['nombre']); ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-sm btn-view" onclick="verMateria(<?php echo $materia['id']; ?>)">
                    <i class="fas fa-eye"></i> Ver
                </button>
                <button class="btn-sm btn-edit" onclick="editarMateria(<?php echo $materia['id']; ?>)">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn-sm btn-toggle" onclick="abrirModalToggle(<?php echo $materia['id']; ?>, '<?php echo $materia['estado']; ?>')">
                    <i class="fas <?php echo $materia['estado'] == 'activo' ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                    <?php echo $materia['estado'] == 'activo' ? 'Desactivar' : 'Activar'; ?>
                </button>
                <button class="btn-sm btn-delete" onclick="abrirModalEliminar(<?php echo $materia['id']; ?>, '<?php echo addslashes($materia['nombre']); ?>')">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Modal de Confirmación para Eliminar -->
<div id="modalEliminar" class="modal-confirm">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> ¿Eliminar Materia?</h3>
        <p id="mensajeEliminar">¿Estás seguro de que deseas eliminar esta materia? Esta acción no se puede deshacer.</p>
        <p style="font-size: 13px; color: #999;">Si tiene estudiantes inscritos, no se podrá eliminar.</p>
        <div class="btn-group">
            <button class="btn-confirm" onclick="confirmarEliminar()">
                <i class="fas fa-trash"></i> Sí, Eliminar
            </button>
            <button class="btn-cancel-modal" onclick="cerrarModal('modalEliminar')">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Activar/Desactivar -->
<div id="modalToggle" class="modal-confirm">
    <div class="modal-content">
        <h3><i class="fas fa-question-circle" style="color: #f39c12;"></i> <span id="tituloToggle">¿Desactivar Materia?</span></h3>
        <p id="mensajeToggle">¿Estás seguro de que deseas cambiar el estado de esta materia?</p>
        <div class="btn-group">
            <button class="btn-confirm-toggle" onclick="confirmarToggle()">
                <i class="fas fa-check"></i> Sí, Cambiar
            </button>
            <button class="btn-cancel-modal" onclick="cerrarModal('modalToggle')">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal para Ver Materia -->
<div id="modalVer" class="modal-confirm">
    <div class="modal-content" style="max-width: 700px; text-align: left;">
        <h3><i class="fas fa-info-circle" style="color: #3498db;"></i> Detalles de la Materia</h3>
        <div id="detallesMateria">
            <p style="color: #999; text-align: center;">Cargando...</p>
        </div>
        <div style="margin-top: 20px; text-align: center;">
            <button class="btn-cancel-modal" onclick="cerrarModal('modalVer')" style="padding: 10px 30px; border: none; border-radius: 8px; background: #95a5a6; color: white; font-weight: 600; cursor: pointer;">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal para Editar Materia -->
<div id="modalEditar" class="modal-confirm">
    <div class="modal-content" style="max-width: 600px; text-align: left; max-height: 90vh; overflow-y: auto;">
        <h3><i class="fas fa-edit" style="color: #3498db;"></i> Editar Materia</h3>
        <form id="formEditarMateria" method="POST" action="actions/editar_materia.php">
            <input type="hidden" name="id" id="editId">
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Nombre *</label>
                <input type="text" name="nombre" id="editNombre" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Descripción</label>
                <textarea name="descripcion" id="editDescripcion" rows="3" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">Estado</label>
                <select name="estado" id="editEstado" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <hr style="margin: 20px 0; border-color: #e0e0e0;">

            <!-- Temas de la materia -->
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 10px; font-weight: 500; color: #555;">
                    <i class="fas fa-list"></i> Temas de la materia
                </label>
                <div id="editTemasContainer">
                    <!-- Los temas se cargarán aquí vía AJAX -->
                    <p style="color: #999;">Cargando temas...</p>
                </div>
                <button type="button" class="btn-add-tema" onclick="agregarTemaEditar()" style="margin-top: 10px; background: #9b59b6;">
                    <i class="fas fa-plus"></i> Agregar Tema
                </button>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="flex: 1; background: #3498db; color: white; padding: 10px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
                <button type="button" onclick="cerrarModal('modalEditar')" style="flex: 1; background: #95a5a6; color: white; padding: 10px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variables para almacenar los datos de la acción
var accionId = 0;
var accionTipo = '';
var accionEstado = '';
var contadorTemasEditar = 0;

// Función para ver materia (ahora con temas)
function verMateria(id) {
    fetch('get_materia.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var m = data.materia;
                var temas = data.temas || [];
                
                var htmlTemas = '';
                if (temas.length > 0) {
                    temas.forEach(function(tema) {
                        htmlTemas += `
                            <div class="detalle-tema">
                                <span class="tema-orden">#${tema.orden}</span>
                                <div class="tema-nombre">${tema.nombre}</div>
                                ${tema.descripcion ? `<div class="tema-desc">${tema.descripcion}</div>` : ''}
                            </div>
                        `;
                    });
                } else {
                    htmlTemas = '<p style="color: #999; text-align: center;">No hay temas en esta materia</p>';
                }
                
                document.getElementById('detallesMateria').innerHTML = `
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong style="color: #2c3e50;">Nombre:</strong>
                        <span style="color: #555;">${m.nombre}</span>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong style="color: #2c3e50;">Descripción:</strong>
                        <span style="color: #555;">${m.descripcion || 'Sin descripción'}</span>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong style="color: #2c3e50;">Estado:</strong>
                        <span class="badge ${m.estado == 'activo' ? 'badge-activo' : 'badge-inactivo'}">
                            ${m.estado.charAt(0).toUpperCase() + m.estado.slice(1)}
                        </span>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong style="color: #2c3e50;">Fecha de creación:</strong>
                        <span style="color: #555;">${new Date(m.fecha_creacion).toLocaleDateString('es-ES')}</span>
                    </div>
                    <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong style="color: #2c3e50; display: block; margin-bottom: 10px;"><i class="fas fa-list"></i> Temas (${temas.length}):</strong>
                        ${htmlTemas}
                    </div>
                `;
            } else {
                document.getElementById('detallesMateria').innerHTML = `
                    <p style="color: #e74c3c; text-align: center;">Error al cargar los detalles</p>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detallesMateria').innerHTML = `
                <p style="color: #e74c3c; text-align: center;">Error de conexión</p>
            `;
        });
    
    document.getElementById('modalVer').classList.add('show');
}

// Función para editar materia (con temas)
function editarMateria(id) {
    fetch('get_materia.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var m = data.materia;
                var temas = data.temas || [];
                
                document.getElementById('editId').value = m.id;
                document.getElementById('editNombre').value = m.nombre;
                document.getElementById('editDescripcion').value = m.descripcion || '';
                document.getElementById('editEstado').value = m.estado;
                
                // Cargar temas en el formulario de edición
                var container = document.getElementById('editTemasContainer');
                container.innerHTML = '';
                contadorTemasEditar = 0;
                
                if (temas.length > 0) {
                    temas.forEach(function(tema) {
                        contadorTemasEditar++;
                        agregarTemaEditarHTML(container, tema.id, tema.nombre, tema.descripcion, tema.orden);
                    });
                } else {
                    // Si no hay temas, agregar uno vacío
                    contadorTemasEditar++;
                    agregarTemaEditarHTML(container, 0, '', '', 1);
                }
                
                document.getElementById('modalEditar').classList.add('show');
            } else {
                alert('Error al cargar los datos de la materia');
            }
        })
        .catch(error => {
            alert('Error de conexión');
        });
}

// Función para agregar tema en edición
function agregarTemaEditarHTML(container, id, nombre, descripcion, orden) {
    var div = document.createElement('div');
    div.className = 'tema-item';
    div.id = 'edit-tema-' + contadorTemasEditar;
    div.innerHTML = `
        <div class="tema-header">
            <span class="tema-numero">Tema #${contadorTemasEditar}</span>
            <button type="button" class="btn-remove-tema" onclick="eliminarTemaEditar(${contadorTemasEditar})">
                <i class="fas fa-times"></i> Eliminar
            </button>
        </div>
        <input type="hidden" name="temas_id[]" value="${id}">
        <input type="text" name="temas_nombre_edit[]" placeholder="Nombre del tema *" class="tema-input" value="${nombre}" required>
        <span class="tema-descripcion-label"><i class="fas fa-info-circle"></i> Descripción del tema (opcional)</span>
        <textarea name="temas_descripcion_edit[]" placeholder="Breve descripción del tema" class="tema-descripcion">${descripcion || ''}</textarea>
        <input type="hidden" name="temas_orden[]" value="${orden}">
    `;
    container.appendChild(div);
    actualizarBotonesEliminarEditar();
}

function agregarTemaEditar() {
    var container = document.getElementById('editTemasContainer');
    contadorTemasEditar++;
    agregarTemaEditarHTML(container, 0, '', '', contadorTemasEditar);
}

function eliminarTemaEditar(id) {
    var temaItem = document.getElementById('edit-tema-' + id);
    var container = document.getElementById('editTemasContainer');
    
    if (container.children.length > 1) {
        temaItem.remove();
        actualizarBotonesEliminarEditar();
        actualizarNumerosEditar();
    } else {
        alert('Debe haber al menos un tema en la materia');
    }
}

function actualizarBotonesEliminarEditar() {
    var items = document.querySelectorAll('#editTemasContainer .tema-item');
    var botones = document.querySelectorAll('#editTemasContainer .btn-remove-tema');
    
    if (items.length <= 1) {
        botones.forEach(function(btn) {
            btn.style.display = 'none';
        });
    } else {
        botones.forEach(function(btn) {
            btn.style.display = 'inline-block';
        });
    }
}

function actualizarNumerosEditar() {
    var items = document.querySelectorAll('#editTemasContainer .tema-item');
    items.forEach(function(item, index) {
        var numero = item.querySelector('.tema-numero');
        if (numero) {
            numero.textContent = 'Tema #' + (index + 1);
        }
    });
}

// Abrir modal de eliminar
function abrirModalEliminar(id, nombre) {
    accionId = id;
    accionTipo = 'eliminar';
    document.getElementById('mensajeEliminar').innerHTML = 
        '¿Estás seguro de que deseas eliminar la materia <strong>"' + nombre + '"</strong>? Esta acción no se puede deshacer.';
    document.getElementById('modalEliminar').classList.add('show');
}

// Abrir modal de toggle
function abrirModalToggle(id, estado) {
    accionId = id;
    accionTipo = 'toggle';
    accionEstado = estado;
    
    var nuevoEstado = estado == 'activo' ? 'desactivar' : 'activar';
    var titulo = estado == 'activo' ? '¿Desactivar Materia?' : '¿Activar Materia?';
    var mensaje = estado == 'activo' ? 
        '¿Estás seguro de que deseas desactivar esta materia? Los estudiantes no podrán acceder a ella.' :
        '¿Estás seguro de que deseas activar esta materia? Los estudiantes podrán acceder a ella nuevamente.';
    
    document.getElementById('tituloToggle').textContent = titulo;
    document.getElementById('mensajeToggle').textContent = mensaje;
    document.getElementById('modalToggle').classList.add('show');
}

// Confirmar eliminar
function confirmarEliminar() {
    if (accionId > 0) {
        window.location.href = 'mis-materias.php?delete=confirm&id=' + accionId;
    }
    cerrarModal('modalEliminar');
}

// Confirmar toggle
function confirmarToggle() {
    if (accionId > 0) {
        var nuevoEstado = accionEstado == 'activo' ? 'inactivo' : 'activo';
        window.location.href = 'mis-materias.php?toggle=1&id=' + accionId + '&estado=' + nuevoEstado;
    }
    cerrarModal('modalToggle');
}

// Cerrar modal
function cerrarModal(id) {
    document.getElementById(id).classList.remove('show');
    accionId = 0;
    accionTipo = '';
    accionEstado = '';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    if (event.target.classList.contains('modal-confirm')) {
        event.target.classList.remove('show');
        accionId = 0;
        accionTipo = '';
        accionEstado = '';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>