<?php
$page_title = 'Foros de Discusión';
$page_icon = 'comments';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

// Procesar nueva pregunta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nueva_pregunta') {
    $id_tema = $_POST['id_tema'];
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);
    
    if (empty($id_tema) || empty($titulo) || empty($contenido)) {
        $_SESSION['error'] = 'Todos los campos son obligatorios';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO PreguntasForo (id_tema, id_usuario, titulo, contenido) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id_tema, $usuario_id, $titulo, $contenido]);
            $_SESSION['success'] = 'Pregunta creada correctamente';
            header('Location: foros.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al crear pregunta: ' . $e->getMessage();
        }
    }
}

// Procesar nueva respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nueva_respuesta') {
    $id_pregunta = $_POST['id_pregunta'];
    $contenido = trim($_POST['contenido']);
    
    if (empty($contenido)) {
        $_SESSION['error'] = 'El contenido de la respuesta es obligatorio';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO RespuestasForo (id_pregunta, id_usuario, contenido) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id_pregunta, $usuario_id, $contenido]);
            $_SESSION['success'] = 'Respuesta publicada correctamente';
            header('Location: foros.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al publicar respuesta: ' . $e->getMessage();
        }
    }
}

// Obtener preguntas del foro
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            CONCAT(u.nombre, ' ', u.apellido) as usuario_nombre,
            t.nombre as tema_nombre,
            m.nombre as materia_nombre,
            (SELECT COUNT(*) FROM RespuestasForo WHERE id_pregunta = p.id) as total_respuestas
        FROM PreguntasForo p
        JOIN Usuarios u ON p.id_usuario = u.id
        JOIN Temas t ON p.id_tema = t.id
        JOIN Materias m ON t.id_materia = m.id
        JOIN Inscripciones i ON i.id_materia = m.id
        WHERE i.id_usuario = ?
        ORDER BY p.fecha_creacion DESC
    ");
    $stmt->execute([$usuario_id]);
    $preguntas = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $preguntas = [];
    $_SESSION['error'] = 'Error al cargar foros';
}
?>

<style>
    .foro-container {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .pregunta-card {
        border-bottom: 1px solid #f0f0f0;
        padding: 15px 0;
    }
    .pregunta-card:last-child {
        border-bottom: none;
    }
    .pregunta-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .pregunta-card .header h4 {
        margin: 0;
        color: #2c3e50;
    }
    .pregunta-card .header .badge-tema {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
    }
    .pregunta-card .meta {
        color: #999;
        font-size: 13px;
        margin: 5px 0;
    }
    .pregunta-card .contenido {
        color: #666;
        margin: 10px 0;
    }
    .pregunta-card .respuestas {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 10px;
    }
    .pregunta-card .respuesta-item {
        padding: 10px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    .pregunta-card .respuesta-item:last-child {
        border-bottom: none;
    }
    .pregunta-card .respuesta-item .autor {
        font-weight: 500;
        color: #2c3e50;
    }
    .pregunta-card .respuesta-item .fecha {
        color: #999;
        font-size: 12px;
        margin-left: 10px;
    }
    .pregunta-card .respuesta-item .texto {
        color: #666;
        margin-top: 5px;
    }
    .btn-responder {
        background: #9b59b6;
        color: white;
        padding: 6px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        margin-top: 10px;
    }
    .btn-responder:hover {
        background: #8e44ad;
    }
    .btn-nueva-pregunta {
        background: #3498db;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        margin-bottom: 20px;
    }
    .btn-nueva-pregunta:hover {
        background: #2980b9;
    }
    .form-responder {
        margin-top: 10px;
        display: none;
    }
    .form-responder textarea {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        resize: vertical;
        min-height: 60px;
    }
    .form-responder textarea:focus {
        outline: none;
        border-color: #9b59b6;
    }
    .form-responder .btn-enviar {
        background: #2ecc71;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        margin-top: 5px;
    }
    .form-responder .btn-enviar:hover {
        background: #27ae60;
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
    }
    .modal-content h3 {
        margin-bottom: 20px;
        color: #2c3e50;
    }
    .modal-content .form-group {
        margin-bottom: 15px;
    }
    .modal-content .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
    }
    .modal-content .form-group input,
    .modal-content .form-group textarea,
    .modal-content .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    .modal-content .form-group input:focus,
    .modal-content .form-group textarea:focus,
    .modal-content .form-group select:focus {
        outline: none;
        border-color: #3498db;
    }
    .modal-content .btn-submit {
        background: #2ecc71;
        color: white;
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
    }
    .modal-content .btn-submit:hover {
        background: #27ae60;
    }
    .modal-content .btn-cancel {
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
    .modal-content .btn-cancel:hover {
        background: #7f8c8d;
    }
</style>

<h3><i class="fas fa-comments"></i> Foros de Discusión</h3>

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

<div class="foro-container">
    <button onclick="openModal()" class="btn-nueva-pregunta">
        <i class="fas fa-question-circle"></i> Nueva Pregunta
    </button>
    
    <?php if (empty($preguntas)): ?>
        <p style="text-align: center; color: #999; padding: 30px;">No hay preguntas en los foros</p>
    <?php else: ?>
        <?php foreach ($preguntas as $pregunta): ?>
            <div class="pregunta-card">
                <div class="header">
                    <h4><?php echo htmlspecialchars($pregunta['titulo']); ?></h4>
                    <span class="badge-tema">
                        <?php echo htmlspecialchars($pregunta['materia_nombre']); ?> - <?php echo htmlspecialchars($pregunta['tema_nombre']); ?>
                    </span>
                </div>
                <div class="meta">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($pregunta['usuario_nombre']); ?>
                    <i class="fas fa-clock" style="margin-left: 10px;"></i> <?php echo date('d/m/Y H:i', strtotime($pregunta['fecha_creacion'])); ?>
                    <i class="fas fa-comment" style="margin-left: 10px;"></i> <?php echo $pregunta['total_respuestas']; ?> respuestas
                </div>
                <div class="contenido">
                    <?php echo nl2br(htmlspecialchars($pregunta['contenido'])); ?>
                </div>
                
                <!-- Respuestas -->
                <div class="respuestas">
                    <?php 
                    // Obtener respuestas de esta pregunta
                    try {
                        $stmt = $pdo->prepare("
                            SELECT r.*, CONCAT(u.nombre, ' ', u.apellido) as usuario_nombre
                            FROM RespuestasForo r
                            JOIN Usuarios u ON r.id_usuario = u.id
                            WHERE r.id_pregunta = ?
                            ORDER BY r.fecha_creacion ASC
                        ");
                        $stmt->execute([$pregunta['id']]);
                        $respuestas = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        $respuestas = [];
                    }
                    ?>
                    
                    <?php if (!empty($respuestas)): ?>
                        <?php foreach ($respuestas as $respuesta): ?>
                            <div class="respuesta-item">
                                <div>
                                    <span class="autor"><?php echo htmlspecialchars($respuesta['usuario_nombre']); ?></span>
                                    <span class="fecha"><?php echo date('d/m/Y H:i', strtotime($respuesta['fecha_creacion'])); ?></span>
                                </div>
                                <div class="texto"><?php echo nl2br(htmlspecialchars($respuesta['contenido'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Formulario de respuesta -->
                    <button onclick="mostrarFormulario(<?php echo $pregunta['id']; ?>)" class="btn-responder">
                        <i class="fas fa-reply"></i> Responder
                    </button>
                    
                    <div id="form-respuesta-<?php echo $pregunta['id']; ?>" class="form-responder">
                        <form method="POST">
                            <input type="hidden" name="action" value="nueva_respuesta">
                            <input type="hidden" name="id_pregunta" value="<?php echo $pregunta['id']; ?>">
                            <textarea name="contenido" placeholder="Escribe tu respuesta..." required></textarea>
                            <button type="submit" class="btn-enviar">
                                <i class="fas fa-paper-plane"></i> Publicar Respuesta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Nueva Pregunta -->
<div id="preguntaModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-question-circle"></i> Nueva Pregunta</h3>
        <form method="POST">
            <input type="hidden" name="action" value="nueva_pregunta">
            
            <div class="form-group">
                <label>Materia</label>
                <select name="id_tema" required>
                    <option value="">Seleccionar materia y tema...</option>
                    <?php 
                    try {
                        $stmt = $pdo->prepare("
                            SELECT t.id, t.nombre as tema_nombre, m.nombre as materia_nombre
                            FROM Temas t
                            JOIN Materias m ON t.id_materia = m.id
                            JOIN Inscripciones i ON i.id_materia = m.id
                            WHERE i.id_usuario = ? AND m.estado = 'activo'
                            ORDER BY m.nombre, t.orden
                        ");
                        $stmt->execute([$usuario_id]);
                        $temas = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        $temas = [];
                    }
                    ?>
                    <?php foreach ($temas as $tema): ?>
                        <option value="<?php echo $tema['id']; ?>">
                            <?php echo htmlspecialchars($tema['materia_nombre'] . ' - ' . $tema['tema_nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Título</label>
                <input type="text" name="titulo" required placeholder="Resumen de tu pregunta">
            </div>
            
            <div class="form-group">
                <label>Contenido</label>
                <textarea name="contenido" rows="4" required placeholder="Describe tu pregunta en detalle"></textarea>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Publicar Pregunta
            </button>
            <button type="button" onclick="closeModal()" class="btn-cancel">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('preguntaModal').classList.add('show');
}

function closeModal() {
    document.getElementById('preguntaModal').classList.remove('show');
}

function mostrarFormulario(preguntaId) {
    const form = document.getElementById('form-respuesta-' + preguntaId);
    if (form.style.display === 'block') {
        form.style.display = 'none';
    } else {
        form.style.display = 'block';
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('preguntaModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>