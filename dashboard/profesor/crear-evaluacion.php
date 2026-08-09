<?php
// PRIMERO: Procesar el formulario
$page_title = 'Gestión de Evaluaciones';
$page_icon = 'file-signature';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// =============================================
// ELIMINAR EVALUACIÓN (y sus preguntas)
// =============================================
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            // Primero eliminar preguntas (por FK cascade)
            $stmt = $pdo->prepare("DELETE FROM Evaluaciones WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Evaluación eliminada correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al eliminar evaluación: ' . $e->getMessage();
        }
        header('Location: crear-evaluacion.php');
        exit();
    }
}

// =============================================
// ELIMINAR PREGUNTA (AJAX)
// =============================================
if (isset($_GET['delete_pregunta']) && $_GET['delete_pregunta'] > 0) {
    header('Content-Type: application/json');
    $id_pregunta = $_GET['delete_pregunta'];
    try {
        $stmt = $pdo->prepare("DELETE FROM PreguntasEvaluacion WHERE id = ?");
        $stmt->execute([$id_pregunta]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// =============================================
// PROCESAR CREACIÓN/EDICIÓN DE EVALUACIÓN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'crear';
    $id = $_POST['id'] ?? 0;
    $id_tema = $_POST['id_tema'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $tiempo_limite_minutos = $_POST['tiempo_limite_minutos'] ?? null;
    $intentos_permitidos = $_POST['intentos_permitidos'] ?? 1;
    $puntaje_maximo = $_POST['puntaje_maximo'] ?? 100;
    $puntaje_aprobacion = $_POST['puntaje_aprobacion'] ?? 70;
    $estado = $_POST['estado'] ?? 'activo';
    
    // Datos de preguntas
    $preguntas_tipo = $_POST['pregunta_tipo'] ?? [];
    $preguntas_texto = $_POST['pregunta_texto'] ?? [];
    $preguntas_opciones = $_POST['pregunta_opciones'] ?? [];
    $preguntas_respuesta = $_POST['pregunta_respuesta'] ?? [];
    $preguntas_puntaje = $_POST['pregunta_puntaje'] ?? [];
    
    $error = false;
    
    if (empty($id_tema) || empty($titulo)) {
        $_SESSION['error'] = 'El tema y el título son obligatorios';
        $error = true;
    } elseif ($puntaje_aprobacion > $puntaje_maximo) {
        $_SESSION['error'] = 'El puntaje de aprobación no puede ser mayor al puntaje máximo';
        $error = true;
    }
    
    if (!$error) {
        try {
            $pdo->beginTransaction();
            
            if ($action == 'crear') {
                $stmt = $pdo->prepare("
                    INSERT INTO Evaluaciones (id_tema, titulo, descripcion, tiempo_limite_minutos, 
                                           intentos_permitidos, puntaje_maximo, puntaje_aprobacion, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$id_tema, $titulo, $descripcion, $tiempo_limite_minutos, 
                               $intentos_permitidos, $puntaje_maximo, $puntaje_aprobacion, $estado]);
                $evaluacion_id = $pdo->lastInsertId();
                $_SESSION['success'] = 'Evaluación creada correctamente';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE Evaluaciones SET
                        id_tema = ?, titulo = ?, descripcion = ?, 
                        tiempo_limite_minutos = ?, intentos_permitidos = ?, 
                        puntaje_maximo = ?, puntaje_aprobacion = ?, estado = ?
                    WHERE id = ?
                ");
                $stmt->execute([$id_tema, $titulo, $descripcion, $tiempo_limite_minutos, 
                               $intentos_permitidos, $puntaje_maximo, $puntaje_aprobacion, $estado, $id]);
                $evaluacion_id = $id;
                $_SESSION['success'] = 'Evaluación actualizada correctamente';
                
                // Eliminar preguntas existentes (se volverán a crear)
                $stmt = $pdo->prepare("DELETE FROM PreguntasEvaluacion WHERE id_evaluacion = ?");
                $stmt->execute([$evaluacion_id]);
            }
            
            // Guardar preguntas
            if (!empty($preguntas_texto)) {
                $stmt_pregunta = $pdo->prepare("
                    INSERT INTO PreguntasEvaluacion (id_evaluacion, tipo, pregunta, opciones, respuesta_correcta, puntaje, orden) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($preguntas_texto as $index => $texto) {
                    if (empty($texto)) continue;
                    
                    $tipo = $preguntas_tipo[$index] ?? 'opcion_unica';
                    $opciones = $preguntas_opciones[$index] ?? '';
                    $respuesta = $preguntas_respuesta[$index] ?? '';
                    $puntaje = $preguntas_puntaje[$index] ?? 1;
                    
                    // Procesar opciones según el tipo
                    $opciones_json = null;
                    $respuesta_json = null;
                    
                    if ($tipo == 'opcion_unica' || $tipo == 'opcion_multiple' || $tipo == 'verdadero_falso') {
                        // Convertir opciones a array
                        $opciones_array = array_filter(array_map('trim', explode("\n", $opciones)));
                        if (!empty($opciones_array)) {
                            $opciones_json = json_encode($opciones_array);
                            
                            // Procesar respuesta correcta (índices)
                            if (!empty($respuesta)) {
                                $respuesta_indices = [];
                                $respuesta_parts = explode(',', $respuesta);
                                foreach ($respuesta_parts as $part) {
                                    $part = trim($part);
                                    if (is_numeric($part) && isset($opciones_array[$part])) {
                                        $respuesta_indices[] = intval($part);
                                    }
                                }
                                $respuesta_json = json_encode($respuesta_indices);
                            }
                        }
                    } else {
                        // Texto corto o largo - la respuesta es el texto esperado
                        $respuesta_json = json_encode([$respuesta]);
                    }
                    
                    $stmt_pregunta->execute([
                        $evaluacion_id,
                        $tipo,
                        $texto,
                        $opciones_json,
                        $respuesta_json,
                        $puntaje,
                        $index + 1
                    ]);
                }
            }
            
            $pdo->commit();
            header('Location: crear-evaluacion.php');
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Error al guardar evaluación: ' . $e->getMessage();
        }
    }
}

// =============================================
// OBTENER DATOS
// =============================================

// Obtener materias del profesor
try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM Materias WHERE id_profesor = ? AND estado = 'activo' ORDER BY nombre");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias = [];
}

// Obtener temas para el modal
$temas_modal = [];
if (isset($_GET['materia_modal']) && $_GET['materia_modal'] > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM Temas WHERE id_materia = ? ORDER BY orden");
        $stmt->execute([$_GET['materia_modal']]);
        $temas_modal = $stmt->fetchAll();
    } catch (PDOException $e) {
        $temas_modal = [];
    }
}

// Obtener evaluaciones agrupadas por materia
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            t.nombre as tema_nombre,
            t.id as tema_id,
            m.nombre as materia_nombre,
            m.id as materia_id,
            (SELECT COUNT(*) FROM PreguntasEvaluacion WHERE id_evaluacion = e.id) as total_preguntas
        FROM Evaluaciones e
        JOIN Temas t ON e.id_tema = t.id
        JOIN Materias m ON t.id_materia = m.id
        WHERE m.id_profesor = ?
        ORDER BY m.nombre, t.orden, e.fecha_creacion DESC
    ");
    $stmt->execute([$usuario_id]);
    $evaluaciones = $stmt->fetchAll();
} catch (PDOException $e) {
    $evaluaciones = [];
}

// Agrupar evaluaciones por materia
$evaluaciones_por_materia = [];
foreach ($evaluaciones as $eval) {
    if (!isset($evaluaciones_por_materia[$eval['materia_id']])) {
        $evaluaciones_por_materia[$eval['materia_id']] = [
            'nombre' => $eval['materia_nombre'],
            'temas' => []
        ];
    }
    if (!isset($evaluaciones_por_materia[$eval['materia_id']]['temas'][$eval['tema_id']])) {
        $evaluaciones_por_materia[$eval['materia_id']]['temas'][$eval['tema_id']] = [
            'nombre' => $eval['tema_nombre'],
            'evaluaciones' => []
        ];
    }
    $evaluaciones_por_materia[$eval['materia_id']]['temas'][$eval['tema_id']]['evaluaciones'][] = $eval;
}

// Para edición: obtener datos de la evaluación a editar
$evaluacion_editar = null;
$preguntas_existentes = [];
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Evaluaciones WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $evaluacion_editar = $stmt->fetch();
        
        if ($evaluacion_editar) {
            $stmt = $pdo->prepare("SELECT * FROM PreguntasEvaluacion WHERE id_evaluacion = ? ORDER BY orden");
            $stmt->execute([$_GET['edit']]);
            $preguntas_existentes = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $evaluacion_editar = null;
    }
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    .gestion-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .materias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }
    
    .materia-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        border-top: 4px solid #e67e22;
    }
    .materia-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 25px rgba(0,0,0,0.12);
    }
    
    .materia-header {
        background: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .materia-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 18px;
    }
    .materia-header .badge-materia {
        background: #e67e22;
        color: white;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 12px;
    }
    .materia-body {
        padding: 15px 20px;
    }
    
    .tema-item {
        margin-bottom: 15px;
        border-left: 3px solid #f39c12;
        padding-left: 12px;
    }
    .tema-item:last-child {
        margin-bottom: 0;
    }
    .tema-item .tema-titulo {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .tema-item .tema-titulo .badge-tema {
        background: #fef9e7;
        color: #f39c12;
        padding: 1px 10px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: normal;
    }
    
    .evaluacion-item {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }
    .evaluacion-item:hover {
        background: #f0f0f0;
    }
    .evaluacion-item .evaluacion-info {
        flex: 1;
        min-width: 0;
    }
    .evaluacion-item .evaluacion-info .titulo {
        font-size: 13px;
        font-weight: 500;
        color: #2c3e50;
    }
    .evaluacion-item .evaluacion-info .titulo .badge-preguntas {
        background: #e3f2fd;
        color: #1976d2;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
        margin-left: 5px;
    }
    .evaluacion-item .evaluacion-info .subtitulo {
        font-size: 11px;
        color: #999;
        display: block;
        margin-top: 2px;
    }
    .evaluacion-item .evaluacion-info .subtitulo span {
        margin-right: 10px;
    }
    .evaluacion-item .evaluacion-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
        margin-left: 10px;
    }
    .evaluacion-item .evaluacion-actions .btn-sm {
        padding: 3px 8px;
        font-size: 11px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .btn-edit-sm { background: #3498db; color: white; }
    .btn-edit-sm:hover { background: #2980b9; }
    .btn-delete-sm { background: #e74c3c; color: white; }
    .btn-delete-sm:hover { background: #c0392b; }
    .btn-view-sm { background: #2ecc71; color: white; }
    .btn-view-sm:hover { background: #27ae60; }
    
    .sin-evaluaciones {
        color: #999;
        font-size: 13px;
        font-style: italic;
        padding: 8px 0;
    }
    
    .badge-estado-eval {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 500;
    }
    .badge-estado-eval.activa { background: #d4edda; color: #155724; }
    .badge-estado-eval.inactiva { background: #f8d7da; color: #721c24; }
    
    /* BOTÓN FLOTANTE */
    .btn-flotante {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #e67e22;
        color: white;
        border: none;
        border-radius: 50px;
        padding: 15px 25px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(230, 126, 34, 0.4);
        transition: all 0.3s;
        z-index: 999;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .btn-flotante:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 30px rgba(230, 126, 34, 0.5);
        background: #d35400;
    }
    .btn-flotante i {
        font-size: 20px;
    }
    
    /* MODAL */
    .modal-overlay {
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
    }
    .modal-overlay.show {
        display: flex;
    }
    .modal-content {
        background: white;
        border-radius: 15px;
        max-width: 800px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        position: relative;
        animation: modalIn 0.3s ease;
    }
    @keyframes modalIn {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .modal-content .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    .modal-content .modal-header h3 {
        margin: 0;
        color: #2c3e50;
    }
    .modal-content .modal-header .btn-close-modal {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s;
        line-height: 1;
    }
    .modal-content .modal-header .btn-close-modal:hover {
        color: #333;
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
    .modal-content .form-group label .required {
        color: #e74c3c;
    }
    .modal-content .form-group input,
    .modal-content .form-group textarea,
    .modal-content .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    .modal-content .form-group input:focus,
    .modal-content .form-group textarea:focus,
    .modal-content .form-group select:focus {
        outline: none;
        border-color: #e67e22;
    }
    .modal-content .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .modal-content .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .modal-content .btn-submit-modal {
        background: #e67e22;
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
    }
    .modal-content .btn-submit-modal:hover {
        background: #d35400;
    }
    .modal-content .info-box {
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #e67e22;
        font-size: 13px;
    }
    .modal-content .info-box i {
        color: #e67e22;
        margin-right: 8px;
    }
    .modal-content .loading-temas {
        color: #999;
        font-size: 13px;
        padding: 5px 0;
    }
    
    /* PREGUNTAS */
    .pregunta-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 3px solid #e67e22;
        position: relative;
    }
    .pregunta-item .pregunta-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .pregunta-item .pregunta-header .pregunta-numero {
        font-weight: 600;
        color: #e67e22;
        font-size: 14px;
    }
    .pregunta-item .pregunta-header .btn-remove-pregunta {
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 3px 10px;
        cursor: pointer;
        font-size: 12px;
    }
    .pregunta-item .pregunta-header .btn-remove-pregunta:hover {
        background: #c0392b;
    }
    .pregunta-item .form-group {
        margin-bottom: 8px;
    }
    .pregunta-item .form-group label {
        font-size: 12px;
        color: #666;
    }
    .pregunta-item .form-group textarea {
        min-height: 40px;
        resize: vertical;
    }
    .pregunta-item .form-group input[type="text"] {
        width: 100%;
        padding: 8px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 13px;
    }
    .pregunta-item .form-group input[type="text"]:focus {
        outline: none;
        border-color: #e67e22;
    }
    .pregunta-item .form-group textarea {
        width: 100%;
        padding: 8px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 13px;
        font-family: inherit;
    }
    .pregunta-item .form-group textarea:focus {
        outline: none;
        border-color: #e67e22;
    }
    .pregunta-item .form-group select {
        width: 100%;
        padding: 8px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 13px;
    }
    .pregunta-item .form-group select:focus {
        outline: none;
        border-color: #e67e22;
    }
    .pregunta-item .opciones-container {
        margin-top: 5px;
    }
    .pregunta-item .opciones-container textarea {
        min-height: 60px;
        font-size: 13px;
    }
    .pregunta-item .opciones-container .help-text {
        font-size: 11px;
        color: #999;
        margin-top: 3px;
    }
    
    .btn-add-pregunta {
        background: #3498db;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        width: 100%;
        margin-top: 10px;
        transition: background 0.3s;
    }
    .btn-add-pregunta:hover {
        background: #2980b9;
    }
    
    .preguntas-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
    }
    .preguntas-section h4 {
        color: #2c3e50;
        margin-bottom: 15px;
    }
    
    @media (max-width: 768px) {
        .materias-grid {
            grid-template-columns: 1fr;
        }
        .modal-content .form-row {
            grid-template-columns: 1fr;
        }
        .btn-flotante {
            bottom: 20px;
            right: 20px;
            padding: 12px 18px;
            font-size: 14px;
        }
        .btn-flotante span {
            display: none;
        }
        .evaluacion-item {
            flex-wrap: wrap;
        }
        .evaluacion-item .evaluacion-actions {
            margin-left: 0;
            margin-top: 5px;
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<div class="gestion-container">
    <h3><i class="fas fa-file-signature"></i> Mis Evaluaciones</h3>

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

    <!-- VISTA POR MATERIAS -->
    <?php if (empty($evaluaciones_por_materia)): ?>
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <i class="fas fa-file-signature" style="font-size: 64px; color: #ccc; display: block; margin-bottom: 20px;"></i>
            <h4 style="color: #666; margin-bottom: 10px;">No tienes evaluaciones creadas</h4>
            <p style="color: #999; font-size: 14px;">Haz clic en el botón <strong>"+ Nueva Evaluación"</strong> para comenzar</p>
        </div>
    <?php else: ?>
        <div class="materias-grid">
            <?php foreach ($evaluaciones_por_materia as $materia_id => $materia): ?>
                <div class="materia-card">
                    <div class="materia-header">
                        <h4><i class="fas fa-book" style="color: #e67e22; margin-right: 8px;"></i><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                        <span class="badge-materia">
                            <?php 
                            $total_evaluaciones = 0;
                            foreach ($materia['temas'] as $tema) {
                                $total_evaluaciones += count($tema['evaluaciones']);
                            }
                            echo $total_evaluaciones . ' evaluaciones';
                            ?>
                        </span>
                    </div>
                    <div class="materia-body">
                        <?php foreach ($materia['temas'] as $tema_id => $tema): ?>
                            <div class="tema-item">
                                <div class="tema-titulo">
                                    <span><i class="fas fa-tag" style="color: #f39c12; margin-right: 5px;"></i><?php echo htmlspecialchars($tema['nombre']); ?></span>
                                    <span class="badge-tema"><?php echo count($tema['evaluaciones']); ?> evaluaciones</span>
                                </div>
                                
                                <?php if (empty($tema['evaluaciones'])): ?>
                                    <div class="sin-evaluaciones">No hay evaluaciones en este tema</div>
                                <?php else: ?>
                                    <?php foreach ($tema['evaluaciones'] as $eval): ?>
                                        <div class="evaluacion-item">
                                            <div class="evaluacion-info">
                                                <span class="titulo">
                                                    <?php echo htmlspecialchars($eval['titulo']); ?>
                                                    <span class="badge-estado-eval <?php echo $eval['estado']; ?>">
                                                        <?php echo ucfirst($eval['estado']); ?>
                                                    </span>
                                                    <span class="badge-preguntas">
                                                        <i class="fas fa-question-circle"></i> <?php echo $eval['total_preguntas']; ?> preguntas
                                                    </span>
                                                </span>
                                                <span class="subtitulo">
                                                    <span><i class="fas fa-clock"></i> <?php echo $eval['tiempo_limite_minutos'] ?? 'Sin límite'; ?> min</span>
                                                    <span><i class="fas fa-redo"></i> <?php echo $eval['intentos_permitidos']; ?> intentos</span>
                                                    <span><i class="fas fa-star"></i> <?php echo $eval['puntaje_maximo']; ?> pts</span>
                                                    <span><i class="fas fa-check"></i> Aprueba: <?php echo $eval['puntaje_aprobacion']; ?> pts</span>
                                                </span>
                                            </div>
                                            <div class="evaluacion-actions">
                                                <a href="corregir-evaluaciones.php?evaluacion_id=<?php echo $eval['id']; ?>" class="btn-sm btn-view-sm" title="Ver resultados">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                                <a href="crear-evaluacion.php?edit=<?php echo $eval['id']; ?>" class="btn-sm btn-edit-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="crear-evaluacion.php?delete=confirm&id=<?php echo $eval['id']; ?>" class="btn-sm btn-delete-sm" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar esta evaluación y todas sus preguntas?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- BOTÓN FLOTANTE -->
<button class="btn-flotante" onclick="abrirModal()">
    <i class="fas fa-plus-circle"></i>
    <span>Nueva Evaluación</span>
</button>

<!-- MODAL -->
<div class="modal-overlay" id="modalEvaluacion">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-<?php echo $evaluacion_editar ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $evaluacion_editar ? 'Editar Evaluación' : 'Nueva Evaluación'; ?></h3>
            <button class="btn-close-modal" onclick="cerrarModal()">&times;</button>
        </div>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            Configura la evaluación y agrega las preguntas. Puedes agregar tantas preguntas como necesites.
        </div>

        <form method="POST" id="formEvaluacionModal">
            <input type="hidden" name="action" value="<?php echo $evaluacion_editar ? 'editar' : 'crear'; ?>">
            <?php if ($evaluacion_editar): ?>
                <input type="hidden" name="id" value="<?php echo $evaluacion_editar['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Materia <span class="required">*</span></label>
                <select id="materiaSelectModal" name="materia_id" required onchange="cargarTemasModal(this.value)">
                    <option value="">Seleccionar materia...</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?php echo $materia['id']; ?>" 
                            <?php 
                            if ($evaluacion_editar) {
                                $stmt = $pdo->prepare("SELECT t.id_materia FROM Temas t WHERE t.id = ?");
                                $stmt->execute([$evaluacion_editar['id_tema']]);
                                $materia_edit = $stmt->fetch();
                                echo ($materia_edit && $materia_edit['id_materia'] == $materia['id']) ? 'selected' : '';
                            }
                            ?>>
                            <?php echo htmlspecialchars($materia['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tema <span class="required">*</span></label>
                <select id="temaSelectModal" name="id_tema" required>
                    <option value="">Primero selecciona una materia...</option>
                    <?php if ($evaluacion_editar): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT id, nombre FROM Temas WHERE id = ?");
                        $stmt->execute([$evaluacion_editar['id_tema']]);
                        $tema_edit = $stmt->fetch();
                        if ($tema_edit): ?>
                            <option value="<?php echo $tema_edit['id']; ?>" selected>
                                <?php echo htmlspecialchars($tema_edit['nombre']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
                <div id="loadingTemasModal" class="loading-temas" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Cargando temas...
                </div>
            </div>

            <div class="form-group">
                <label>Título <span class="required">*</span></label>
                <input type="text" name="titulo" required placeholder="Ej: Examen Final - Módulo 1" value="<?php echo $evaluacion_editar ? htmlspecialchars($evaluacion_editar['titulo']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" placeholder="Describe el contenido de la evaluación"><?php echo $evaluacion_editar ? htmlspecialchars($evaluacion_editar['descripcion']) : ''; ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tiempo Límite (minutos)</label>
                    <input type="number" name="tiempo_limite_minutos" placeholder="60" min="0" value="<?php echo $evaluacion_editar ? $evaluacion_editar['tiempo_limite_minutos'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Intentos Permitidos</label>
                    <input type="number" name="intentos_permitidos" value="<?php echo $evaluacion_editar ? $evaluacion_editar['intentos_permitidos'] : '1'; ?>" min="1">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Puntaje Máximo</label>
                    <input type="number" name="puntaje_maximo" value="<?php echo $evaluacion_editar ? $evaluacion_editar['puntaje_maximo'] : '100'; ?>" min="1" step="0.5">
                </div>
                <div class="form-group">
                    <label>Puntaje de Aprobación</label>
                    <input type="number" name="puntaje_aprobacion" value="<?php echo $evaluacion_editar ? $evaluacion_editar['puntaje_aprobacion'] : '70'; ?>" min="0" step="0.5">
                </div>
            </div>

            <div class="form-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="activo" <?php echo ($evaluacion_editar && $evaluacion_editar['estado'] == 'activo') ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?php echo ($evaluacion_editar && $evaluacion_editar['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>

            <!-- ============================================= -->
            <!-- SECCIÓN DE PREGUNTAS -->
            <!-- ============================================= -->
            <div class="preguntas-section">
                <h4><i class="fas fa-question-circle"></i> Preguntas de la Evaluación</h4>
                <div id="preguntasContainer">
                    <?php if (!empty($preguntas_existentes)): ?>
                        <?php foreach ($preguntas_existentes as $index => $preg): ?>
                            <div class="pregunta-item" data-index="<?php echo $index; ?>">
                                <div class="pregunta-header">
                                    <span class="pregunta-numero">Pregunta #<?php echo $index + 1; ?></span>
                                    <button type="button" class="btn-remove-pregunta" onclick="eliminarPregunta(this)">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                                <div class="form-group">
                                    <label>Tipo de Pregunta</label>
                                    <select name="pregunta_tipo[]" class="tipo-pregunta" onchange="cambiarTipoPregunta(this)">
                                        <option value="opcion_unica" <?php echo $preg['tipo'] == 'opcion_unica' ? 'selected' : ''; ?>>Opción Única</option>
                                        <option value="opcion_multiple" <?php echo $preg['tipo'] == 'opcion_multiple' ? 'selected' : ''; ?>>Opción Múltiple</option>
                                        <option value="verdadero_falso" <?php echo $preg['tipo'] == 'verdadero_falso' ? 'selected' : ''; ?>>Verdadero / Falso</option>
                                        <option value="texto_corto" <?php echo $preg['tipo'] == 'texto_corto' ? 'selected' : ''; ?>>Texto Corto</option>
                                        <option value="texto_largo" <?php echo $preg['tipo'] == 'texto_largo' ? 'selected' : ''; ?>>Texto Largo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Pregunta</label>
                                    <textarea name="pregunta_texto[]" required><?php echo htmlspecialchars($preg['pregunta']); ?></textarea>
                                </div>
                                <div class="form-group opciones-container">
                                    <label>Opciones (una por línea)</label>
                                    <textarea name="pregunta_opciones[]" class="opciones-textarea"><?php 
                                        $opciones = json_decode($preg['opciones'], true);
                                        echo is_array($opciones) ? implode("\n", $opciones) : '';
                                    ?></textarea>
                                    <div class="help-text">Para Verdadero/Falso usa: Verdadero<enter>Falso</div>
                                </div>
                                <div class="form-group">
                                    <label>Respuesta Correcta (índices separados por coma, ej: 0 o 0,2)</label>
                                    <input type="text" name="pregunta_respuesta[]" placeholder="0,2" value="<?php 
                                        $resp = json_decode($preg['respuesta_correcta'], true);
                                        echo is_array($resp) ? implode(',', $resp) : '';
                                    ?>">
                                </div>
                                <div class="form-group">
                                    <label>Puntaje</label>
                                    <input type="number" name="pregunta_puntaje[]" value="<?php echo $preg['puntaje']; ?>" min="0.5" step="0.5">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Pregunta por defecto -->
                        <div class="pregunta-item" data-index="0">
                            <div class="pregunta-header">
                                <span class="pregunta-numero">Pregunta #1</span>
                                <button type="button" class="btn-remove-pregunta" onclick="eliminarPregunta(this)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                            <div class="form-group">
                                <label>Tipo de Pregunta</label>
                                <select name="pregunta_tipo[]" class="tipo-pregunta" onchange="cambiarTipoPregunta(this)">
                                    <option value="opcion_unica">Opción Única</option>
                                    <option value="opcion_multiple">Opción Múltiple</option>
                                    <option value="verdadero_falso">Verdadero / Falso</option>
                                    <option value="texto_corto">Texto Corto</option>
                                    <option value="texto_largo">Texto Largo</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pregunta</label>
                                <textarea name="pregunta_texto[]" required></textarea>
                            </div>
                            <div class="form-group opciones-container">
                                <label>Opciones (una por línea)</label>
                                <textarea name="pregunta_opciones[]" class="opciones-textarea"></textarea>
                                <div class="help-text">Para Verdadero/Falso usa: Verdadero<enter>Falso</div>
                            </div>
                            <div class="form-group">
                                <label>Respuesta Correcta (índices separados por coma, ej: 0 o 0,2)</label>
                                <input type="text" name="pregunta_respuesta[]" placeholder="0,2">
                            </div>
                            <div class="form-group">
                                <label>Puntaje</label>
                                <input type="number" name="pregunta_puntaje[]" value="1" min="0.5" step="0.5">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="btn-add-pregunta" onclick="agregarPregunta()">
                    <i class="fas fa-plus"></i> Agregar Pregunta
                </button>
            </div>

            <button type="submit" class="btn-submit-modal">
                <i class="fas fa-save"></i> <?php echo $evaluacion_editar ? 'Actualizar Evaluación' : 'Crear Evaluación'; ?>
            </button>
        </form>
    </div>
</div>

<script>
var contadorPreguntas = <?php echo !empty($preguntas_existentes) ? count($preguntas_existentes) : 1; ?>;

function abrirModal() {
    document.getElementById('modalEvaluacion').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarModal() {
    document.getElementById('modalEvaluacion').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('modalEvaluacion').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});

function cargarTemasModal(materiaId) {
    var select = document.getElementById('temaSelectModal');
    var loading = document.getElementById('loadingTemasModal');
    
    if (!materiaId) {
        select.innerHTML = '<option value="">Primero selecciona una materia...</option>';
        return;
    }
    
    loading.style.display = 'block';
    select.disabled = true;
    
    fetch('ajax_get_temas.php?materia_id=' + materiaId)
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">Seleccionar tema...</option>';
            
            if (data.success && data.temas.length > 0) {
                data.temas.forEach(function(tema) {
                    var option = document.createElement('option');
                    option.value = tema.id;
                    option.textContent = tema.nombre;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No hay temas en esta materia</option>';
            }
            
            loading.style.display = 'none';
            select.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            select.innerHTML = '<option value="">Error al cargar temas</option>';
            loading.style.display = 'none';
            select.disabled = false;
        });
}

function cambiarTipoPregunta(select) {
    var container = select.closest('.pregunta-item');
    var opcionesContainer = container.querySelector('.opciones-container');
    var opcionesTextarea = container.querySelector('.opciones-textarea');
    var ayuda = container.querySelector('.help-text');
    
    var tipo = select.value;
    
    if (tipo === 'verdadero_falso') {
        opcionesTextarea.value = 'Verdadero\nFalso';
        opcionesTextarea.readOnly = true;
        if (ayuda) ayuda.textContent = 'Opciones predefinidas para Verdadero/Falso';
    } else if (tipo === 'texto_corto' || tipo === 'texto_largo') {
        opcionesTextarea.value = '';
        opcionesTextarea.readOnly = true;
        if (ayuda) ayuda.textContent = 'No se requieren opciones para preguntas de texto';
    } else {
        opcionesTextarea.readOnly = false;
        if (ayuda) ayuda.textContent = 'Una opción por línea. Para Verdadero/Falso usa: Verdadero<enter>Falso';
    }
}

function agregarPregunta() {
    contadorPreguntas++;
    var container = document.getElementById('preguntasContainer');
    var div = document.createElement('div');
    div.className = 'pregunta-item';
    div.setAttribute('data-index', contadorPreguntas - 1);
    div.innerHTML = `
        <div class="pregunta-header">
            <span class="pregunta-numero">Pregunta #${contadorPreguntas}</span>
            <button type="button" class="btn-remove-pregunta" onclick="eliminarPregunta(this)">
                <i class="fas fa-trash"></i> Eliminar
            </button>
        </div>
        <div class="form-group">
            <label>Tipo de Pregunta</label>
            <select name="pregunta_tipo[]" class="tipo-pregunta" onchange="cambiarTipoPregunta(this)">
                <option value="opcion_unica">Opción Única</option>
                <option value="opcion_multiple">Opción Múltiple</option>
                <option value="verdadero_falso">Verdadero / Falso</option>
                <option value="texto_corto">Texto Corto</option>
                <option value="texto_largo">Texto Largo</option>
            </select>
        </div>
        <div class="form-group">
            <label>Pregunta</label>
            <textarea name="pregunta_texto[]" required></textarea>
        </div>
        <div class="form-group opciones-container">
            <label>Opciones (una por línea)</label>
            <textarea name="pregunta_opciones[]" class="opciones-textarea"></textarea>
            <div class="help-text">Para Verdadero/Falso usa: Verdadero<enter>Falso</div>
        </div>
        <div class="form-group">
            <label>Respuesta Correcta (índices separados por coma, ej: 0 o 0,2)</label>
            <input type="text" name="pregunta_respuesta[]" placeholder="0,2">
        </div>
        <div class="form-group">
            <label>Puntaje</label>
            <input type="number" name="pregunta_puntaje[]" value="1" min="0.5" step="0.5">
        </div>
    `;
    container.appendChild(div);
    actualizarNumerosPreguntas();
}

function eliminarPregunta(btn) {
    var item = btn.closest('.pregunta-item');
    var container = document.getElementById('preguntasContainer');
    
    if (container.children.length > 1) {
        // Si tiene ID, eliminar de la base de datos
        var inputId = item.querySelector('input[name="pregunta_id"]');
        if (inputId && inputId.value) {
            if (confirm('¿Eliminar esta pregunta permanentemente?')) {
                fetch('crear-evaluacion.php?delete_pregunta=' + inputId.value)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            item.remove();
                            actualizarNumerosPreguntas();
                        } else {
                            alert('Error al eliminar: ' + data.message);
                        }
                    });
            }
        } else {
            item.remove();
            actualizarNumerosPreguntas();
        }
    } else {
        alert('Debe haber al menos una pregunta en la evaluación');
    }
}

function actualizarNumerosPreguntas() {
    var items = document.querySelectorAll('.pregunta-item');
    items.forEach(function(item, index) {
        var numero = item.querySelector('.pregunta-numero');
        if (numero) {
            numero.textContent = 'Pregunta #' + (index + 1);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($evaluacion_editar): ?>
        abrirModal();
        var materiaSelect = document.getElementById('materiaSelectModal');
        if (materiaSelect.value) {
            cargarTemasModal(materiaSelect.value);
        }
        // Aplicar tipos a las preguntas existentes
        document.querySelectorAll('.tipo-pregunta').forEach(function(select) {
            cambiarTipoPregunta(select);
        });
    <?php endif; ?>
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>