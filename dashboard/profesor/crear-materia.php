<?php
// PRIMERO: Procesar el formulario (antes de cualquier salida HTML)
$page_title = 'Crear Materia';
$page_icon = 'plus-circle';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté logueado y sea profesor
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Procesar el formulario ANTES de mostrar HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $estado = $_POST['estado'] ?? 'activo';
    
    // Obtener temas y sus descripciones
    $temas_nombres = $_POST['temas_nombre'] ?? [];
    $temas_descripciones = $_POST['temas_descripcion'] ?? [];
    
    if (empty($nombre)) {
        $_SESSION['error'] = 'El nombre de la materia es obligatorio';
    } else {
        try {
            // Iniciar transacción
            $pdo->beginTransaction();
            
            // 1. Insertar la materia
            $stmt = $pdo->prepare("
                INSERT INTO Materias (nombre, descripcion, id_profesor, estado) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $descripcion, $usuario_id, $estado]);
            $materia_id = $pdo->lastInsertId();
            
            // 2. Insertar los temas con sus descripciones
            if (!empty($temas_nombres)) {
                $stmt_tema = $pdo->prepare("
                    INSERT INTO Temas (id_materia, nombre, descripcion, orden) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $orden = 1;
                foreach ($temas_nombres as $index => $tema_nombre) {
                    $tema_nombre = trim($tema_nombre);
                    $tema_descripcion = trim($temas_descripciones[$index] ?? '');
                    
                    if (!empty($tema_nombre)) {
                        $stmt_tema->execute([$materia_id, $tema_nombre, $tema_descripcion, $orden]);
                        $orden++;
                    }
                }
            }
            
            // Confirmar transacción
            $pdo->commit();
            
            $_SESSION['success'] = 'Materia y temas creados correctamente';
            header('Location: mis-materias.php');
            exit();
            
        } catch (PDOException $e) {
            // Revertir transacción en caso de error
            $pdo->rollBack();
            $_SESSION['error'] = 'Error al crear materia: ' . $e->getMessage();
        }
    }
}

// AHORA SÍ: Incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    .form-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        max-width: 800px;
        margin: 0 auto;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
    }
    .form-group label .required {
        color: #e74c3c;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #9b59b6;
    }
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    .btn-submit {
        background: #9b59b6;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.3s;
        width: 100%;
    }
    .btn-submit:hover {
        background: #8e44ad;
    }
    .btn-cancel {
        background: #95a5a6;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.3s;
        width: 100%;
        margin-top: 10px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
    }
    .btn-cancel:hover {
        background: #7f8c8d;
    }
    .temas-container {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 2px dashed #e0e0e0;
        max-height: 400px;
        overflow-y: auto;
    }
    .tema-item {
        background: white;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 10px;
        border: 1px solid #e0e0e0;
    }
    .tema-item .tema-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .tema-item .tema-header .tema-numero {
        font-weight: 600;
        color: #9b59b6;
        font-size: 13px;
    }
    .tema-item input,
    .tema-item textarea {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
        margin-bottom: 8px;
    }
    .tema-item input:focus,
    .tema-item textarea:focus {
        outline: none;
        border-color: #9b59b6;
    }
    .tema-item textarea {
        resize: vertical;
        min-height: 50px;
    }
    .tema-item .btn-remove-tema {
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 5px 12px;
        cursor: pointer;
        font-size: 13px;
    }
    .tema-item .btn-remove-tema:hover {
        background: #c0392b;
    }
    .btn-add-tema {
        background: #3498db;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 10px 20px;
        cursor: pointer;
        font-size: 14px;
        margin-top: 10px;
        width: 100%;
    }
    .btn-add-tema:hover {
        background: #2980b9;
    }
    .info-box {
        background: #e8f5e9;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #2e7d32;
        color: #2e7d32;
        font-size: 14px;
    }
    .info-box i {
        margin-right: 8px;
    }
    .tema-item .tema-descripcion-label {
        font-size: 12px;
        color: #999;
        display: block;
        margin-bottom: 3px;
    }
</style>

<h3><i class="fas fa-plus-circle"></i> Crear Nueva Materia</h3>

<div class="form-container">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="formCrearMateria">
        <!-- Datos de la Materia -->
        <div class="form-group">
            <label>Nombre de la Materia <span class="required">*</span></label>
            <input type="text" name="nombre" required placeholder="Ej: Programación Web" id="nombreMateria">
        </div>
        
        <div class="form-group">
            <label>Descripción</label>
            <textarea name="descripcion" placeholder="Describe el contenido de la materia" id="descripcionMateria"></textarea>
        </div>
        
        <div class="form-group">
            <label>Estado</label>
            <select name="estado" id="estadoMateria">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
        </div>

        <hr style="margin: 25px 0; border-color: #e0e0e0;">

        <!-- Temas de la Materia -->
        <div class="form-group">
            <label><i class="fas fa-list"></i> Temas de la Materia</label>
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                Agrega los temas que tendrá la materia. Cada tema puede tener una descripción opcional.
            </div>
            
            <div class="temas-container" id="temasContainer">
                <div id="listaTemas">
                    <!-- Tema inicial -->
                    <div class="tema-item" id="tema-1">
                        <div class="tema-header">
                            <span class="tema-numero">Tema #1</span>
                            <button type="button" class="btn-remove-tema" onclick="eliminarTema(1)" style="display: none;">
                                <i class="fas fa-times"></i> Eliminar
                            </button>
                        </div>
                        <input type="text" name="temas_nombre[]" placeholder="Nombre del tema *" class="tema-input" required>
                        <span class="tema-descripcion-label"><i class="fas fa-info-circle"></i> Descripción del tema (opcional)</span>
                        <textarea name="temas_descripcion[]" placeholder="Breve descripción del tema" class="tema-descripcion"></textarea>
                    </div>
                </div>
                
                <button type="button" class="btn-add-tema" onclick="agregarTema()">
                    <i class="fas fa-plus"></i> Agregar Tema
                </button>
            </div>
            <small style="color: #999; display: block; margin-top: 5px;">
                <i class="fas fa-info-circle"></i> Puedes agregar cuantos temas necesites. El orden se establecerá automáticamente.
            </small>
        </div>
        
        <button type="submit" class="btn-submit" onclick="return validarTemas()">
            <i class="fas fa-save"></i> Crear Materia con Temas
        </button>
        <a href="mis-materias.php" class="btn-cancel">
            <i class="fas fa-times"></i> Cancelar
        </a>
    </form>
</div>

<script>
var contadorTemas = 1;

function agregarTema() {
    contadorTemas++;
    var lista = document.getElementById('listaTemas');
    var nuevoTema = document.createElement('div');
    nuevoTema.className = 'tema-item';
    nuevoTema.id = 'tema-' + contadorTemas;
    nuevoTema.innerHTML = `
        <div class="tema-header">
            <span class="tema-numero">Tema #${contadorTemas}</span>
            <button type="button" class="btn-remove-tema" onclick="eliminarTema(${contadorTemas})">
                <i class="fas fa-times"></i> Eliminar
            </button>
        </div>
        <input type="text" name="temas_nombre[]" placeholder="Nombre del tema *" class="tema-input" required>
        <span class="tema-descripcion-label"><i class="fas fa-info-circle"></i> Descripción del tema (opcional)</span>
        <textarea name="temas_descripcion[]" placeholder="Breve descripción del tema" class="tema-descripcion"></textarea>
    `;
    lista.appendChild(nuevoTema);
    actualizarBotonesEliminar();
}

function eliminarTema(id) {
    var temaItem = document.getElementById('tema-' + id);
    var lista = document.getElementById('listaTemas');
    
    if (lista.children.length > 1) {
        temaItem.remove();
        actualizarBotonesEliminar();
        actualizarNumeros();
    } else {
        alert('Debe haber al menos un tema en la materia');
    }
}

function actualizarBotonesEliminar() {
    var items = document.querySelectorAll('.tema-item');
    var botones = document.querySelectorAll('.btn-remove-tema');
    
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

function actualizarNumeros() {
    var items = document.querySelectorAll('.tema-item');
    items.forEach(function(item, index) {
        var numero = item.querySelector('.tema-numero');
        if (numero) {
            numero.textContent = 'Tema #' + (index + 1);
        }
    });
}

function validarTemas() {
    var inputs = document.querySelectorAll('.tema-input');
    var vacios = false;
    
    inputs.forEach(function(input) {
        if (input.value.trim() === '') {
            vacios = true;
            input.style.borderColor = '#e74c3c';
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });
    
    if (vacios) {
        alert('Por favor, completa el nombre de todos los temas o elimina los campos vacíos.');
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    actualizarBotonesEliminar();
});
</script>

<?php require_once '../../includes/footer.php'; ?>