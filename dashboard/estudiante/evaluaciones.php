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
            // =============================================
            // CORRECCIÓN: Siempre guardar como JSON array
            // =============================================
            if (is_array($respuesta)) {
                // Ya es un array (checkbox múltiple)
                $respuesta = json_encode(array_values($respuesta));
            } else {
                // Para opción única (radio) o texto
                if ($respuesta !== '' && $respuesta !== null) {
                    // Guardar como JSON array para consistencia
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
// 3. OBTENER DATOS PARA LA EVALUACIÓN EN CURSO
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
// 4. OBTENER LISTA DE EVALUACIONES
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
// 5. AHORA SÍ: INCLUIR EL HEADER
// =============================================
require_once 'includes/estudiante_header.php';
?>

<style>
    .filtro-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .filtro-container select {
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        min-width: 200px;
        margin-right: 10px;
    }
    .filtro-container select:focus {
        outline: none;
        border-color: #3498db;
    }
    
    /* Estilos para la lista de evaluaciones */
    .evaluacion-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        border-left: 4px solid #3498db;
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
    }
    .evaluacion-card .header h4 {
        margin: 0;
        color: #2c3e50;
    }
    .evaluacion-card .info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 10px 0;
    }
    .evaluacion-card .info-item {
        font-size: 14px;
        color: #666;
    }
    .evaluacion-card .info-item strong {
        color: #2c3e50;
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
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
    }
    .btn-realizar:hover {
        background: #27ae60;
    }
    .btn-realizar:disabled {
        background: #95a5a6;
        cursor: not-allowed;
    }
    .btn-ver-resultados {
        background: #3498db;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .btn-ver-resultados:hover {
        background: #2980b9;
    }
    .badge-estado {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
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
    .puntaje {
        font-size: 20px;
        font-weight: bold;
    }
    .puntaje.aprobado {
        color: #2ecc71;
    }
    .puntaje.reprobado {
        color: #e74c3c;
    }
    .puntaje.pendiente {
        color: #f39c12;
    }

    /* Estilos para el examen en curso */
    .examen-container {
        background: white;
        border-radius: 10px;
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
    }
    .examen-container .examen-header .examen-info {
        color: #666;
        font-size: 14px;
        margin-top: 5px;
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
    }
    .examen-container .examen-header .timer.warning {
        color: #e74c3c;
        animation: blink 1s infinite;
    }
    @keyframes blink {
        50% { opacity: 0.5; }
    }
    .pregunta-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 3px solid #3498db;
    }
    .pregunta-item .pregunta-texto {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 10px;
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
    .pregunta-item .opciones {
        margin-left: 10px;
    }
    .pregunta-item .opciones label {
        display: block;
        padding: 8px 12px;
        margin-bottom: 5px;
        background: white;
        border-radius: 5px;
        border: 1px solid #e0e0e0;
        cursor: pointer;
        transition: all 0.2s;
    }
    .pregunta-item .opciones label:hover {
        background: #e3f2fd;
        border-color: #3498db;
    }
    .pregunta-item .opciones input[type="radio"],
    .pregunta-item .opciones input[type="checkbox"] {
        margin-right: 8px;
    }
    .pregunta-item .opciones label.selected {
        background: #e3f2fd;
        border-color: #3498db;
    }
    .pregunta-item .respuesta-texto {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 5px;
        font-size: 14px;
        font-family: inherit;
        resize: vertical;
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
        transition: background 0.3s;
        margin-top: 10px;
    }
    .btn-enviar-examen:hover {
        background: #27ae60;
    }
    .btn-enviar-examen:disabled {
        background: #95a5a6;
        cursor: not-allowed;
    }
    .btn-volver {
        background: #95a5a6;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 15px;
    }
    .btn-volver:hover {
        background: #7f8c8d;
    }
    .progreso-bar {
        background: #f0f0f0;
        border-radius: 5px;
        height: 8px;
        overflow: hidden;
        margin: 10px 0;
    }
    .progreso-bar .fill {
        height: 100%;
        background: linear-gradient(90deg, #3498db, #2ecc71);
        transition: width 0.5s;
        border-radius: 5px;
    }
    .progreso-texto {
        font-size: 13px;
        color: #999;
        text-align: right;
    }
    
    .btn-voltar {
        background: #95a5a6;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 15px;
    }
    .btn-voltar:hover {
        background: #7f8c8d;
    }
    .btn-voltar i {
        margin-right: 5px;
    }
</style>

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

<!-- ============================================= -->
<!-- MODO: EVALUACIÓN EN CURSO -->
<!-- ============================================= -->
<?php if ($evaluacion_actual && !empty($preguntas)): ?>
    <a href="evaluaciones.php" class="btn-voltar">
        <i class="fas fa-arrow-left"></i> Volver a evaluaciones
    </a>

    <div class="examen-container">
        <div class="examen-header">
            <h3><?php echo htmlspecialchars($evaluacion_actual['titulo']); ?></h3>
            <div class="examen-info">
                <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($evaluacion_actual['materia_nombre']); ?></span>
                <span style="margin-left: 15px;"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($evaluacion_actual['tema_nombre']); ?></span>
                <span style="margin-left: 15px;"><i class="fas fa-question-circle"></i> <?php echo count($preguntas); ?> preguntas</span>
                <?php if ($tiempo_limite > 0): ?>
                    <span style="margin-left: 15px;"><i class="fas fa-clock"></i> <?php echo $tiempo_limite; ?> minutos</span>
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

        <form method="POST" id="formExamen" onsubmit="return confirmarEnvio()">
            <input type="hidden" name="action" value="enviar_evaluacion">
            <input type="hidden" name="evaluacion_id" value="<?php echo $evaluacion_actual['id']; ?>">
            <input type="hidden" name="tiempo_restante" id="tiempoRestante" value="<?php echo $tiempo_limite * 60; ?>">

            <?php foreach ($preguntas as $index => $pregunta): 
                // Decodificar opciones de forma segura
                $opciones = null;
                
                if (!empty($pregunta['opciones']) && $pregunta['opciones'] !== null) {
                    $opciones = json_decode($pregunta['opciones'], true);
                }
                
                $es_opcion = in_array($pregunta['tipo'], ['opcion_unica', 'opcion_multiple', 'verdadero_falso']);
                $es_texto = in_array($pregunta['tipo'], ['texto_corto', 'texto_largo']);
            ?>
                <div class="pregunta-item" data-pregunta-id="<?php echo $pregunta['id']; ?>">
                    <div class="pregunta-texto">
                        <span class="pregunta-numero">Pregunta <?php echo $index + 1; ?>:</span>
                        <?php echo htmlspecialchars($pregunta['pregunta']); ?>
                        <span class="puntaje-label">(<?php echo $pregunta['puntaje']; ?> pts)</span>
                        <span style="font-size: 11px; color: #999; margin-left: 10px;">
                            <?php 
                            $tipos = [
                                'opcion_unica' => 'Opción Única',
                                'opcion_multiple' => 'Opción Múltiple',
                                'verdadero_falso' => 'Verdadero/Falso',
                                'texto_corto' => 'Texto Corto',
                                'texto_largo' => 'Texto Largo'
                            ];
                            echo $tipos[$pregunta['tipo']] ?? $pregunta['tipo'];
                            ?>
                        </span>
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

            <button type="submit" class="btn-enviar-examen" id="btnEnviar" disabled>
                <i class="fas fa-paper-plane"></i> Terminar y Enviar
            </button>
        </form>
    </div>

    <script>
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
                    alert('¡Se acabó el tiempo! La evaluación se enviará automáticamente.');
                    document.getElementById('formExamen').submit();
                }
            }, 1000);
        }

        // Iniciar timer cuando el usuario interactúe
        document.addEventListener('click', function() {
            iniciarTimer();
        }, { once: true });

        // También iniciar con scroll o teclado
        document.addEventListener('scroll', function() {
            iniciarTimer();
        }, { once: true });
        
        document.addEventListener('keydown', function() {
            iniciarTimer();
        }, { once: true });
    <?php endif; ?>

    // =============================================
    // PROGRESO
    // =============================================
    var totalPreguntas = <?php echo count($preguntas); ?>;
    var respondidas = new Set();

    function marcarRespondido(element) {
        var preguntaItem = element.closest('.pregunta-item');
        if (!preguntaItem) return;
        
        var preguntaId = preguntaItem.dataset.preguntaId;
        if (!preguntaId) return;
        
        // Verificar si la pregunta está realmente respondida
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
        
        // Actualizar barra de progreso
        var totalRespondidas = respondidas.size;
        var porcentaje = (totalRespondidas / totalPreguntas) * 100;
        
        document.getElementById('progresoFill').style.width = porcentaje + '%';
        document.getElementById('progresoTexto').textContent = totalRespondidas + ' / ' + totalPreguntas + ' respondidas';
        
        // Habilitar/deshabilitar botón de enviar
        document.getElementById('btnEnviar').disabled = (totalRespondidas < totalPreguntas);
    }

    // =============================================
    // CONFIRMAR ENVÍO
    // =============================================
    function confirmarEnvio() {
        var totalRespondidas = respondidas.size;
        if (totalRespondidas < totalPreguntas) {
            if (!confirm('Faltan ' + (totalPreguntas - totalRespondidas) + ' preguntas por responder. ¿Estás seguro de que quieres enviar?')) {
                return false;
            }
        }
        return confirm('¿Estás seguro de que quieres enviar la evaluación?\n\n' +
                      'Respondidas: ' + totalRespondidas + ' de ' + totalPreguntas + ' preguntas\n' +
                      'No podrás modificarla después de enviar.');
    }

    // =============================================
    // SELECCIÓN VISUAL DE OPCIONES
    // =============================================
    document.querySelectorAll('.opciones label').forEach(function(label) {
        label.addEventListener('click', function(e) {
            // Para opción única, quitar selección de otros
            var radio = this.querySelector('input[type="radio"]');
            if (radio) {
                var parent = this.closest('.opciones');
                parent.querySelectorAll('label').forEach(function(l) {
                    l.classList.remove('selected');
                });
                this.classList.add('selected');
            }
            
            // Para checkbox, toggle
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
    </script>

<?php else: ?>
    <!-- ============================================= -->
    <!-- MODO: LISTA DE EVALUACIONES -->
    <!-- ============================================= -->
    <h3><i class="fas fa-tasks"></i> Mis Evaluaciones</h3>

    <div class="filtro-container">
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <label style="font-weight: 500;">
                <i class="fas fa-book"></i> Materia:
            </label>
            <select name="materia_id" onchange="this.form.submit()">
                <option value="">-- Todas las materias --</option>
                <?php foreach ($materias as $materia): ?>
                    <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($materia['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($materia_id > 0): ?>
                <a href="evaluaciones.php" class="btn-sm btn-delete" style="background: #95a5a6; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($evaluaciones)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
            <i class="fas fa-file-alt" style="font-size: 48px; color: #ccc;"></i>
            <p style="color: #999; margin-top: 15px;">No hay evaluaciones disponibles</p>
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
                    <h4><?php echo htmlspecialchars($eval['titulo']); ?></h4>
                    <span class="badge-estado <?php echo $badge_clase; ?>">
                        <?php echo $badge_texto; ?>
                    </span>
                </div>
                <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars($eval['descripcion']); ?></p>
                <div class="info">
                    <div class="info-item">
                        <strong>Materia:</strong> <?php echo htmlspecialchars($eval['materia_nombre']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Tema:</strong> <?php echo htmlspecialchars($eval['tema_nombre']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Preguntas:</strong> <?php echo $eval['total_preguntas']; ?>
                    </div>
                    <div class="info-item">
                        <strong>Intentos:</strong> <?php echo $eval['intentos_realizados']; ?>/<?php echo $eval['intentos_permitidos']; ?>
                    </div>
                    <?php if ($eval['tiempo_limite_minutos']): ?>
                        <div class="info-item">
                            <strong>Tiempo:</strong> <?php echo $eval['tiempo_limite_minutos']; ?> min
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <strong>Puntaje:</strong> <?php echo $eval['puntaje_maximo']; ?> pts
                    </div>
                    <div class="info-item">
                        <strong>Aprobación:</strong> <?php echo $eval['puntaje_aprobacion']; ?> pts
                    </div>
                </div>
                
                <?php if ($completada): ?>
                    <div style="margin: 10px 0;">
                        <span class="puntaje <?php echo $pendiente_correccion ? 'pendiente' : ($aprobada ? 'aprobado' : 'reprobado'); ?>">
                            <?php if ($pendiente_correccion): ?>
                                ⏳ Pendiente de corrección
                            <?php else: ?>
                                <?php echo $eval['mejor_puntaje']; ?> / <?php echo $eval['puntaje_maximo']; ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($eval['ultima_fecha']): ?>
                            <span style="color: #999; font-size: 13px; margin-left: 10px;">
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
                        <a href="#" class="btn-ver-resultados" onclick="alert('Funcionalidad en desarrollo - Ver resultados')">
                            <i class="fas fa-eye"></i> Ver Resultados
                        </a>
                    <?php endif; ?>
                    <?php if ($pendiente_correccion): ?>
                        <span style="color: #999; font-size: 13px; font-style: italic;">
                            <i class="fas fa-hourglass-half"></i> Esperando corrección del profesor...
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>