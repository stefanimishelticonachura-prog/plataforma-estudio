<?php
// =============================================
// 1. PRIMERO: Configuración y procesamiento (ANTES del header)
// =============================================
$page_title = 'Foros de Discusión';
$page_icon = 'comments';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 1) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// =============================================
// 2. PROCESAR ACCIONES (ANTES DEL HEADER)
// =============================================

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

// =============================================
// 3. OBTENER DATOS
// =============================================

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

// Obtener temas para el select del modal
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

// =============================================
// 4. AHORA SÍ: INCLUIR EL HEADER
// =============================================
require_once 'includes/estudiante_header.php';
?>

<style>
    /* ===== RESET & BASE ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* ===== CONTENEDOR PRINCIPAL ===== */
    .foro-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        width: 100%;
    }
    
    .page-title {
        font-size: 24px;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .page-title i {
        color: #3498db;
    }

    /* ===== ALERTAS ===== */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert i {
        font-size: 20px;
    }

    .alert-error {
        background-color: #fee;
        color: #c33;
        border: 1px solid #fcc;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    /* ===== FORO CARD ===== */
    .foro-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s;
    }
    
    .foro-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .btn-nueva-pregunta {
        background: #3498db;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        font-size: 15px;
        margin-bottom: 20px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-nueva-pregunta:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
    }
    
    .btn-nueva-pregunta:active {
        transform: translateY(0);
    }

    /* ===== PREGUNTA CARD ===== */
    .pregunta-card {
        border-bottom: 1px solid #f0f0f0;
        padding: 20px 0;
        transition: all 0.3s;
    }
    
    .pregunta-card:last-child {
        border-bottom: none;
    }
    
    .pregunta-card:hover {
        background: #fafafa;
        margin: 0 -10px;
        padding: 20px 10px;
        border-radius: 8px;
    }
    
    .pregunta-card .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .pregunta-card .header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 17px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pregunta-card .header h4 i {
        color: #3498db;
        font-size: 16px;
    }
    
    .pregunta-card .header .badge-tema {
        background: #e3f2fd;
        color: #1976d2;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .pregunta-card .header .badge-tema i {
        font-size: 11px;
    }
    
    .pregunta-card .meta {
        color: #999;
        font-size: 13px;
        margin: 8px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .pregunta-card .meta span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .pregunta-card .meta i {
        color: #3498db;
        font-size: 13px;
    }
    
    .pregunta-card .contenido {
        color: #555;
        margin: 10px 0;
        word-wrap: break-word;
        line-height: 1.6;
        font-size: 14px;
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 8px;
        border-left: 3px solid #3498db;
    }

    /* ===== RESPUESTAS ===== */
    .pregunta-card .respuestas {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-top: 10px;
    }
    
    .pregunta-card .respuesta-item {
        padding: 10px 0;
        border-bottom: 1px solid #e8e8e8;
    }
    
    .pregunta-card .respuesta-item:last-child {
        border-bottom: none;
    }
    
    .pregunta-card .respuesta-item .autor {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pregunta-card .respuesta-item .autor .avatar-mini {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .pregunta-card .respuesta-item .fecha {
        color: #999;
        font-size: 12px;
    }
    
    .pregunta-card .respuesta-item .texto {
        color: #555;
        margin-top: 5px;
        word-wrap: break-word;
        line-height: 1.5;
        font-size: 14px;
        padding-left: 36px;
    }
    
    .btn-responder {
        background: #9b59b6;
        color: white;
        padding: 6px 18px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        margin-top: 10px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-responder:hover {
        background: #8e44ad;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(155, 89, 182, 0.4);
    }
    
    .btn-responder:active {
        transform: translateY(0);
    }
    
    .btn-responder.oculto {
        background: #95a5a6;
    }
    
    .btn-responder.oculto:hover {
        background: #7f8c8d;
    }

    /* ===== FORMULARIO RESPONDER ===== */
    .form-responder {
        margin-top: 10px;
        display: none;
        animation: slideDown 0.3s ease;
    }
    
    .form-responder.active {
        display: block;
    }
    
    .form-responder textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        resize: vertical;
        min-height: 70px;
        font-family: inherit;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    
    .form-responder textarea:focus {
        outline: none;
        border-color: #9b59b6;
    }
    
    .form-responder .btn-enviar {
        background: #2ecc71;
        color: white;
        padding: 8px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        margin-top: 8px;
        transition: all 0.3s;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .form-responder .btn-enviar:hover {
        background: #27ae60;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.4);
    }
    
    .form-responder .btn-enviar:active {
        transform: translateY(0);
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .empty-state i {
        font-size: 64px;
        color: #ccc;
        display: block;
        margin-bottom: 20px;
    }
    
    .empty-state h4 {
        color: #666;
        margin-bottom: 10px;
        font-size: 20px;
    }
    
    .empty-state p {
        color: #999;
        font-size: 14px;
    }

    /* ============================================= */
    /* MODAL NUEVA PREGUNTA */
    /* ============================================= */
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
        padding: 20px;
        backdrop-filter: blur(4px);
    }
    
    .modal.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 15px;
        max-width: 550px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalSlideUp 0.3s ease;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    @keyframes modalSlideUp {
        from { transform: translateY(-30px) scale(0.95); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    
    .modal-content h3 {
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 22px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-content h3 i {
        color: #3498db;
    }
    
    .modal-content .form-group {
        margin-bottom: 15px;
    }
    
    .modal-content .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
        font-size: 14px;
    }
    
    .modal-content .form-group label .required {
        color: #e74c3c;
    }
    
    .modal-content .form-group input,
    .modal-content .form-group textarea,
    .modal-content .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.3s;
    }
    
    .modal-content .form-group input:focus,
    .modal-content .form-group textarea:focus,
    .modal-content .form-group select:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .modal-content .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .modal-content .btn-submit {
        background: #2ecc71;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        transition: all 0.3s;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .modal-content .btn-submit:hover {
        background: #27ae60;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
    }
    
    .modal-content .btn-submit:active {
        transform: translateY(0);
    }
    
    .modal-content .btn-cancel {
        background: #95a5a6;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        margin-top: 10px;
        transition: all 0.3s;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .modal-content .btn-cancel:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }
    
    .modal-content .btn-cancel:active {
        transform: translateY(0);
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .foro-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .pregunta-card .header h4 {
            font-size: 16px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .foro-container {
            padding: 12px;
        }
        
        .page-title {
            font-size: 20px;
        }
        
        .foro-card {
            padding: 15px;
            border-radius: 10px;
        }
        
        .btn-nueva-pregunta {
            width: 100%;
            justify-content: center;
            padding: 10px 20px;
            font-size: 14px;
        }
        
        .pregunta-card {
            padding: 15px 0;
        }
        
        .pregunta-card .header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .pregunta-card .header h4 {
            font-size: 15px;
        }
        
        .pregunta-card .meta {
            font-size: 12px;
            gap: 10px;
        }
        
        .pregunta-card .contenido {
            font-size: 13px;
            padding: 10px 12px;
        }
        
        .pregunta-card .respuestas {
            padding: 12px;
        }
        
        .pregunta-card .respuesta-item .texto {
            font-size: 13px;
            padding-left: 0;
            margin-top: 8px;
        }
        
        .pregunta-card .respuesta-item .autor {
            font-size: 13px;
        }
        
        .btn-responder {
            width: 100%;
            justify-content: center;
            padding: 8px 16px;
        }
        
        .form-responder textarea {
            font-size: 13px;
            min-height: 60px;
        }
        
        .form-responder .btn-enviar {
            width: 100%;
            justify-content: center;
        }
        
        .modal-content {
            padding: 20px;
            margin: 10px;
        }
        
        .modal-content h3 {
            font-size: 18px;
        }
        
        .modal-content .form-group label {
            font-size: 13px;
        }
        
        .modal-content .form-group input,
        .modal-content .form-group textarea,
        .modal-content .form-group select {
            font-size: 13px;
            padding: 8px 10px;
        }
        
        .modal-content .btn-submit,
        .modal-content .btn-cancel {
            font-size: 14px;
            padding: 10px 20px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .foro-container {
            padding: 8px;
        }
        
        .page-title {
            font-size: 17px;
        }
        
        .page-title i {
            font-size: 16px;
        }
        
        .foro-card {
            padding: 12px;
        }
        
        .btn-nueva-pregunta {
            font-size: 13px;
            padding: 8px 16px;
        }
        
        .pregunta-card {
            padding: 12px 0;
        }
        
        .pregunta-card .header h4 {
            font-size: 14px;
        }
        
        .pregunta-card .header .badge-tema {
            font-size: 10px;
            padding: 2px 8px;
        }
        
        .pregunta-card .meta {
            font-size: 11px;
            gap: 8px;
        }
        
        .pregunta-card .contenido {
            font-size: 12px;
            padding: 8px 10px;
        }
        
        .pregunta-card .respuestas {
            padding: 10px;
        }
        
        .pregunta-card .respuesta-item {
            padding: 8px 0;
        }
        
        .pregunta-card .respuesta-item .autor {
            font-size: 12px;
        }
        
        .pregunta-card .respuesta-item .texto {
            font-size: 12px;
        }
        
        .btn-responder {
            font-size: 12px;
            padding: 6px 14px;
        }
        
        .form-responder textarea {
            font-size: 12px;
            min-height: 50px;
            padding: 8px 10px;
        }
        
        .form-responder .btn-enviar {
            font-size: 13px;
            padding: 6px 16px;
        }
        
        .modal-content {
            padding: 15px;
        }
        
        .modal-content h3 {
            font-size: 16px;
        }
        
        .modal-content .form-group {
            margin-bottom: 10px;
        }
        
        .modal-content .form-group input,
        .modal-content .form-group textarea,
        .modal-content .form-group select {
            font-size: 12px;
            padding: 6px 8px;
        }
        
        .modal-content .btn-submit,
        .modal-content .btn-cancel {
            font-size: 13px;
            padding: 8px 16px;
        }
        
        .empty-state {
            padding: 40px 15px;
        }
        
        .empty-state i {
            font-size: 48px;
        }
        
        .empty-state h4 {
            font-size: 17px;
        }
        
        .empty-state p {
            font-size: 13px;
        }
        
        .alert {
            padding: 10px 14px;
            font-size: 13px;
            border-radius: 8px;
        }
        
        .alert i {
            font-size: 16px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES MUY PEQUEÑOS ===== */
    @media (max-width: 360px) {
        .foro-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .pregunta-card .header h4 {
            font-size: 13px;
        }
        
        .modal-content {
            padding: 12px;
        }
        
        .modal-content h3 {
            font-size: 14px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .foro-container {
            padding: 10px;
        }
        
        .modal-content {
            max-height: 95vh;
        }
        
        .modal-content .form-group {
            margin-bottom: 8px;
        }
        
        .modal-content .form-group textarea {
            min-height: 50px;
        }
        
        .pregunta-card .contenido {
            padding: 8px 12px;
        }
        
        .pregunta-card .respuestas {
            padding: 10px;
        }
    }

    /* ===== MEJORAS DE ACCESIBILIDAD ===== */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* ===== SCROLL SUAVE ===== */
    html {
        scroll-behavior: smooth;
    }

    /* ===== SELECTION ===== */
    ::selection {
        background: #3498db;
        color: white;
    }

    /* ===== UTILITY ===== */
    .hidden {
        display: none !important;
    }
</style>

<div class="foro-container">
    <h3 class="page-title"><i class="fas fa-comments"></i> Foros de Discusión</h3>

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

    <div class="foro-card">
        <button onclick="openModal()" class="btn-nueva-pregunta">
            <i class="fas fa-question-circle"></i> Nueva Pregunta
        </button>
        
        <?php if (empty($preguntas)): ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h4>No hay preguntas en los foros</h4>
                <p>Sé el primero en hacer una pregunta sobre tus materias</p>
            </div>
        <?php else: ?>
            <?php foreach ($preguntas as $pregunta): ?>
                <div class="pregunta-card">
                    <div class="header">
                        <h4><i class="fas fa-question-circle"></i> <?php echo htmlspecialchars($pregunta['titulo']); ?></h4>
                        <span class="badge-tema">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($pregunta['materia_nombre']); ?> - <?php echo htmlspecialchars($pregunta['tema_nombre']); ?>
                        </span>
                    </div>
                    <div class="meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($pregunta['usuario_nombre']); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($pregunta['fecha_creacion'])); ?></span>
                        <span><i class="fas fa-comment"></i> <?php echo $pregunta['total_respuestas']; ?> respuestas</span>
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
                                    <div class="autor">
                                        <span class="avatar-mini">
                                            <?php echo strtoupper(substr($respuesta['usuario_nombre'], 0, 1)); ?>
                                        </span>
                                        <?php echo htmlspecialchars($respuesta['usuario_nombre']); ?>
                                        <span class="fecha"><?php echo date('d/m/Y H:i', strtotime($respuesta['fecha_creacion'])); ?></span>
                                    </div>
                                    <div class="texto"><?php echo nl2br(htmlspecialchars($respuesta['contenido'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; font-size: 13px; text-align: center; padding: 5px 0;">
                                <i class="fas fa-info-circle"></i> Sé el primero en responder
                            </p>
                        <?php endif; ?>
                        
                        <!-- Formulario de respuesta -->
                        <button onclick="mostrarFormulario(<?php echo $pregunta['id']; ?>)" class="btn-responder" id="btn-responder-<?php echo $pregunta['id']; ?>">
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
</div>

<!-- Modal Nueva Pregunta -->
<div id="preguntaModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-question-circle"></i> Nueva Pregunta</h3>
        <form method="POST">
            <input type="hidden" name="action" value="nueva_pregunta">
            
            <div class="form-group">
                <label>Materia y Tema <span class="required">*</span></label>
                <select name="id_tema" required>
                    <option value="">Seleccionar materia y tema...</option>
                    <?php foreach ($temas as $tema): ?>
                        <option value="<?php echo $tema['id']; ?>">
                            <?php echo htmlspecialchars($tema['materia_nombre'] . ' - ' . $tema['tema_nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Título <span class="required">*</span></label>
                <input type="text" name="titulo" required placeholder="Resumen de tu pregunta">
            </div>
            
            <div class="form-group">
                <label>Contenido <span class="required">*</span></label>
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
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('preguntaModal').classList.remove('show');
    document.body.style.overflow = '';
}

function mostrarFormulario(preguntaId) {
    const form = document.getElementById('form-respuesta-' + preguntaId);
    const btn = document.getElementById('btn-responder-' + preguntaId);
    
    if (form.classList.contains('active')) {
        form.classList.remove('active');
        btn.classList.remove('oculto');
    } else {
        form.classList.add('active');
        btn.classList.add('oculto');
        // Enfocar el textarea
        const textarea = form.querySelector('textarea');
        if (textarea) {
            setTimeout(() => textarea.focus(), 100);
        }
    }
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('preguntaModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Cerrar formularios de respuesta al hacer clic fuera
document.addEventListener('click', function(e) {
    document.querySelectorAll('.form-responder').forEach(function(form) {
        if (form.classList.contains('active')) {
            const preguntaId = form.id.replace('form-respuesta-', '');
            const btn = document.getElementById('btn-responder-' + preguntaId);
            const isClickInside = form.contains(e.target) || (btn && btn.contains(e.target));
            
            if (!isClickInside) {
                form.classList.remove('active');
                if (btn) btn.classList.remove('oculto');
            }
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>