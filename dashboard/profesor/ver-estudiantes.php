<?php
// PRIMERO: Procesar acciones
$page_title = 'Ver Estudiantes';
$page_icon = 'users';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener materias del profesor
try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM Materias WHERE id_profesor = ? AND estado = 'activo' ORDER BY nombre");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias = [];
}

$materia_id = $_GET['materia_id'] ?? 0;

// Obtener estudiantes de la materia seleccionada
$estudiantes = [];
if ($materia_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                u.id, u.nombre, u.apellido, u.correo, u.fecha_registro,
                (SELECT AVG(r.puntaje_obtenido) FROM ResultadosEvaluacion r 
                 JOIN Evaluaciones e ON r.id_evaluacion = e.id 
                 JOIN Temas t ON e.id_tema = t.id 
                 WHERE t.id_materia = ? AND r.id_usuario = u.id) as promedio
            FROM Inscripciones i
            JOIN Usuarios u ON i.id_usuario = u.id
            WHERE i.id_materia = ? AND u.activo = 1
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$materia_id, $materia_id]);
        $estudiantes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $estudiantes = [];
        $_SESSION['error'] = 'Error al cargar estudiantes';
    }
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    /* ===== RESET & BASE ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* ===== CONTENEDOR PRINCIPAL ===== */
    .estudiantes-container {
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
        color: #9b59b6;
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
        color: #9b59b6;
    }
    
    .filtro-container form select {
        padding: 10px 14px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        min-width: 250px;
        flex: 1;
        transition: border-color 0.3s;
        background: white;
        cursor: pointer;
    }
    
    .filtro-container form select:focus {
        outline: none;
        border-color: #9b59b6;
    }
    
    .filtro-container form select option {
        padding: 8px;
    }

    /* ===== ESTADÍSTICAS ===== */
    .estadisticas {
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .estadisticas .total {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
    }
    
    .estadisticas .total i {
        color: #9b59b6;
        margin-right: 8px;
    }
    
    .estadisticas .total strong {
        color: #9b59b6;
        font-size: 18px;
    }

    /* ===== ESTUDIANTE CARD ===== */
    .estudiante-card {
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        transition: all 0.3s;
        border-left: 4px solid transparent;
    }
    
    .estudiante-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transform: translateX(4px);
        border-left-color: #9b59b6;
    }
    
    .estudiante-card .estudiante-info {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .estudiante-card .estudiante-info .nombre {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .estudiante-card .estudiante-info .nombre .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    
    .estudiante-card .estudiante-info .correo {
        color: #666;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .estudiante-card .estudiante-info .correo i {
        color: #9b59b6;
        font-size: 13px;
    }
    
    .estudiante-card .estudiante-info .fecha {
        color: #999;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .estudiante-card .estudiante-info .fecha i {
        color: #9b59b6;
        font-size: 13px;
    }
    
    .estudiante-card .promedio-container {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .estudiante-card .promedio {
        font-weight: 700;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .estudiante-card .promedio.alto {
        background: #d4edda;
        color: #155724;
    }
    
    .estudiante-card .promedio.medio {
        background: #fff3cd;
        color: #856404;
    }
    
    .estudiante-card .promedio.bajo {
        background: #f8d7da;
        color: #721c24;
    }
    
    .estudiante-card .promedio.sin {
        background: #e9ecef;
        color: #6c757d;
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
        .estudiantes-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .filtro-container form select {
            min-width: 200px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .estudiantes-container {
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
        
        .estadisticas {
            padding: 12px 15px;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .estudiante-card {
            padding: 12px 15px;
            flex-direction: column;
            align-items: stretch;
        }
        
        .estudiante-card .estudiante-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .estudiante-card .estudiante-info .nombre {
            font-size: 14px;
        }
        
        .estudiante-card .estudiante-info .nombre .avatar {
            width: 32px;
            height: 32px;
            font-size: 12px;
        }
        
        .estudiante-card .estudiante-info .correo {
            font-size: 13px;
        }
        
        .estudiante-card .estudiante-info .fecha {
            font-size: 12px;
        }
        
        .estudiante-card .promedio-container {
            align-self: flex-start;
        }
        
        .estudiante-card .promedio {
            font-size: 13px;
            padding: 4px 12px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .estudiantes-container {
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
        
        .estadisticas {
            padding: 10px 12px;
            font-size: 13px;
        }
        
        .estadisticas .total {
            font-size: 13px;
        }
        
        .estadisticas .total strong {
            font-size: 16px;
        }
        
        .estudiante-card {
            padding: 10px 12px;
            border-radius: 10px;
        }
        
        .estudiante-card .estudiante-info .nombre {
            font-size: 13px;
        }
        
        .estudiante-card .estudiante-info .nombre .avatar {
            width: 28px;
            height: 28px;
            font-size: 11px;
        }
        
        .estudiante-card .estudiante-info .correo {
            font-size: 12px;
        }
        
        .estudiante-card .estudiante-info .fecha {
            font-size: 11px;
        }
        
        .estudiante-card .promedio {
            font-size: 12px;
            padding: 3px 10px;
        }
        
        .estudiante-card .promedio i {
            font-size: 11px;
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
        .estudiantes-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .filtro-container {
            padding: 10px;
        }
        
        .filtro-container form select {
            font-size: 12px;
            padding: 6px 10px;
        }
        
        .estudiante-card .estudiante-info .nombre {
            font-size: 12px;
        }
        
        .estudiante-card .estudiante-info .correo {
            font-size: 11px;
        }
        
        .estudiante-card .promedio {
            font-size: 11px;
            padding: 2px 8px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .estudiantes-container {
            padding: 10px;
        }
        
        .filtro-container {
            padding: 12px;
        }
        
        .filtro-container form {
            flex-direction: row;
            flex-wrap: wrap;
        }
        
        .filtro-container form select {
            min-width: 200px;
            flex: 1;
        }
        
        .estudiante-card {
            padding: 10px 15px;
        }
        
        .estudiante-card .estudiante-info {
            gap: 12px;
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
        background: #9b59b6;
        color: white;
    }

    /* ===== UTILITY ===== */
    .hidden {
        display: none !important;
    }
</style>

<div class="estudiantes-container">
    <h3 class="page-title"><i class="fas fa-users"></i> Ver Estudiantes</h3>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- FILTRO POR MATERIA -->
    <div class="filtro-container">
        <form method="GET">
            <label for="materia_id">
                <i class="fas fa-book"></i> Seleccionar Materia:
            </label>
            <select name="materia_id" id="materia_id" onchange="this.form.submit()">
                <option value="">-- Selecciona una materia --</option>
                <?php foreach ($materias as $materia): ?>
                    <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($materia['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($materia_id == 0): ?>
        <!-- ESTADO: SIN MATERIA SELECCIONADA -->
        <div class="empty-state">
            <i class="fas fa-hand-pointer"></i>
            <h4>Selecciona una materia</h4>
            <p>Elige una materia del menú desplegable para ver sus estudiantes inscritos</p>
        </div>
    <?php elseif (empty($estudiantes)): ?>
        <!-- ESTADO: MATERIA SIN ESTUDIANTES -->
        <div class="empty-state">
            <i class="fas fa-user-graduate"></i>
            <h4>No hay estudiantes inscritos</h4>
            <p>Esta materia aún no tiene estudiantes inscritos. Comparte el código de inscripción con tus alumnos.</p>
        </div>
    <?php else: ?>
        <!-- ESTADÍSTICAS -->
        <div class="estadisticas">
            <span class="total">
                <i class="fas fa-users"></i> Total de estudiantes: <strong><?php echo count($estudiantes); ?></strong>
            </span>
            <?php 
            $con_promedio = 0;
            $sin_promedio = 0;
            foreach ($estudiantes as $est) {
                if ($est['promedio'] !== null) {
                    $con_promedio++;
                } else {
                    $sin_promedio++;
                }
            }
            ?>
            <span style="font-size: 13px; color: #999;">
                <span style="color: #2ecc71;">✅ <?php echo $con_promedio; ?> con calificaciones</span>
                <span style="margin-left: 15px; color: #f39c12;">⏳ <?php echo $sin_promedio; ?> sin calificaciones</span>
            </span>
        </div>
        
        <!-- LISTA DE ESTUDIANTES -->
        <?php foreach ($estudiantes as $estudiante): ?>
            <div class="estudiante-card">
                <div class="estudiante-info">
                    <span class="nombre">
                        <span class="avatar">
                            <?php echo strtoupper(substr($estudiante['nombre'], 0, 1)); ?>
                        </span>
                        <?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']); ?>
                    </span>
                    <span class="correo">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($estudiante['correo']); ?>
                    </span>
                    <span class="fecha">
                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($estudiante['fecha_registro'])); ?>
                    </span>
                </div>
                <div class="promedio-container">
                    <?php if ($estudiante['promedio'] !== null): ?>
                        <?php 
                        $promedio = round($estudiante['promedio'], 2);
                        $clase = $promedio >= 70 ? 'alto' : ($promedio >= 50 ? 'medio' : 'bajo');
                        ?>
                        <span class="promedio <?php echo $clase; ?>">
                            <i class="fas fa-star"></i> <?php echo $promedio; ?>
                        </span>
                    <?php else: ?>
                        <span class="promedio sin">
                            <i class="fas fa-minus"></i> Sin calificaciones
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>