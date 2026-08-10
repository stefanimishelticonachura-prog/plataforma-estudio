<?php
// =============================================
// 1. PRIMERO: Configuración básica y sesión
// =============================================
$page_title = 'Evaluaciones';
$page_icon = 'tasks';

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
$materia_id = $_GET['materia_id'] ?? 0;
$evaluacion_id = $_GET['id'] ?? 0;
$ver_resultados = $_GET['ver_resultados'] ?? 0;

// =============================================
// 2. PROCESAR ENVÍO DE EVALUACIÓN (ANTES DEL HEADER)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enviar_evaluacion') {
    $evaluacion_id_post = $_POST['evaluacion_id'];
    $respuestas = $_POST['respuestas'] ?? [];
    $tiempo_restante = $_POST['tiempo_restante'] ?? 0;
    
    try {
        // Verificar que el estudiante tiene acceso
        $stmt = $pdo->prepare("
            SELECT e.*, t.id_materia 
            FROM Evaluaciones e
            JOIN Temas t ON e.id_tema = t.id
            JOIN Inscripciones i ON i.id_materia = t.id_materia
            WHERE e.id = ? AND i.id_usuario = ?
        ");
        $stmt->execute([$evaluacion_id_post, $usuario_id]);
        $evaluacion = $stmt->fetch();
        
        if (!$evaluacion) {
            $_SESSION['error'] = 'No tienes acceso a esta evaluación';
            header('Location: evaluaciones.php');
            exit();
        }
        
        // Verificar intentos
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ResultadosEvaluacion WHERE id_evaluacion = ? AND id_usuario = ?");
        $stmt->execute([$evaluacion_id_post, $usuario_id]);
        $intentos = $stmt->fetch()['total'];
        
        if ($intentos >= $evaluacion['intentos_permitidos']) {
            $_SESSION['error'] = 'Has alcanzado el límite de intentos permitidos';
            header('Location: evaluaciones.php');
            exit();
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Crear resultado
        $stmt = $pdo->prepare("
            INSERT INTO ResultadosEvaluacion (id_usuario, id_evaluacion, intento, puntaje_obtenido, estado) 
            VALUES (?, ?, ?, NULL, 'pendiente')
        ");
        $nuevo_intento = $intentos + 1;
        $stmt->execute([$usuario_id, $evaluacion_id_post, $nuevo_intento]);
        $resultado_id = $pdo->lastInsertId();
        
        // Guardar respuestas
        $stmt = $pdo->prepare("
            INSERT INTO RespuestasEvaluacion (id_resultado, id_pregunta, respuesta) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($respuestas as $pregunta_id => $respuesta) {
            if (is_array($respuesta)) {
                $respuesta = json_encode(array_values($respuesta));
            } else {
                if ($respuesta !== '' && $respuesta !== null) {
                    $respuesta = json_encode([$respuesta]);
                } else {
                    $respuesta = null;
                }
            }
            
            $stmt->execute([$resultado_id, $pregunta_id, $respuesta]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Evaluación enviada correctamente. Espera la corrección del profesor.';
        header('Location: evaluaciones.php');
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error al enviar evaluación: ' . $e->getMessage();
        header('Location: evaluaciones.php');
        exit();
    }
}

// =============================================
// 3. OBTENER DATOS PARA VER RESULTADOS
// =============================================
$resultados_detalle = null;
$preguntas_detalle = [];
$puntaje_maximo_evaluacion = 0;

if ($ver_resultados > 0) {
    try {
        // Obtener el resultado más reciente de esta evaluación con el puntaje máximo de la evaluación
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                e.puntaje_maximo as puntaje_maximo_evaluacion
            FROM ResultadosEvaluacion r
            JOIN Evaluaciones e ON r.id_evaluacion = e.id
            WHERE r.id_evaluacion = ? AND r.id_usuario = ? AND r.estado = 'corregido'
            ORDER BY r.fecha DESC
            LIMIT 1
        ");
        $stmt->execute([$ver_resultados, $usuario_id]);
        $resultados_detalle = $stmt->fetch();
        
        if ($resultados_detalle) {
            $puntaje_maximo_evaluacion = $resultados_detalle['puntaje_maximo_evaluacion'];
            
            // Obtener respuestas con detalles de las preguntas
            $stmt = $pdo->prepare("
                SELECT 
                    re.*,
                    p.pregunta as pregunta_texto,
                    p.tipo as pregunta_tipo,
                    p.puntaje as pregunta_puntaje_maximo,
                    p.opciones,
                    p.respuesta_correcta
                FROM RespuestasEvaluacion re
                JOIN PreguntasEvaluacion p ON re.id_pregunta = p.id
                WHERE re.id_resultado = ?
                ORDER BY p.orden
            ");
            $stmt->execute([$resultados_detalle['id']]);
            $preguntas_detalle = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $resultados_detalle = null;
        $preguntas_detalle = [];
    }
}

// =============================================
// 4. OBTENER DATOS PARA LA EVALUACIÓN EN CURSO
// =============================================
$evaluacion_actual = null;
$preguntas = [];
$tiempo_limite = 0;

if ($evaluacion_id > 0) {
    try {
        // Verificar acceso
        $stmt = $pdo->prepare("
            SELECT e.*, t.nombre as tema_nombre, m.nombre as materia_nombre
            FROM Evaluaciones e
            JOIN Temas t ON e.id_tema = t.id
            JOIN Materias m ON t.id_materia = m.id
            JOIN Inscripciones i ON i.id_materia = m.id
            WHERE e.id = ? AND i.id_usuario = ? AND e.estado = 'activo'
        ");
        $stmt->execute([$evaluacion_id, $usuario_id]);
        $evaluacion_actual = $stmt->fetch();
        
        if ($evaluacion_actual) {
            // Verificar intentos
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ResultadosEvaluacion WHERE id_evaluacion = ? AND id_usuario = ?");
            $stmt->execute([$evaluacion_id, $usuario_id]);
            $intentos = $stmt->fetch()['total'];
            
            if ($intentos >= $evaluacion_actual['intentos_permitidos']) {
                $_SESSION['error'] = 'Has alcanzado el límite de intentos permitidos';
                header('Location: evaluaciones.php');
                exit();
            }
            
            // Obtener preguntas
            $stmt = $pdo->prepare("
                SELECT * FROM PreguntasEvaluacion 
                WHERE id_evaluacion = ? 
                ORDER BY orden
            ");
            $stmt->execute([$evaluacion_id]);
            $preguntas = $stmt->fetchAll();
            
            if (empty($preguntas)) {
                $_SESSION['error'] = 'Esta evaluación no tiene preguntas';
                header('Location: evaluaciones.php');
                exit();
            }
            
            $tiempo_limite = $evaluacion_actual['tiempo_limite_minutos'] ?? 0;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al cargar evaluación: ' . $e->getMessage();
        header('Location: evaluaciones.php');
        exit();
    }
}

// =============================================
// 5. OBTENER LISTA DE EVALUACIONES
// =============================================
try {
    // Obtener materias del estudiante
    $stmt = $pdo->prepare("
        SELECT m.id, m.nombre 
        FROM Inscripciones i
        JOIN Materias m ON i.id_materia = m.id
        WHERE i.id_usuario = ? AND m.estado = 'activo'
        ORDER BY m.nombre
    ");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
    
    // Obtener evaluaciones disponibles
    $sql = "
        SELECT 
            e.*,
            t.nombre as tema_nombre,
            m.nombre as materia_nombre,
            (SELECT COUNT(*) FROM ResultadosEvaluacion 
             WHERE id_evaluacion = e.id AND id_usuario = ?) as intentos_realizados,
            (SELECT MAX(puntaje_obtenido) FROM ResultadosEvaluacion 
             WHERE id_evaluacion = e.id AND id_usuario = ?) as mejor_puntaje,
            (SELECT MAX(fecha) FROM ResultadosEvaluacion 
             WHERE id_evaluacion = e.id AND id_usuario = ?) as ultima_fecha,
            (SELECT estado FROM ResultadosEvaluacion 
             WHERE id_evaluacion = e.id AND id_usuario = ? 
             ORDER BY intento DESC LIMIT 1) as ultimo_estado,
            (SELECT COUNT(*) FROM PreguntasEvaluacion WHERE id_evaluacion = e.id) as total_preguntas
        FROM Evaluaciones e
        JOIN Temas t ON e.id_tema = t.id
        JOIN Materias m ON t.id_materia = m.id
        JOIN Inscripciones i ON i.id_materia = m.id
        WHERE i.id_usuario = ? AND e.estado = 'activo'
    ";
    $params = [$usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id];
    
    if ($materia_id > 0) {
        $sql .= " AND m.id = ?";
        $params[] = $materia_id;
    }
    
    $sql .= " ORDER BY e.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $evaluaciones = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $materias = [];
    $evaluaciones = [];
    $_SESSION['error'] = 'Error al cargar evaluaciones';
}

// =============================================
// 6. AHORA SÍ: INCLUIR EL HEADER
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
    .evaluaciones-container {
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

    /* ===== FILTRO ===== */
    .filtro-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        transition: all 0.3s;
    }
    
    .filtro-container:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .filtro-container form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    
    .filtro-container form label {
        font-weight: 500;
        color: #555;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filtro-container form label i {
        color: #3498db;
    }
    
    .filtro-container form select {
        padding: 10px 14px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        min-width: 200px;
        flex: 1;
        background: white;
        cursor: pointer;
        transition: border-color 0.3s;
    }
    
    .filtro-container form select:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .filtro-container form .btn-limpiar {
        background: #95a5a6;
        color: white;
        padding: 8px 15px;
        text-decoration: none;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .filtro-container form .btn-limpiar:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }

    /* ===== EVALUACION CARD ===== */
    .evaluacion-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        border-left: 4px solid #3498db;
        transition: all 0.3s;
    }
    
    .evaluacion-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transform: translateX(4px);
    }
    
    .evaluacion-card.completada {
        border-left-color: #2ecc71;
    }
    
    .evaluacion-card.pendiente-correccion {
        border-left-color: #f39c12;
    }
    
    .evaluacion-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .evaluacion-card .header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 17px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .evaluacion-card .header h4 i {
        color: #3498db;
    }
    
    .evaluacion-card .descripcion {
        color: #666;
        margin-bottom: 12px;
        font-size: 14px;
    }
    
    .evaluacion-card .info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
        margin: 10px 0;
    }
    
    .evaluacion-card .info-item {
        font-size: 13px;
        color: #666;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .evaluacion-card .info-item i {
        color: #3498db;
        width: 16px;
    }
    
    .evaluacion-card .info-item strong {
        color: #2c3e50;
    }
    
    .evaluacion-card .puntaje-mostrado {
        margin: 10px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .evaluacion-card .puntaje-mostrado .puntaje {
        font-size: 20px;
        font-weight: bold;
    }
    
    .evaluacion-card .puntaje-mostrado .puntaje.aprobado {
        color: #2ecc71;
    }
    
    .evaluacion-card .puntaje-mostrado .puntaje.reprobado {
        color: #e74c3c;
    }
    
    .evaluacion-card .puntaje-mostrado .puntaje.pendiente {
        color: #f39c12;
    }
    
    .evaluacion-card .actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn-realizar {
        background: #2ecc71;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }
    
    .btn-realizar:hover {
        background: #27ae60;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.4);
    }
    
    .btn-realizar:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .btn-ver-resultados {
        background: #3498db;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }
    
    .btn-ver-resultados:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }
    
    .badge-estado {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .badge-pendiente {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-completada {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-aprobada {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-reprobada {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-correccion {
        background: #fff3cd;
        color: #856404;
    }

    /* ============================================= */
    /* MODAL DE RESULTADOS */
    /* ============================================= */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(5px);
        padding: 20px;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-box {
        background: white;
        border-radius: 16px;
        padding: 30px;
        max-width: 700px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideUp 0.3s ease;
    }
    
    @keyframes modalSlideUp {
        from { transform: translateY(30px) scale(0.95); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    
    .modal-box .modal-header {
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
    
    .modal-box .modal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-box .modal-header h3 i {
        color: #3498db;
    }
    
    .modal-box .modal-header .btn-close-modal {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s;
        line-height: 1;
    }
    
    .modal-box .modal-header .btn-close-modal:hover {
        color: #333;
    }
    
    .modal-box .modal-resumen {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        border-left: 4px solid #3498db;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .modal-box .modal-resumen .total {
        font-size: 20px;
        font-weight: bold;
    }
    
    .modal-box .modal-resumen .total.aprobado {
        color: #2ecc71;
    }
    
    .modal-box .modal-resumen .total.reprobado {
        color: #e74c3c;
    }
    
    .modal-box .modal-resumen .estado-badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 14px;
    }
    
    .modal-box .modal-resumen .estado-badge.aprobado {
        background: #d4edda;
        color: #155724;
    }
    
    .modal-box .modal-resumen .estado-badge.reprobado {
        background: #f8d7da;
        color: #721c24;
    }
    
    .modal-box .pregunta-resultado {
        padding: 12px 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        border-left: 4px solid #3498db;
        background: #f8f9fa;
        transition: all 0.2s;
    }
    
    .modal-box .pregunta-resultado:hover {
        transform: translateX(4px);
    }
    
    .modal-box .pregunta-resultado.correcta {
        border-left-color: #2ecc71;
        background: #f0fff4;
    }
    
    .modal-box .pregunta-resultado.incorrecta {
        border-left-color: #e74c3c;
        background: #fff5f5;
    }
    
    .modal-box .pregunta-resultado .pregunta-texto {
        font-weight: 500;
        color: #2c3e50;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .modal-box .pregunta-resultado .pregunta-texto .tipo-badge {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 500;
        margin-left: 5px;
    }
    
    .modal-box .pregunta-resultado .pregunta-texto .tipo-badge.opcion_unica { background: #e3f2fd; color: #1976d2; }
    .modal-box .pregunta-resultado .pregunta-texto .tipo-badge.opcion_multiple { background: #f3e5f5; color: #7b1fa2; }
    .modal-box .pregunta-resultado .pregunta-texto .tipo-badge.verdadero_falso { background: #fff3e0; color: #e65100; }
    .modal-box .pregunta-resultado .pregunta-texto .tipo-badge.texto_corto { background: #e8f5e9; color: #2e7d32; }
    .modal-box .pregunta-resultado .pregunta-texto .tipo-badge.texto_largo { background: #fce4ec; color: #c62828; }
    
    .modal-box .pregunta-resultado .respuesta-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 5px;
        font-size: 13px;
    }
    
    .modal-box .pregunta-resultado .respuesta-info .respuesta-estudiante {
        color: #555;
        padding: 4px 10px;
        background: white;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
        font-size: 13px;
        max-width: 300px;
        word-wrap: break-word;
    }
    
    .modal-box .pregunta-resultado .respuesta-info .respuesta-estudiante.sin-respuesta {
        color: #999;
        font-style: italic;
    }
    
    .modal-box .pregunta-resultado .respuesta-info .puntaje-obtenido {
        font-weight: 600;
        font-size: 15px;
        padding: 2px 12px;
        border-radius: 12px;
    }
    
    .modal-box .pregunta-resultado .respuesta-info .puntaje-obtenido.correcto {
        color: #2ecc71;
        background: #d4edda;
    }
    
    .modal-box .pregunta-resultado .respuesta-info .puntaje-obtenido.incorrecto {
        color: #e74c3c;
        background: #f8d7da;
    }
    
    .modal-box .pregunta-resultado .respuesta-info .icono-resultado {
        font-size: 20px;
    }
    
    .modal-box .modal-footer {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 2px solid #f0f0f0;
        text-align: center;
    }
    
    .modal-box .modal-footer .btn-cerrar {
        background: #3498db;
        color: white;
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .modal-box .modal-footer .btn-cerrar:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }

    /* ============================================= */
    /* ESTILOS PARA EL EXAMEN EN CURSO */
    /* ============================================= */
    .btn-volver {
        background: #95a5a6;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 15px;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .btn-volver:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .examen-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        max-width: 800px;
        margin: 0 auto;
    }
    
    .examen-container .examen-header {
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .examen-container .examen-header h3 {
        color: #2c3e50;
        margin: 0;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .examen-container .examen-header h3 i {
        color: #3498db;
    }
    
    .examen-container .examen-header .examen-info {
        color: #666;
        font-size: 14px;
        margin-top: 5px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .examen-container .examen-header .examen-info span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .examen-container .examen-header .examen-info i {
        color: #3498db;
    }
    
    .examen-container .examen-header .timer {
        font-size: 24px;
        font-weight: bold;
        color: #e74c3c;
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .examen-container .examen-header .timer.warning {
        animation: blink 1s infinite;
    }
    
    @keyframes blink {
        50% { opacity: 0.5; }
    }
    
    .examen-container .progreso-bar {
        background: #f0f0f0;
        border-radius: 8px;
        height: 10px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .examen-container .progreso-bar .fill {
        height: 100%;
        background: linear-gradient(90deg, #3498db, #2ecc71);
        transition: width 0.5s;
        border-radius: 8px;
    }
    
    .examen-container .progreso-texto {
        font-size: 13px;
        color: #999;
        text-align: right;
    }
    
    .pregunta-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 3px solid #3498db;
        transition: all 0.3s;
    }
    
    .pregunta-item:hover {
        background: #f5f5f5;
    }
    
    .pregunta-item .pregunta-texto {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 10px;
        font-size: 15px;
    }
    
    .pregunta-item .pregunta-texto .pregunta-numero {
        color: #3498db;
        margin-right: 5px;
    }
    
    .pregunta-item .pregunta-texto .puntaje-label {
        font-weight: normal;
        font-size: 12px;
        color: #999;
    }
    
    .pregunta-item .pregunta-texto .tipo-label {
        font-size: 11px;
        color: #999;
        margin-left: 10px;
    }
    
    .pregunta-item .opciones label {
        display: block;
        padding: 8px 12px;
        margin-bottom: 5px;
        background: white;
        border-radius: 8px;
        border: 2px solid #e0e0e0;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
    }
    
    .pregunta-item .opciones label:hover {
        background: #e3f2fd;
        border-color: #3498db;
        transform: translateX(4px);
    }
    
    .pregunta-item .opciones input[type="radio"],
    .pregunta-item .opciones input[type="checkbox"] {
        margin-right: 10px;
        transform: scale(1.1);
        cursor: pointer;
    }
    
    .pregunta-item .opciones label.selected {
        background: #e3f2fd;
        border-color: #3498db;
    }
    
    .pregunta-item .respuesta-texto {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        resize: vertical;
        transition: border-color 0.3s;
    }
    
    .pregunta-item .respuesta-texto:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .btn-enviar-examen {
        background: #2ecc71;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        transition: all 0.3s;
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-enviar-examen:hover:not(:disabled) {
        background: #27ae60;
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(46, 204, 113, 0.4);
    }
    
    .btn-enviar-examen:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ============================================= */
    /* MODAL DE CONFIRMACIÓN PARA ENVÍO */
    /* ============================================= */
    .modal-confirm {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(5px);
        padding: 20px;
    }
    
    .modal-confirm.active {
        display: flex;
    }
    
    .modal-confirm .modal-box {
        max-width: 420px;
        text-align: center;
    }
    
    .modal-confirm .modal-box h3 {
        color: #2c3e50;
        margin-bottom: 10px;
        font-size: 20px;
    }
    
    .modal-confirm .modal-box p {
        color: #666;
        margin-bottom: 15px;
        line-height: 1.6;
    }
    
    .modal-confirm .modal-box .resumen-box {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 3px solid #f39c12;
        text-align: left;
    }
    
    .modal-confirm .modal-box .btn-group {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .modal-confirm .modal-box .btn-group button {
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 15px;
        min-width: 120px;
    }
    
    .modal-confirm .modal-box .btn-group button:hover {
        transform: translateY(-2px);
    }
    
    .modal-confirm .modal-box .btn-cancelar {
        background: #95a5a6;
        color: white;
    }
    .modal-confirm .modal-box .btn-cancelar:hover {
        background: #7f8c8d;
    }
    
    .modal-confirm .modal-box .btn-confirmar {
        background: #2ecc71;
        color: white;
    }
    .modal-confirm .modal-box .btn-confirmar:hover {
        background: #27ae60;
        box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
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

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .evaluaciones-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .evaluacion-card {
            padding: 16px;
        }
        
        .evaluacion-card .info {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .evaluaciones-container {
            padding: 12px;
        }
        
        .page-title {
            font-size: 20px;
        }
        
        .filtro-container {
            padding: 15px;
        }
        
        .filtro-container form {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filtro-container form label {
            margin-bottom: 5px;
        }
        
        .filtro-container form select {
            min-width: 100%;
            width: 100%;
        }
        
        .evaluacion-card {
            padding: 14px;
            border-radius: 10px;
        }
        
        .evaluacion-card .header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .evaluacion-card .header h4 {
            font-size: 15px;
        }
        
        .evaluacion-card .info {
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        
        .evaluacion-card .info-item {
            font-size: 12px;
        }
        
        .evaluacion-card .actions {
            flex-direction: column;
            width: 100%;
        }
        
        .evaluacion-card .actions .btn-realizar,
        .evaluacion-card .actions .btn-ver-resultados {
            width: 100%;
            justify-content: center;
        }
        
        .examen-container {
            padding: 15px;
        }
        
        .examen-container .examen-header h3 {
            font-size: 17px;
        }
        
        .examen-container .examen-header .examen-info {
            font-size: 13px;
            flex-direction: column;
            gap: 4px;
        }
        
        .examen-container .examen-header .timer {
            font-size: 20px;
        }
        
        .pregunta-item {
            padding: 12px;
        }
        
        .pregunta-item .pregunta-texto {
            font-size: 14px;
        }
        
        .pregunta-item .opciones label {
            font-size: 13px;
            padding: 6px 10px;
        }
        
        .btn-enviar-examen {
            font-size: 15px;
            padding: 10px 20px;
        }
        
        .modal-box {
            padding: 20px;
        }
        
        .modal-box .pregunta-resultado .respuesta-info {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .modal-box .pregunta-resultado .respuesta-info .respuesta-estudiante {
            max-width: 100%;
            width: 100%;
        }
        
        .modal-confirm .modal-box .btn-group {
            flex-direction: column;
        }
        
        .modal-confirm .modal-box .btn-group button {
            width: 100%;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .evaluaciones-container {
            padding: 8px;
        }
        
        .page-title {
            font-size: 17px;
        }
        
        .page-title i {
            font-size: 16px;
        }
        
        .filtro-container {
            padding: 12px;
            border-radius: 10px;
        }
        
        .filtro-container form label {
            font-size: 13px;
        }
        
        .filtro-container form select {
            font-size: 13px;
            padding: 8px 12px;
        }
        
        .evaluacion-card {
            padding: 12px;
        }
        
        .evaluacion-card .header h4 {
            font-size: 14px;
        }
        
        .evaluacion-card .descripcion {
            font-size: 13px;
        }
        
        .evaluacion-card .info {
            grid-template-columns: 1fr;
            gap: 4px;
        }
        
        .evaluacion-card .info-item {
            font-size: 12px;
        }
        
        .evaluacion-card .puntaje-mostrado .puntaje {
            font-size: 17px;
        }
        
        .badge-estado {
            font-size: 11px;
            padding: 3px 10px;
        }
        
        .examen-container {
            padding: 10px;
            border-radius: 10px;
        }
        
        .examen-container .examen-header h3 {
            font-size: 15px;
        }
        
        .examen-container .examen-header .examen-info {
            font-size: 12px;
        }
        
        .examen-container .examen-header .timer {
            font-size: 18px;
            padding: 8px;
        }
        
        .pregunta-item {
            padding: 10px;
        }
        
        .pregunta-item .pregunta-texto {
            font-size: 13px;
        }
        
        .pregunta-item .opciones label {
            font-size: 12px;
            padding: 6px 8px;
        }
        
        .pregunta-item .respuesta-texto {
            font-size: 13px;
            padding: 8px;
        }
        
        .btn-enviar-examen {
            font-size: 14px;
            padding: 8px 16px;
        }
        
        .btn-volver {
            font-size: 13px;
            padding: 8px 16px;
        }
        
        .modal-box {
            padding: 15px;
        }
        
        .modal-box .modal-header h3 {
            font-size: 17px;
        }
        
        .modal-box .modal-resumen {
            flex-direction: column;
            text-align: center;
        }
        
        .modal-box .pregunta-resultado {
            padding: 10px 12px;
        }
        
        .modal-box .pregunta-resultado .pregunta-texto {
            font-size: 13px;
        }
        
        .modal-box .pregunta-resultado .respuesta-info {
            font-size: 12px;
        }
        
        .modal-box .modal-footer .btn-cerrar {
            font-size: 14px;
            padding: 8px 20px;
        }
        
        .modal-confirm .modal-box h3 {
            font-size: 17px;
        }
        
        .modal-confirm .modal-box p {
            font-size: 14px;
        }
        
        .modal-confirm .modal-box .btn-group button {
            font-size: 14px;
            padding: 8px 20px;
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
        .evaluaciones-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .evaluacion-card .header h4 {
            font-size: 13px;
        }
        
        .examen-container .examen-header h3 {
            font-size: 13px;
        }
        
        .examen-container .examen-header .timer {
            font-size: 16px;
        }
        
        .modal-box .modal-header h3 {
            font-size: 15px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .evaluaciones-container {
            padding: 10px;
        }
        
        .examen-container {
            padding: 15px;
        }
        
        .examen-container .examen-header .timer {
            font-size: 18px;
            padding: 6px;
        }
        
        .pregunta-item {
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .pregunta-item .opciones label {
            padding: 4px 10px;
            font-size: 13px;
        }
        
        .btn-enviar-examen {
            padding: 8px 20px;
            font-size: 14px;
        }
        
        .modal-box {
            max-height: 95vh;
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

<div class="evaluaciones-container">
    <!-- ============================================= -->
    <!-- MODO: VER RESULTADOS -->
    <!-- ============================================= -->
    <?php if ($ver_resultados > 0 && $resultados_detalle): ?>
        <div class="modal-overlay active" id="modalResultados">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><i class="fas fa-eye"></i> Resultados de la Evaluación</h3>
                    <button class="btn-close-modal" onclick="window.location.href='evaluaciones.php'">&times;</button>
                </div>
                
                <div class="modal-resumen">
                    <div>
                        <div style="font-size: 14px; color: #666;">Puntaje obtenido</div>
                        <div class="total <?php echo $resultados_detalle['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
                            <?php echo number_format($resultados_detalle['puntaje_obtenido'], 2); ?> / <?php echo $puntaje_maximo_evaluacion; ?>
                        </div>
                    </div>
                    <div>
                        <span class="estado-badge <?php echo $resultados_detalle['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
                            <?php echo $resultados_detalle['aprobado'] ? '✅ Aprobado' : '❌ Reprobado'; ?>
                        </span>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong style="color: #2c3e50;">Detalle de preguntas:</strong>
                </div>
                
                <?php foreach ($preguntas_detalle as $pregunta): 
                    // Obtener texto de la respuesta del estudiante
                    $respuesta_texto = '';
                    $tiene_respuesta = false;
                    $opciones = null;
                    
                    if (!empty($pregunta['opciones']) && $pregunta['opciones'] !== null) {
                        $opciones = json_decode($pregunta['opciones'], true);
                    }
                    
                    if (!empty($pregunta['respuesta']) && $pregunta['respuesta'] !== null && $pregunta['respuesta'] !== 'null') {
                        $raw = $pregunta['respuesta'];
                        $tipo = $pregunta['pregunta_tipo'];
                        
                        if (in_array($tipo, ['opcion_unica', 'opcion_multiple', 'verdadero_falso'])) {
                            try {
                                $indices = json_decode($raw, true);
                                if (is_array($indices) && $opciones && is_array($opciones)) {
                                    $textos = [];
                                    foreach ($indices as $idx) {
                                        if (isset($opciones[$idx])) {
                                            $textos[] = $opciones[$idx];
                                        }
                                    }
                                    $respuesta_texto = implode(', ', $textos);
                                    $tiene_respuesta = !empty($textos);
                                }
                            } catch (Exception $e) {
                                $respuesta_texto = $raw;
                                $tiene_respuesta = !empty(trim($raw));
                            }
                        } else {
                            $respuesta_texto = $raw;
                            $tiene_respuesta = !empty(trim($raw));
                        }
                    }
                    
                    $es_correcta = $pregunta['puntaje_obtenido'] > 0;
                    $clase = $es_correcta ? 'correcta' : 'incorrecta';
                    
                    $tipos = [
                        'opcion_unica' => 'Única',
                        'opcion_multiple' => 'Múltiple',
                        'verdadero_falso' => 'V/F',
                        'texto_corto' => 'Corto',
                        'texto_largo' => 'Largo'
                    ];
                ?>
                    <div class="pregunta-resultado <?php echo $clase; ?>">
                        <div class="pregunta-texto">
                            <?php echo htmlspecialchars($pregunta['pregunta_texto']); ?>
                            <span class="tipo-badge <?php echo $pregunta['pregunta_tipo']; ?>">
                                <?php echo $tipos[$pregunta['pregunta_tipo']] ?? $pregunta['pregunta_tipo']; ?>
                            </span>
                            <span style="color: #999; font-size: 12px;">(<?php echo $pregunta['pregunta_puntaje_maximo']; ?> pts)</span>
                        </div>
                        <div class="respuesta-info">
                            <div>
                                <span style="color: #999; font-size: 12px;">Tu respuesta:</span>
                                <span class="respuesta-estudiante <?php echo !$tiene_respuesta ? 'sin-respuesta' : ''; ?>">
                                    <?php echo $tiene_respuesta ? htmlspecialchars($respuesta_texto) : 'Sin respuesta'; ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="icono-resultado">
                                    <?php echo $es_correcta ? '✅' : '❌'; ?>
                                </span>
                                <span class="puntaje-obtenido <?php echo $es_correcta ? 'correcto' : 'incorrecto'; ?>">
                                    <?php echo number_format($pregunta['puntaje_obtenido'], 2); ?> / <?php echo $pregunta['pregunta_puntaje_maximo']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="modal-footer">
                    <button class="btn-cerrar" onclick="window.location.href='evaluaciones.php'">
                        <i class="fas fa-arrow-left"></i> Volver a evaluaciones
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================= -->
    <!-- MODO: EVALUACIÓN EN CURSO -->
    <!-- ============================================= -->
    <?php if ($evaluacion_actual && !empty($preguntas)): ?>
        <a href="evaluaciones.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a evaluaciones
        </a>

        <div class="examen-container">
            <div class="examen-header">
                <h3><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars($evaluacion_actual['titulo']); ?></h3>
                <div class="examen-info">
                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($evaluacion_actual['materia_nombre']); ?></span>
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($evaluacion_actual['tema_nombre']); ?></span>
                    <span><i class="fas fa-question-circle"></i> <?php echo count($preguntas); ?> preguntas</span>
                    <?php if ($tiempo_limite > 0): ?>
                        <span><i class="fas fa-clock"></i> <?php echo $tiempo_limite; ?> minutos</span>
                    <?php endif; ?>
                </div>
                <?php if ($tiempo_limite > 0): ?>
                    <div class="timer" id="timer">
                        <i class="fas fa-hourglass-half"></i> 
                        <span id="minutos"><?php echo str_pad($tiempo_limite, 2, '0', STR_PAD_LEFT); ?></span>:<span id="segundos">00</span>
                    </div>
                <?php endif; ?>
                <div class="progreso-bar">
                    <div class="fill" id="progresoFill" style="width: 0%;"></div>
                </div>
                <div class="progreso-texto" id="progresoTexto">0 / <?php echo count($preguntas); ?> respondidas</div>
            </div>

            <form method="POST" id="formExamen" onsubmit="return false;">
                <input type="hidden" name="action" value="enviar_evaluacion">
                <input type="hidden" name="evaluacion_id" value="<?php echo $evaluacion_actual['id']; ?>">
                <input type="hidden" name="tiempo_restante" id="tiempoRestante" value="<?php echo $tiempo_limite * 60; ?>">

                <?php foreach ($preguntas as $index => $pregunta): 
                    $opciones = null;
                    
                    if (!empty($pregunta['opciones']) && $pregunta['opciones'] !== null) {
                        $opciones = json_decode($pregunta['opciones'], true);
                    }
                    
                    $es_opcion = in_array($pregunta['tipo'], ['opcion_unica', 'opcion_multiple', 'verdadero_falso']);
                    $es_texto = in_array($pregunta['tipo'], ['texto_corto', 'texto_largo']);
                    
                    $tipos = [
                        'opcion_unica' => 'Opción Única',
                        'opcion_multiple' => 'Opción Múltiple',
                        'verdadero_falso' => 'Verdadero/Falso',
                        'texto_corto' => 'Texto Corto',
                        'texto_largo' => 'Texto Largo'
                    ];
                ?>
                    <div class="pregunta-item" data-pregunta-id="<?php echo $pregunta['id']; ?>">
                        <div class="pregunta-texto">
                            <span class="pregunta-numero">Pregunta <?php echo $index + 1; ?>:</span>
                            <?php echo htmlspecialchars($pregunta['pregunta']); ?>
                            <span class="puntaje-label">(<?php echo $pregunta['puntaje']; ?> pts)</span>
                            <span class="tipo-label"><?php echo $tipos[$pregunta['tipo']] ?? $pregunta['tipo']; ?></span>
                        </div>

                        <?php if ($es_opcion && !empty($opciones) && is_array($opciones)): ?>
                            <div class="opciones">
                                <?php foreach ($opciones as $idx => $opcion): ?>
                                    <label>
                                        <?php if ($pregunta['tipo'] == 'opcion_unica' || $pregunta['tipo'] == 'verdadero_falso'): ?>
                                            <input type="radio" name="respuestas[<?php echo $pregunta['id']; ?>]" value="<?php echo $idx; ?>" 
                                                   onchange="marcarRespondido(this)">
                                        <?php else: ?>
                                            <input type="checkbox" name="respuestas[<?php echo $pregunta['id']; ?>][]" value="<?php echo $idx; ?>" 
                                                   onchange="marcarRespondido(this)">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($opcion); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($es_texto): ?>
                            <div>
                                <textarea class="respuesta-texto" 
                                          name="respuestas[<?php echo $pregunta['id']; ?>]" 
                                          rows="<?php echo $pregunta['tipo'] == 'texto_largo' ? 5 : 2; ?>"
                                          placeholder="Escribe tu respuesta aquí..."
                                          oninput="marcarRespondido(this)"></textarea>
                            </div>
                        <?php else: ?>
                            <div style="color: #999; font-style: italic; padding: 10px;">
                                <i class="fas fa-exclamation-triangle"></i> No hay opciones disponibles para esta pregunta.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="button" class="btn-enviar-examen" id="btnEnviar" disabled onclick="mostrarModalConfirmacion()">
                    <i class="fas fa-paper-plane"></i> Terminar y Enviar
                </button>
            </form>
        </div>

        <!-- Modal de Confirmación -->
        <div class="modal-confirm" id="modalConfirmacion">
            <div class="modal-box">
                <h3>⚠️ ¿Estás seguro?</h3>
                <p>Una vez enviada la evaluación, <strong>no podrás modificarla</strong>.</p>
                <div class="resumen-box" id="resumenConfirmacion">
                    Cargando...
                </div>
                <div class="btn-group">
                    <button class="btn-cancelar" onclick="cerrarModalConfirmacion()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button class="btn-confirmar" onclick="confirmarEnvio()">
                        <i class="fas fa-check"></i> Sí, enviar
                    </button>
                </div>
            </div>
        </div>

        <script>
        // =============================================
        // VARIABLES
        // =============================================
        var totalPreguntas = <?php echo count($preguntas); ?>;
        var respondidas = new Set();
        var envioEnProceso = false;

        // =============================================
        // TIMER
        // =============================================
        <?php if ($tiempo_limite > 0): ?>
            var tiempoTotal = <?php echo $tiempo_limite * 60; ?>;
            var tiempoRestante = tiempoTotal;
            var timerInterval;
            var timerIniciado = false;

            function iniciarTimer() {
                if (timerIniciado) return;
                timerIniciado = true;
                
                timerInterval = setInterval(function() {
                    tiempoRestante--;
                    var minutos = Math.floor(tiempoRestante / 60);
                    var segundos = tiempoRestante % 60;
                    
                    document.getElementById('minutos').textContent = String(minutos).padStart(2, '0');
                    document.getElementById('segundos').textContent = String(segundos).padStart(2, '0');
                    document.getElementById('tiempoRestante').value = tiempoRestante;
                    
                    if (tiempoRestante <= 60) {
                        document.getElementById('timer').classList.add('warning');
                    }
                    
                    if (tiempoRestante <= 0) {
                        clearInterval(timerInterval);
                        alert('⏰ ¡Se acabó el tiempo! La evaluación se enviará automáticamente.');
                        enviarEvaluacion();
                    }
                }, 1000);
            }

            document.addEventListener('click', function() { iniciarTimer(); }, { once: true });
            document.addEventListener('scroll', function() { iniciarTimer(); }, { once: true });
            document.addEventListener('keydown', function() { iniciarTimer(); }, { once: true });
        <?php endif; ?>

        // =============================================
        // PROGRESO
        // =============================================
        function marcarRespondido(element) {
            var preguntaItem = element.closest('.pregunta-item');
            if (!preguntaItem) return;
            
            var preguntaId = preguntaItem.dataset.preguntaId;
            if (!preguntaId) return;
            
            var inputs = preguntaItem.querySelectorAll('input, textarea');
            var respondida = false;
            
            inputs.forEach(function(input) {
                if (input.type === 'radio' || input.type === 'checkbox') {
                    if (input.checked) respondida = true;
                } else if (input.tagName === 'TEXTAREA') {
                    if (input.value.trim() !== '') respondida = true;
                }
            });
            
            if (respondida) {
                respondidas.add(preguntaId);
            } else {
                respondidas.delete(preguntaId);
            }
            
            var totalRespondidas = respondidas.size;
            var porcentaje = (totalRespondidas / totalPreguntas) * 100;
            
            document.getElementById('progresoFill').style.width = porcentaje + '%';
            document.getElementById('progresoTexto').textContent = totalRespondidas + ' / ' + totalPreguntas + ' respondidas';
            document.getElementById('btnEnviar').disabled = (totalRespondidas < totalPreguntas);
        }

        // =============================================
        // SELECCIÓN VISUAL
        // =============================================
        document.querySelectorAll('.opciones label').forEach(function(label) {
            label.addEventListener('click', function(e) {
                var radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    var parent = this.closest('.opciones');
                    parent.querySelectorAll('label').forEach(function(l) {
                        l.classList.remove('selected');
                    });
                    this.classList.add('selected');
                }
                
                var checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    if (checkbox.checked) {
                        this.classList.add('selected');
                    } else {
                        this.classList.remove('selected');
                    }
                }
            });
        });

        // =============================================
        // MODAL DE CONFIRMACIÓN
        // =============================================
        function mostrarModalConfirmacion() {
            if (envioEnProceso) return;
            
            var totalRespondidas = respondidas.size;
            var faltantes = totalPreguntas - totalRespondidas;
            var resumen = '✅ Respondidas: <strong>' + totalRespondidas + '</strong> de ' + totalPreguntas;
            if (faltantes > 0) {
                resumen += '<br>⚠️ <span style="color: #e74c3c;">Faltan ' + faltantes + ' preguntas</span>';
            } else {
                resumen += '<br>🎯 <span style="color: #2ecc71;">¡Todas respondidas!</span>';
            }
            
            document.getElementById('resumenConfirmacion').innerHTML = resumen;
            document.getElementById('modalConfirmacion').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalConfirmacion() {
            document.getElementById('modalConfirmacion').classList.remove('active');
            document.body.style.overflow = '';
        }

        function confirmarEnvio() {
            cerrarModalConfirmacion();
            enviarEvaluacion();
        }

        // =============================================
        // ENVÍO
        // =============================================
        function enviarEvaluacion() {
            if (envioEnProceso) return;
            envioEnProceso = true;
            
            var form = document.getElementById('formExamen');
            var formData = new FormData(form);
            
            if (document.getElementById('tiempoRestante')) {
                formData.set('tiempo_restante', document.getElementById('tiempoRestante').value || 0);
            }
            
            // Mostrar loading
            var btn = document.getElementById('btnEnviar');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            btn.disabled = true;
            
            fetch('evaluaciones.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                if (html.includes('Evaluación enviada correctamente')) {
                    alert('✅ ¡Evaluación enviada correctamente!');
                    window.location.href = 'evaluaciones.php';
                } else {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    envioEnProceso = false;
                    alert('❌ Error al enviar. Intenta de nuevo.');
                }
            })
            .catch(function(error) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                envioEnProceso = false;
                alert('Error de conexión: ' + error.message);
            });
        }
        </script>

    <?php else: ?>
        <!-- ============================================= -->
        <!-- MODO: LISTA DE EVALUACIONES -->
        <!-- ============================================= -->
        <h3 class="page-title"><i class="fas fa-tasks"></i> Mis Evaluaciones</h3>

        <div class="filtro-container">
            <form method="GET">
                <label for="materia_id">
                    <i class="fas fa-book"></i> Materia:
                </label>
                <select name="materia_id" id="materia_id" onchange="this.form.submit()">
                    <option value="">-- Todas las materias --</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($materia['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($materia_id > 0): ?>
                    <a href="evaluaciones.php" class="btn-limpiar">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($evaluaciones)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h4>No hay evaluaciones disponibles</h4>
                <p>Espera a que tu profesor publique nuevas evaluaciones</p>
            </div>
        <?php else: ?>
            <?php foreach ($evaluaciones as $eval): 
                $completada = $eval['intentos_realizados'] > 0;
                $aprobada = $eval['mejor_puntaje'] >= $eval['puntaje_aprobacion'];
                $puede_intentar = $eval['intentos_realizados'] < $eval['intentos_permitidos'];
                $pendiente_correccion = $completada && $eval['ultimo_estado'] == 'pendiente';
                
                $clase_borde = 'evaluacion-card';
                if ($completada && $aprobada) {
                    $clase_borde .= ' completada';
                } elseif ($pendiente_correccion) {
                    $clase_borde .= ' pendiente-correccion';
                }
                
                $badge_texto = '⏳ Pendiente';
                $badge_clase = 'badge-pendiente';
                if ($completada && $pendiente_correccion) {
                    $badge_texto = '⏳ En corrección';
                    $badge_clase = 'badge-correccion';
                } elseif ($completada && $aprobada) {
                    $badge_texto = '✅ Aprobada';
                    $badge_clase = 'badge-aprobada';
                } elseif ($completada && !$aprobada) {
                    $badge_texto = '❌ Reprobada';
                    $badge_clase = 'badge-reprobada';
                }
            ?>
                <div class="<?php echo $clase_borde; ?>">
                    <div class="header">
                        <h4><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars($eval['titulo']); ?></h4>
                        <span class="badge-estado <?php echo $badge_clase; ?>">
                            <?php echo $badge_texto; ?>
                        </span>
                    </div>
                    <div class="descripcion"><?php echo htmlspecialchars($eval['descripcion']); ?></div>
                    <div class="info">
                        <div class="info-item">
                            <i class="fas fa-book"></i> <strong>Materia:</strong> <?php echo htmlspecialchars($eval['materia_nombre']); ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-tag"></i> <strong>Tema:</strong> <?php echo htmlspecialchars($eval['tema_nombre']); ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-question-circle"></i> <strong>Preguntas:</strong> <?php echo $eval['total_preguntas']; ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-redo"></i> <strong>Intentos:</strong> <?php echo $eval['intentos_realizados']; ?>/<?php echo $eval['intentos_permitidos']; ?>
                        </div>
                        <?php if ($eval['tiempo_limite_minutos']): ?>
                            <div class="info-item">
                                <i class="fas fa-clock"></i> <strong>Tiempo:</strong> <?php echo $eval['tiempo_limite_minutos']; ?> min
                            </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <i class="fas fa-star"></i> <strong>Puntaje:</strong> <?php echo $eval['puntaje_maximo']; ?> pts
                        </div>
                        <div class="info-item">
                            <i class="fas fa-flag-checkered"></i> <strong>Aprobación:</strong> <?php echo $eval['puntaje_aprobacion']; ?> pts
                        </div>
                    </div>
                    
                    <?php if ($completada): ?>
                        <div class="puntaje-mostrado">
                            <span class="puntaje <?php echo $pendiente_correccion ? 'pendiente' : ($aprobada ? 'aprobado' : 'reprobado'); ?>">
                                <?php if ($pendiente_correccion): ?>
                                    ⏳ Pendiente de corrección
                                <?php else: ?>
                                    <?php echo $eval['mejor_puntaje']; ?> / <?php echo $eval['puntaje_maximo']; ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($eval['ultima_fecha']): ?>
                                <span style="color: #999; font-size: 13px;">
                                    <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($eval['ultima_fecha'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <?php if (!$completada || ($completada && !$aprobada && $puede_intentar && !$pendiente_correccion)): ?>
                            <a href="evaluaciones.php?id=<?php echo $eval['id']; ?>" class="btn-realizar">
                                <i class="fas fa-play"></i> <?php echo $completada ? 'Reintentar' : 'Realizar'; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($completada && !$pendiente_correccion): ?>
                            <a href="evaluaciones.php?ver_resultados=<?php echo $eval['id']; ?>" class="btn-ver-resultados">
                                <i class="fas fa-eye"></i> Ver Resultados
                            </a>
                        <?php endif; ?>
                        <?php if ($pendiente_correccion): ?>
                            <span style="color: #999; font-size: 13px; font-style: italic; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-hourglass-half"></i> Esperando corrección del profesor...
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>