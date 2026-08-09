<?php
$page_title = 'Corregir Evaluaciones';
$page_icon = 'check-double';

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
// PROCESAR CORRECCIÓN POR PREGUNTA (AJAX)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'corregir_pregunta') {
    $id_respuesta = $_POST['id_respuesta'];
    $puntaje_obtenido = $_POST['puntaje_obtenido'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE RespuestasEvaluacion SET puntaje_obtenido = ? WHERE id = ?");
        $stmt->execute([$puntaje_obtenido, $id_respuesta]);
        
        $stmt = $pdo->prepare("
            SELECT r.id, SUM(COALESCE(re.puntaje_obtenido, 0)) as total
            FROM ResultadosEvaluacion r
            JOIN RespuestasEvaluacion re ON r.id = re.id_resultado
            WHERE r.id = (
                SELECT id_resultado FROM RespuestasEvaluacion WHERE id = ?
            )
            GROUP BY r.id
        ");
        $stmt->execute([$id_respuesta]);
        $resultado = $stmt->fetch();
        
        if ($resultado) {
            $stmt = $pdo->prepare("UPDATE ResultadosEvaluacion SET puntaje_obtenido = ?, estado = 'corregido', fecha_correccion = NOW() WHERE id = ?");
            $stmt->execute([$resultado['total'], $resultado['id']]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'total' => $resultado['total'] ?? 0]);
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// =============================================
// PROCESAR CORRECCIÓN MÚLTIPLE (TODAS LAS PREGUNTAS)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'corregir_todo') {
    $respuestas = $_POST['respuestas'] ?? [];
    $resultado_id = $_POST['resultado_id'] ?? 0;
    
    if (empty($respuestas) || !$resultado_id) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        $total_general = 0;
        
        foreach ($respuestas as $id_respuesta => $puntaje) {
            $puntaje = floatval($puntaje);
            $stmt = $pdo->prepare("UPDATE RespuestasEvaluacion SET puntaje_obtenido = ? WHERE id = ?");
            $stmt->execute([$puntaje, $id_respuesta]);
            $total_general += $puntaje;
        }
        
        // Actualizar resultado general
        $stmt = $pdo->prepare("UPDATE ResultadosEvaluacion SET puntaje_obtenido = ?, estado = 'corregido', fecha_correccion = NOW() WHERE id = ?");
        $stmt->execute([$total_general, $resultado_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Todas las preguntas fueron corregidas correctamente.';
        echo json_encode(['success' => true, 'total' => $total_general]);
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// =============================================
// OBTENER DATOS
// =============================================

$evaluacion_id = $_GET['evaluacion_id'] ?? 0;

// Obtener todas las evaluaciones del profesor con sus estudiantes
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            e.id as evaluacion_id,
            e.titulo as evaluacion_titulo,
            e.puntaje_maximo,
            t.nombre as tema_nombre,
            m.nombre as materia_nombre,
            m.id as materia_id
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

// Obtener estudiantes inscritos en cada materia y sus resultados
$resultados_por_evaluacion = [];
if (!empty($evaluaciones)) {
    foreach ($evaluaciones as $eval) {
        try {
            // Obtener estudiantes inscritos en la materia
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.nombre, u.apellido, u.correo
                FROM Inscripciones i
                JOIN Usuarios u ON i.id_usuario = u.id
                WHERE i.id_materia = (
                    SELECT id_materia FROM Temas WHERE id = (
                        SELECT id_tema FROM Evaluaciones WHERE id = ?
                    )
                )
                ORDER BY u.apellido, u.nombre
            ");
            $stmt->execute([$eval['evaluacion_id']]);
            $estudiantes_materia = $stmt->fetchAll();
            
            // Obtener resultados de la evaluación
            $stmt = $pdo->prepare("
                SELECT 
                    r.id as resultado_id,
                    r.id_usuario,
                    r.puntaje_obtenido as resultado_total,
                    r.estado,
                    r.fecha,
                    p.id as pregunta_id,
                    p.pregunta as pregunta_texto,
                    p.tipo as pregunta_tipo,
                    p.puntaje as pregunta_puntaje_maximo,
                    p.opciones,
                    p.respuesta_correcta,
                    re.id as respuesta_id,
                    re.respuesta,
                    re.puntaje_obtenido as respuesta_puntaje
                FROM ResultadosEvaluacion r
                JOIN RespuestasEvaluacion re ON r.id = re.id_resultado
                JOIN PreguntasEvaluacion p ON re.id_pregunta = p.id
                WHERE r.id_evaluacion = ?
                ORDER BY r.id_usuario, p.orden
            ");
            $stmt->execute([$eval['evaluacion_id']]);
            $resultados_raw = $stmt->fetchAll();
            
            // Agrupar resultados por estudiante
            $resultados_por_usuario = [];
            foreach ($resultados_raw as $row) {
                $usuario_id_row = $row['id_usuario'];
                if (!isset($resultados_por_usuario[$usuario_id_row])) {
                    $resultados_por_usuario[$usuario_id_row] = [
                        'resultado_id' => $row['resultado_id'],
                        'total' => $row['resultado_total'],
                        'estado' => $row['estado'],
                        'fecha' => $row['fecha'],
                        'respuestas' => []
                    ];
                }
                
                $opciones = null;
                if (!empty($row['opciones']) && $row['opciones'] !== null) {
                    $opciones = json_decode($row['opciones'], true);
                }
                
                $resultados_por_usuario[$usuario_id_row]['respuestas'][] = [
                    'pregunta_id' => $row['pregunta_id'],
                    'pregunta' => $row['pregunta_texto'],
                    'tipo' => $row['pregunta_tipo'],
                    'puntaje_maximo' => $row['pregunta_puntaje_maximo'],
                    'opciones' => $opciones,
                    'respuesta_id' => $row['respuesta_id'],
                    'respuesta' => $row['respuesta'],
                    'puntaje_obtenido' => $row['respuesta_puntaje']
                ];
            }
            
            // Combinar estudiantes con resultados
            $estudiantes_completos = [];
            foreach ($estudiantes_materia as $est) {
                $est_id = $est['id'];
                if (isset($resultados_por_usuario[$est_id])) {
                    $estudiantes_completos[] = [
                        'id' => $est['id'],
                        'nombre' => $est['nombre'] . ' ' . $est['apellido'],
                        'correo' => $est['correo'],
                        'resultado' => $resultados_por_usuario[$est_id],
                        'realizo' => true
                    ];
                } else {
                    $estudiantes_completos[] = [
                        'id' => $est['id'],
                        'nombre' => $est['nombre'] . ' ' . $est['apellido'],
                        'correo' => $est['correo'],
                        'resultado' => null,
                        'realizo' => false
                    ];
                }
            }
            
            $resultados_por_evaluacion[$eval['evaluacion_id']] = [
                'info' => $eval,
                'estudiantes' => $estudiantes_completos
            ];
            
        } catch (PDOException $e) {
            $resultados_por_evaluacion[$eval['evaluacion_id']] = [
                'info' => $eval,
                'estudiantes' => []
            ];
        }
    }
}

// Agrupar por materia
$evaluaciones_por_materia = [];
foreach ($resultados_por_evaluacion as $eval_id => $data) {
    $info = $data['info'];
    $materia_id = $info['materia_id'];
    
    if (!isset($evaluaciones_por_materia[$materia_id])) {
        $evaluaciones_por_materia[$materia_id] = [
            'nombre' => $info['materia_nombre'],
            'evaluaciones' => []
        ];
    }
    $evaluaciones_por_materia[$materia_id]['evaluaciones'][] = $data;
}

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
        border-top: 4px solid #2ecc71;
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
        background: #2ecc71;
        color: white;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 12px;
    }
    .materia-body {
        padding: 15px 20px;
    }
    
    .evaluacion-item {
        margin-bottom: 20px;
        border-left: 3px solid #2ecc71;
        padding-left: 12px;
    }
    .evaluacion-item:last-child {
        margin-bottom: 0;
    }
    .evaluacion-item .eval-header {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .evaluacion-item .eval-header .badge-eval {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 2px 12px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: normal;
    }
    
    .estudiante-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 8px;
        border: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        transition: all 0.2s;
    }
    .estudiante-card:hover {
        background: #f0f0f0;
    }
    .estudiante-card .info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .estudiante-card .info .nombre {
        font-weight: 500;
        color: #2c3e50;
        font-size: 14px;
    }
    .estudiante-card .info .correo {
        font-size: 12px;
        color: #999;
    }
    .estudiante-card .estado {
        font-size: 13px;
        font-weight: 500;
        padding: 3px 12px;
        border-radius: 12px;
    }
    .estudiante-card .estado.no-realizado {
        background: #e9ecef;
        color: #6c757d;
    }
    .estudiante-card .estado.pendiente {
        background: #fff3cd;
        color: #856404;
    }
    .estudiante-card .estado.corregido {
        background: #d4edda;
        color: #155724;
    }
    
    .btn-corregir-estudiante {
        background: #3498db;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 5px 15px;
        cursor: pointer;
        font-size: 12px;
        transition: background 0.3s;
    }
    .btn-corregir-estudiante:hover {
        background: #2980b9;
    }
    .btn-corregir-estudiante:disabled {
        background: #95a5a6;
        cursor: not-allowed;
    }
    
    .sin-estudiantes {
        color: #999;
        font-size: 13px;
        font-style: italic;
        padding: 8px 0;
    }
    
    /* MODAL DE CORRECCIÓN */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
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
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }
    .modal-content .modal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
    }
    .modal-content .modal-header .btn-close-modal {
        background: none;
        border: none;
        font-size: 30px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s;
        line-height: 1;
    }
    .modal-content .modal-header .btn-close-modal:hover {
        color: #333;
    }
    .modal-content .estudiante-info-modal {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #3498db;
    }
    .modal-content .estudiante-info-modal .nombre {
        font-weight: 600;
        font-size: 16px;
        color: #2c3e50;
    }
    .modal-content .estudiante-info-modal .correo {
        color: #999;
        font-size: 13px;
    }
    .modal-content .pregunta-corregir {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        border-left: 3px solid #3498db;
    }
    .modal-content .pregunta-corregir .pregunta-texto {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 8px;
    }
    .modal-content .pregunta-corregir .pregunta-texto .tipo-badge {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 500;
        margin-right: 5px;
    }
    .modal-content .pregunta-corregir .pregunta-texto .tipo-badge.opcion_unica { background: #e3f2fd; color: #1976d2; }
    .modal-content .pregunta-corregir .pregunta-texto .tipo-badge.opcion_multiple { background: #f3e5f5; color: #7b1fa2; }
    .modal-content .pregunta-corregir .pregunta-texto .tipo-badge.verdadero_falso { background: #fff3e0; color: #e65100; }
    .modal-content .pregunta-corregir .pregunta-texto .tipo-badge.texto_corto { background: #e8f5e9; color: #2e7d32; }
    .modal-content .pregunta-corregir .pregunta-texto .tipo-badge.texto_largo { background: #fce4ec; color: #c62828; }
    
    .modal-content .pregunta-corregir .respuesta-estudiante {
        background: white;
        padding: 8px 12px;
        border-radius: 5px;
        border: 1px solid #e0e0e0;
        margin: 5px 0 8px 0;
        font-size: 14px;
    }
    .modal-content .pregunta-corregir .respuesta-estudiante.sin-respuesta {
        color: #999;
        font-style: italic;
    }
    .modal-content .pregunta-corregir .correccion-row {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .modal-content .pregunta-corregir .correccion-row label {
        font-size: 13px;
        color: #555;
        font-weight: 500;
    }
    .modal-content .pregunta-corregir .correccion-row input {
        padding: 5px 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        width: 80px;
        font-size: 13px;
    }
    .modal-content .pregunta-corregir .correccion-row input:focus {
        outline: none;
        border-color: #2ecc71;
    }
    .modal-content .pregunta-corregir .correccion-row .puntaje-actual {
        font-size: 13px;
        color: #999;
    }
    .modal-content .pregunta-corregir .correccion-row .puntaje-actual.corregido {
        color: #2ecc71;
        font-weight: 600;
    }
    .modal-content .pregunta-corregir .correccion-row .puntaje-actual.pendiente {
        color: #f39c12;
    }
    
    .modal-content .total-container {
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: center;
        font-size: 16px;
        font-weight: 600;
        border: 2px solid #e0e0e0;
    }
    .modal-content .total-container .total-puntaje {
        color: #2ecc71;
    }
    .modal-content .total-container .total-pendiente {
        color: #f39c12;
    }
    
    .modal-content .acciones-modal {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
        position: sticky;
        bottom: 0;
        background: white;
        padding-bottom: 5px;
    }
    .modal-content .acciones-modal .btn-cerrar-modal {
        background: #95a5a6;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 25px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.3s;
        flex: 1;
    }
    .modal-content .acciones-modal .btn-cerrar-modal:hover {
        background: #7f8c8d;
    }
    .modal-content .acciones-modal .btn-calificar-todo {
        background: #2ecc71;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 25px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s;
        flex: 2;
        font-size: 16px;
    }
    .modal-content .acciones-modal .btn-calificar-todo:hover {
        background: #27ae60;
    }
    .modal-content .acciones-modal .btn-calificar-todo:disabled {
        background: #95a5a6;
        cursor: not-allowed;
    }
    
    @media (max-width: 768px) {
        .materias-grid {
            grid-template-columns: 1fr;
        }
        .estudiante-card {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }
        .estudiante-card .info {
            flex-direction: column;
            align-items: flex-start;
        }
        .modal-content .pregunta-corregir .correccion-row {
            flex-direction: column;
            align-items: stretch;
        }
        .modal-content .acciones-modal {
            flex-direction: column;
        }
    }
</style>

<div class="gestion-container">
    <h3><i class="fas fa-check-double"></i> Corregir Evaluaciones</h3>

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

    <?php if (empty($evaluaciones_por_materia)): ?>
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <i class="fas fa-check-double" style="font-size: 64px; color: #ccc; display: block; margin-bottom: 20px;"></i>
            <h4 style="color: #666; margin-bottom: 10px;">No hay evaluaciones para corregir</h4>
            <p style="color: #999; font-size: 14px;">Crea evaluaciones en la sección <strong>"Gestión de Evaluaciones"</strong></p>
            <a href="crear-evaluacion.php" class="btn" style="background: #9b59b6; color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: inline-block; margin-top: 10px;">
                <i class="fas fa-plus-circle"></i> Crear Evaluación
            </a>
        </div>
    <?php else: ?>
        <div class="materias-grid">
            <?php foreach ($evaluaciones_por_materia as $materia_id => $materia): ?>
                <div class="materia-card">
                    <div class="materia-header">
                        <h4><i class="fas fa-book" style="color: #2ecc71; margin-right: 8px;"></i><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                        <span class="badge-materia">
                            <?php echo count($materia['evaluaciones']); ?> evaluaciones
                        </span>
                    </div>
                    <div class="materia-body">
                        <?php foreach ($materia['evaluaciones'] as $data): ?>
                            <?php 
                            $info = $data['info'];
                            $estudiantes = $data['estudiantes'];
                            $total_estudiantes = count($estudiantes);
                            $realizados = 0;
                            $corregidos = 0;
                            $pendientes = 0;
                            
                            foreach ($estudiantes as $est) {
                                if ($est['realizo']) {
                                    $realizados++;
                                    if ($est['resultado']['estado'] == 'corregido') {
                                        $corregidos++;
                                    } else {
                                        $pendientes++;
                                    }
                                }
                            }
                            ?>
                            <div class="evaluacion-item">
                                <div class="eval-header">
                                    <span><?php echo htmlspecialchars($info['evaluacion_titulo']); ?></span>
                                    <span class="badge-eval">
                                        <i class="fas fa-users"></i> <?php echo $total_estudiantes; ?> estudiantes
                                        <?php if ($realizados > 0): ?>
                                            | ✅ <?php echo $corregidos; ?> corregidos | ⏳ <?php echo $pendientes; ?> pendientes
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if (empty($estudiantes)): ?>
                                    <div class="sin-estudiantes">No hay estudiantes inscritos en esta materia</div>
                                <?php else: ?>
                                    <?php foreach ($estudiantes as $est): ?>
                                        <?php 
                                        $estado_texto = 'No ha realizado';
                                        $estado_clase = 'no-realizado';
                                        $btn_disabled = true;
                                        
                                        if ($est['realizo']) {
                                            if ($est['resultado']['estado'] == 'corregido') {
                                                $estado_texto = '✅ Corregido';
                                                $estado_clase = 'corregido';
                                                $btn_disabled = false;
                                            } else {
                                                $estado_texto = '⏳ Pendiente';
                                                $estado_clase = 'pendiente';
                                                $btn_disabled = false;
                                            }
                                        }
                                        ?>
                                        <div class="estudiante-card">
                                            <div class="info">
                                                <span class="nombre"><?php echo htmlspecialchars($est['nombre']); ?></span>
                                                <span class="correo"><?php echo htmlspecialchars($est['correo']); ?></span>
                                                <span class="estado <?php echo $estado_clase; ?>">
                                                    <?php echo $estado_texto; ?>
                                                </span>
                                                <?php if ($est['realizo'] && $est['resultado']['estado'] == 'corregido'): ?>
                                                    <span style="font-size: 13px; font-weight: 600; color: #2ecc71;">
                                                        <?php echo number_format($est['resultado']['total'], 2); ?>/<?php echo $info['puntaje_maximo']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <button class="btn-corregir-estudiante" 
                                                    onclick="abrirModalCorreccion(<?php echo htmlspecialchars(json_encode([$est, $info])); ?>)"
                                                    <?php echo $btn_disabled ? 'disabled' : ''; ?>>
                                                <i class="fas fa-edit"></i> 
                                                <?php echo $est['realizo'] ? 'Corregir' : 'Ver'; ?>
                                            </button>
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

<!-- MODAL DE CORRECCIÓN -->
<div class="modal-overlay" id="modalCorreccion">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="modalTitulo">Corregir Evaluación</span></h3>
            <button class="btn-close-modal" onclick="cerrarModalCorreccion()">&times;</button>
        </div>
        
        <div class="estudiante-info-modal">
            <div class="nombre" id="modalEstudianteNombre">-</div>
            <div class="correo" id="modalEstudianteCorreo">-</div>
        </div>
        
        <div id="modalPreguntasContainer">
            <!-- Se llena con JavaScript -->
        </div>
        
        <div class="total-container" id="totalContainer">
            Total: <span id="totalPuntaje" class="total-pendiente">⏳ Pendiente</span>
        </div>
        
        <div class="acciones-modal">
            <button class="btn-cerrar-modal" onclick="cerrarModalCorreccion()">
                <i class="fas fa-times"></i> Cerrar
            </button>
            <button class="btn-calificar-todo" id="btnCalificarTodo" onclick="calificarTodo()">
                <i class="fas fa-save"></i> Calificar Todo
            </button>
        </div>
    </div>
</div>

<script>
var datosActuales = null;
var resultadoIdActual = 0;

function abrirModalCorreccion(data) {
    var est = data[0];
    var info = data[1];
    var resultado = est.resultado;
    
    datosActuales = data;
    resultadoIdActual = resultado ? resultado.resultado_id : 0;
    
    document.getElementById('modalTitulo').textContent = 'Corregir: ' + info.evaluacion_titulo;
    document.getElementById('modalEstudianteNombre').textContent = est.nombre;
    document.getElementById('modalEstudianteCorreo').textContent = est.correo;
    
    var container = document.getElementById('modalPreguntasContainer');
    container.innerHTML = '';
    
    if (!est.realizo || !resultado) {
        container.innerHTML = `
            <div style="text-align: center; padding: 30px; color: #999;">
                <i class="fas fa-info-circle" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                <p>Este estudiante aún no ha realizado esta evaluación.</p>
            </div>
        `;
        document.getElementById('totalContainer').style.display = 'none';
        document.getElementById('btnCalificarTodo').disabled = true;
    } else {
        var respuestas = resultado.respuestas || [];
        var total_maximo = 0;
        var total_actual = 0;
        
        respuestas.forEach(function(resp, index) {
            total_maximo += parseFloat(resp.puntaje_maximo);
            if (resp.puntaje_obtenido !== null) {
                total_actual += parseFloat(resp.puntaje_obtenido);
            }
            
            // Obtener texto de la respuesta
            var respuesta_texto = '';
            var tiene_respuesta = false;
            
            if (resp.respuesta && resp.respuesta !== null && resp.respuesta !== 'null') {
                var tipo = resp.tipo;
                var opciones = resp.opciones;
                var raw = resp.respuesta;
                
                if (tipo == 'opcion_unica' || tipo == 'opcion_multiple' || tipo == 'verdadero_falso') {
                    try {
                        var indices = JSON.parse(raw);
                        if (Array.isArray(indices) && opciones && Array.isArray(opciones)) {
                            var textos = [];
                            indices.forEach(function(idx) {
                                if (opciones[idx]) {
                                    textos.push(opciones[idx]);
                                }
                            });
                            respuesta_texto = textos.join(', ');
                            tiene_respuesta = textos.length > 0;
                        }
                    } catch(e) {
                        respuesta_texto = raw;
                        tiene_respuesta = raw.trim() !== '';
                    }
                } else {
                    respuesta_texto = raw;
                    tiene_respuesta = raw.trim() !== '';
                }
            }
            
            var tipos = {
                'opcion_unica': 'Única',
                'opcion_multiple': 'Múltiple',
                'verdadero_falso': 'V/F',
                'texto_corto': 'Corto',
                'texto_largo': 'Largo'
            };
            
            var tipo_clase = resp.tipo || 'opcion_unica';
            var puntaje_asignado = resp.puntaje_obtenido !== null ? resp.puntaje_obtenido : '';
            var estado_clase = resp.puntaje_obtenido !== null ? 'corregido' : 'pendiente';
            var estado_texto = resp.puntaje_obtenido !== null ? resp.puntaje_obtenido + '/' + resp.puntaje_maximo : '⏳ Pendiente';
            
            var div = document.createElement('div');
            div.className = 'pregunta-corregir';
            div.innerHTML = `
                <div class="pregunta-texto">
                    <span class="tipo-badge ${tipo_clase}">${tipos[tipo_clase] || tipo_clase}</span>
                    <span>Pregunta ${index + 1}:</span> ${resp.pregunta}
                    <span style="color: #999; font-size: 12px;">(${resp.puntaje_maximo} pts)</span>
                </div>
                <div class="respuesta-estudiante ${!tiene_respuesta ? 'sin-respuesta' : ''}">
                    ${tiene_respuesta ? respuesta_texto : 'Sin respuesta'}
                </div>
                <div class="correccion-row">
                    <label>Puntaje:</label>
                    <input type="number" 
                           class="puntaje-input"
                           data-respuesta-id="${resp.respuesta_id}"
                           data-maximo="${resp.puntaje_maximo}"
                           value="${puntaje_asignado}" 
                           min="0" max="${resp.puntaje_maximo}" 
                           step="0.5"
                           placeholder="pts"
                           onchange="actualizarTotal()">
                    <span class="puntaje-actual ${estado_clase}" id="estado_${resp.respuesta_id}">
                        ${estado_texto}
                    </span>
                </div>
            `;
            container.appendChild(div);
        });
        
        // Mostrar total
        document.getElementById('totalContainer').style.display = 'block';
        actualizarTotal();
        document.getElementById('btnCalificarTodo').disabled = false;
    }
    
    document.getElementById('modalCorreccion').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function actualizarTotal() {
    var inputs = document.querySelectorAll('.puntaje-input');
    var total = 0;
    var todos_tienen = true;
    
    inputs.forEach(function(input) {
        var val = parseFloat(input.value);
        if (!isNaN(val) && val >= 0) {
            total += val;
        } else {
            todos_tienen = false;
        }
    });
    
    var totalSpan = document.getElementById('totalPuntaje');
    if (todos_tienen && inputs.length > 0) {
        var maximo_total = 0;
        inputs.forEach(function(input) {
            maximo_total += parseFloat(input.dataset.maximo);
        });
        totalSpan.textContent = total.toFixed(2) + ' / ' + maximo_total.toFixed(2);
        totalSpan.className = 'total-puntaje';
    } else {
        totalSpan.textContent = '⏳ Pendiente (faltan puntajes)';
        totalSpan.className = 'total-pendiente';
    }
}

function calificarTodo() {
    var inputs = document.querySelectorAll('.puntaje-input');
    var respuestas = {};
    var todos_validos = true;
    var mensaje_error = '';
    
    inputs.forEach(function(input) {
        var id = input.dataset.respuestaId;
        var val = parseFloat(input.value);
        var maximo = parseFloat(input.dataset.maximo);
        
        if (isNaN(val) || val < 0 || val > maximo) {
            todos_validos = false;
            mensaje_error = 'Todos los puntajes deben ser válidos (0 a ' + maximo + ')';
        }
        respuestas[id] = val;
    });
    
    if (!todos_validos) {
        alert(mensaje_error);
        return;
    }
    
    if (!confirm('¿Estás seguro de guardar todas las calificaciones?')) {
        return;
    }
    
    // Preparar datos para enviar
    var formData = new FormData();
    formData.append('action', 'corregir_todo');
    formData.append('resultado_id', resultadoIdActual);
    
    for (var id in respuestas) {
        formData.append('respuestas[' + id + ']', respuestas[id]);
    }
    
    // Enviar via AJAX
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Todas las calificaciones fueron guardadas correctamente.');
            window.location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error al guardar: ' + error);
    });
}

function cerrarModalCorreccion() {
    document.getElementById('modalCorreccion').classList.remove('show');
    document.body.style.overflow = '';
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalCorreccion').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalCorreccion();
    }
});

// Cerrar con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalCorreccion();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>