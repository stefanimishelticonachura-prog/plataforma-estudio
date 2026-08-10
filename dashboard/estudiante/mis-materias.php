<?php
// =============================================
// 1. PRIMERO: Configuración y procesamiento (ANTES del header)
// =============================================
$page_title = 'Mis Materias';
$page_icon = 'book';

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
// PROCESAR INSCRIPCIÓN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inscribir') {
    $materia_id = $_POST['materia_id'];
    
    try {
        // Verificar que la materia existe y está activa
        $stmt = $pdo->prepare("SELECT id FROM Materias WHERE id = ? AND estado = 'activo'");
        $stmt->execute([$materia_id]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'La materia no está disponible';
            header('Location: mis-materias.php');
            exit();
        }
        
        // Verificar si ya está inscrito
        $stmt = $pdo->prepare("SELECT id FROM Inscripciones WHERE id_usuario = ? AND id_materia = ?");
        $stmt->execute([$usuario_id, $materia_id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Ya estás inscrito en esta materia';
            header('Location: mis-materias.php');
            exit();
        }
        
        // Inscribir
        $stmt = $pdo->prepare("INSERT INTO Inscripciones (id_usuario, id_materia) VALUES (?, ?)");
        $stmt->execute([$usuario_id, $materia_id]);
        
        $_SESSION['success'] = '¡Te has inscrito correctamente en la materia!';
        header('Location: mis-materias.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al inscribirse: ' . $e->getMessage();
        header('Location: mis-materias.php');
        exit();
    }
}

// =============================================
// PROCESAR ELIMINACIÓN (DAR DE BAJA)
// =============================================
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $materia_id = $_GET['id'] ?? 0;
    
    if ($materia_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Inscripciones WHERE id_usuario = ? AND id_materia = ?");
            $stmt->execute([$usuario_id, $materia_id]);
            $_SESSION['success'] = 'Has dado de baja la materia correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al dar de baja: ' . $e->getMessage();
        }
        header('Location: mis-materias.php');
        exit();
    }
}

// =============================================
// 2. OBTENER DATOS
// =============================================

// Obtener materias disponibles (NO inscritas)
try {
    $stmt = $pdo->prepare("
        SELECT m.*, CONCAT(u.nombre, ' ', u.apellido) as profesor, u.correo as profesor_correo
        FROM Materias m
        JOIN Usuarios u ON m.id_profesor = u.id
        WHERE m.estado = 'activo' 
        AND m.id NOT IN (
            SELECT id_materia FROM Inscripciones WHERE id_usuario = ?
        )
        ORDER BY m.nombre
    ");
    $stmt->execute([$usuario_id]);
    $materias_disponibles = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias_disponibles = [];
}

// Obtener materias inscritas con progreso CORREGIDO
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.*, 
            CONCAT(u.nombre, ' ', u.apellido) as profesor,
            u.correo as profesor_correo,
            (SELECT COUNT(*) FROM Temas WHERE id_materia = m.id) as total_temas,
            (SELECT COUNT(*) FROM Inscripciones WHERE id_materia = m.id) as total_estudiantes,
            (
                SELECT COUNT(*) 
                FROM Progreso p
                JOIN Temas t ON p.id_tema = t.id
                WHERE t.id_materia = m.id 
                AND p.id_usuario = ?
                AND p.porcentaje = 100
            ) as temas_completados,
            (
                SELECT AVG(p.porcentaje) 
                FROM Progreso p
                JOIN Temas t ON p.id_tema = t.id
                WHERE t.id_materia = m.id 
                AND p.id_usuario = ?
            ) as promedio_progreso
        FROM Inscripciones i
        JOIN Materias m ON i.id_materia = m.id
        JOIN Usuarios u ON m.id_profesor = u.id
        WHERE i.id_usuario = ? AND m.estado = 'activo'
        ORDER BY m.nombre
    ");
    $stmt->execute([$usuario_id, $usuario_id, $usuario_id]);
    $materias_inscritas = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias_inscritas = [];
}

// =============================================
// 3. AHORA SÍ: INCLUIR EL HEADER
// =============================================
require_once 'includes/estudiante_header.php';
?>

<style>
    /* ============================================= */
    /* ESTILOS PARA MATERIAS DISPONIBLES */
    /* ============================================= */
    .materias-disponibles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    
    .materia-disponible-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 2px solid #e0e0e0;
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
    }
    .materia-disponible-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        border-color: #3498db;
    }
    .materia-disponible-card .nombre {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    .materia-disponible-card .profesor {
        color: #666;
        font-size: 14px;
        margin-bottom: 3px;
    }
    .materia-disponible-card .profesor i {
        color: #3498db;
        margin-right: 5px;
    }
    .materia-disponible-card .correo {
        color: #999;
        font-size: 13px;
        margin-bottom: 10px;
    }
    .materia-disponible-card .descripcion {
        color: #666;
        font-size: 14px;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .materia-disponible-card .badge-disponible {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .materia-disponible-card .btn-inscribir {
        background: #2ecc71;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.3s;
        width: 100%;
        margin-top: 10px;
    }
    .materia-disponible-card .btn-inscribir:hover {
        background: #27ae60;
    }
    .materia-disponible-card .btn-inscribir i {
        margin-right: 5px;
    }
    
    /* ============================================= */
    /* ESTILOS PARA MATERIAS INSCRITAS */
    /* ============================================= */
    .materias-inscritas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-top: 15px;
    }
    
    .materia-inscrita-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border-left: 4px solid #3498db;
        transition: transform 0.2s;
    }
    .materia-inscrita-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    .materia-inscrita-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    .materia-inscrita-card .header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 18px;
    }
    .materia-inscrita-card .header .badge-activa {
        background: #d4edda;
        color: #155724;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .materia-inscrita-card .profesor-info {
        color: #666;
        font-size: 14px;
        margin-bottom: 8px;
    }
    .materia-inscrita-card .profesor-info i {
        color: #3498db;
        margin-right: 5px;
    }
    .materia-inscrita-card .temas-list {
        margin: 10px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .materia-inscrita-card .temas-list .tema-item {
        font-size: 13px;
        color: #555;
        padding: 3px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .materia-inscrita-card .temas-list .tema-item:last-child {
        border-bottom: none;
    }
    .materia-inscrita-card .temas-list .tema-item i {
        color: #3498db;
        font-size: 12px;
    }
    .materia-inscrita-card .temas-list .sin-temas {
        color: #999;
        font-size: 13px;
        font-style: italic;
    }
    .materia-inscrita-card .progreso-bar {
        margin: 12px 0;
        background: #f0f0f0;
        border-radius: 5px;
        height: 8px;
        overflow: hidden;
    }
    .materia-inscrita-card .progreso-bar .fill {
        height: 100%;
        background: linear-gradient(90deg, #3498db, #2ecc71);
        transition: width 0.5s;
        border-radius: 5px;
    }
    .materia-inscrita-card .progreso-texto {
        text-align: right;
        font-size: 13px;
        color: #666;
    }
    .materia-inscrita-card .actions {
        margin-top: 15px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .materia-inscrita-card .actions .btn-sm {
        padding: 5px 12px;
        font-size: 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background 0.2s;
    }
    .btn-ver-materia {
        background: #3498db;
        color: white;
    }
    .btn-ver-materia:hover {
        background: #2980b9;
    }
    .btn-baja-materia {
        background: #e74c3c;
        color: white;
    }
    .btn-baja-materia:hover {
        background: #c0392b;
    }
    .btn-material {
        background: #9b59b6;
        color: white;
    }
    .btn-material:hover {
        background: #8e44ad;
    }
    .btn-evaluacion {
        background: #e67e22;
        color: white;
    }
    .btn-evaluacion:hover {
        background: #d35400;
    }
    
    .sin-materias {
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 10px;
        color: #999;
    }
    .sin-materias i {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
        color: #ccc;
    }
    
    /* ============================================= */
    /* MODAL DE INSCRIPCIÓN / VER DETALLE */
    /* ============================================= */
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
        max-width: 600px;
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
    }
    .modal-content .modal-header .btn-volver {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #3498db;
        transition: color 0.3s;
        padding: 0 10px 0 0;
    }
    .modal-content .modal-header .btn-volver:hover {
        color: #2980b9;
    }
    .modal-content .modal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
        flex: 1;
    }
    .modal-content .modal-header .btn-close-modal {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s;
        line-height: 1;
        padding: 0 0 0 10px;
    }
    .modal-content .modal-header .btn-close-modal:hover {
        color: #333;
    }
    .modal-content .detalle-materia {
        margin-bottom: 20px;
    }
    .modal-content .detalle-materia .label {
        font-weight: 600;
        color: #555;
        font-size: 13px;
        display: block;
        margin-top: 10px;
    }
    .modal-content .detalle-materia .valor {
        color: #2c3e50;
        font-size: 15px;
        padding: 5px 0;
    }
    .modal-content .detalle-materia .temas-modal {
        margin-top: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    .modal-content .detalle-materia .temas-modal .tema-modal-item {
        padding: 5px 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
        color: #555;
    }
    .modal-content .detalle-materia .temas-modal .tema-modal-item:last-child {
        border-bottom: none;
    }
    .modal-content .detalle-materia .temas-modal .tema-modal-item i {
        color: #3498db;
        margin-right: 8px;
    }
    .modal-content .btn-inscribir-modal {
        background: #2ecc71;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        cursor: pointer;
        font-weight: 600;
        font-size: 16px;
        transition: background 0.3s;
        width: 100%;
        margin-top: 15px;
    }
    .modal-content .btn-inscribir-modal:hover {
        background: #27ae60;
    }
    .modal-content .btn-inscribir-modal i {
        margin-right: 8px;
    }
    
    /* ============================================= */
    /* MODAL DE CONFIRMACIÓN PARA INSCRIPCIÓN */
    /* ============================================= */
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
        padding: 20px;
    }
    .modal-confirm.show {
        display: flex;
    }
    .modal-confirm .modal-content {
        background: white;
        border-radius: 15px;
        max-width: 420px;
        width: 100%;
        padding: 30px;
        text-align: center;
        animation: modalIn 0.3s ease;
    }
    .modal-confirm .modal-content .icono-pregunta {
        font-size: 48px;
        color: #f39c12;
        display: block;
        margin-bottom: 15px;
    }
    .modal-confirm .modal-content h3 {
        color: #2c3e50;
        margin-bottom: 10px;
    }
    .modal-confirm .modal-content p {
        color: #666;
        margin-bottom: 20px;
    }
    .modal-confirm .modal-content .btn-group {
        display: flex;
        gap: 10px;
    }
    .modal-confirm .modal-content .btn-group button {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        transition: background 0.3s;
    }
    .modal-confirm .modal-content .btn-confirmar-inscripcion {
        background: #2ecc71;
        color: white;
    }
    .modal-confirm .modal-content .btn-confirmar-inscripcion:hover {
        background: #27ae60;
    }
    .modal-confirm .modal-content .btn-cancelar-inscripcion {
        background: #95a5a6;
        color: white;
    }
    .modal-confirm .modal-content .btn-cancelar-inscripcion:hover {
        background: #7f8c8d;
    }
    
    /* ============================================= */
    /* MODAL DE CONFIRMACIÓN PARA BAJA */
    /* ============================================= */
    .modal-confirm-baja .icono-warning {
        font-size: 48px;
        color: #e74c3c;
        display: block;
        margin-bottom: 15px;
    }
    .modal-confirm-baja .btn-confirmar-baja {
        background: #e74c3c;
        color: white;
    }
    .modal-confirm-baja .btn-confirmar-baja:hover {
        background: #c0392b;
    }
    .modal-confirm-baja .btn-cancelar-baja {
        background: #95a5a6;
        color: white;
    }
    .modal-confirm-baja .btn-cancelar-baja:hover {
        background: #7f8c8d;
    }
    
    @media (max-width: 768px) {
        .materias-disponibles-grid,
        .materias-inscritas-grid {
            grid-template-columns: 1fr;
        }
        .modal-content {
            padding: 20px;
        }
        .modal-confirm .modal-content .btn-group {
            flex-direction: column;
        }
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
<!-- SECCIÓN 1: MATERIAS DISPONIBLES PARA INSCRIBIRSE -->
<!-- ============================================= -->
<?php if (!empty($materias_disponibles)): ?>
    <div style="margin-bottom: 30px;">
        <h4 style="color: #2c3e50; margin-bottom: 10px;">
            <i class="fas fa-plus-circle" style="color: #2ecc71;"></i> Materias Disponibles
            <span style="font-size: 13px; color: #999; font-weight: normal; margin-left: 10px;">
                (<?php echo count($materias_disponibles); ?> disponibles)
            </span>
        </h4>
        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
            Haz clic en una materia para ver más detalles e inscribirte.
        </p>
        <div class="materias-disponibles-grid">
            <?php foreach ($materias_disponibles as $materia): ?>
                <div class="materia-disponible-card" onclick="abrirModalInscripcion(<?php echo $materia['id']; ?>)">
                    <div class="nombre"><?php echo htmlspecialchars($materia['nombre']); ?></div>
                    <div class="profesor">
                        <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($materia['profesor']); ?>
                    </div>
                    <div class="correo">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($materia['profesor_correo']); ?>
                    </div>
                    <div class="descripcion"><?php echo htmlspecialchars($materia['descripcion']); ?></div>
                    <span class="badge-disponible"><i class="fas fa-check-circle"></i> Disponible</span>
                    <button class="btn-inscribir" onclick="event.stopPropagation(); abrirModalInscripcion(<?php echo $materia['id']; ?>)">
                        <i class="fas fa-user-plus"></i> Inscribirse
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================= -->
<!-- SECCIÓN 2: MATERIAS INSCRITAS -->
<!-- ============================================= -->
<h4 style="color: #2c3e50; margin-bottom: 10px;">
    <i class="fas fa-book" style="color: #3498db;"></i> Mis Materias Inscritas
    <span style="font-size: 13px; color: #999; font-weight: normal; margin-left: 10px;">
        (<?php echo count($materias_inscritas); ?> inscritas)
    </span>
</h4>

<?php if (empty($materias_inscritas) && empty($materias_disponibles)): ?>
    <div class="sin-materias">
        <i class="fas fa-book-open"></i>
        <p>No hay materias disponibles en este momento</p>
        <p style="font-size: 14px;">Contacta a tu profesor para más información</p>
    </div>
<?php elseif (empty($materias_inscritas)): ?>
    <div class="sin-materias">
        <i class="fas fa-user-plus"></i>
        <p>No estás inscrito en ninguna materia</p>
        <p style="font-size: 14px;">Selecciona una materia de la lista de arriba para inscribirte</p>
    </div>
<?php else: ?>
    <div class="materias-inscritas-grid">
        <?php foreach ($materias_inscritas as $materia): 
            $total = $materia['total_temas'];
            $completados = $materia['temas_completados'];
            $promedio = round($materia['promedio_progreso'] ?? 0, 0);
            $porcentaje = $total > 0 ? round(($completados / $total) * 100) : 0;
            
            // Obtener temas de la materia
            $temas_materia_inscrita = [];
            try {
                $stmt = $pdo->prepare("SELECT nombre, orden FROM Temas WHERE id_materia = ? ORDER BY orden");
                $stmt->execute([$materia['id']]);
                $temas_materia_inscrita = $stmt->fetchAll();
            } catch (PDOException $e) {
                $temas_materia_inscrita = [];
            }
        ?>
            <div class="materia-inscrita-card">
                <div class="header">
                    <h4><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                    <span class="badge-activa">
                        <i class="fas fa-check-circle"></i> Activa
                    </span>
                </div>
                <div class="profesor-info">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($materia['profesor']); ?>
                    <span style="color: #999; font-size: 13px; margin-left: 10px;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($materia['profesor_correo']); ?>
                    </span>
                </div>
                
                <!-- Lista de temas -->
                <div class="temas-list">
                    <?php if (empty($temas_materia_inscrita)): ?>
                        <div class="sin-temas">No hay temas creados aún</div>
                    <?php else: ?>
                        <?php foreach ($temas_materia_inscrita as $tema): ?>
                            <div class="tema-item">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($tema['orden'] . '. ' . $tema['nombre']); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="progreso-bar">
                    <div class="fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                </div>
                <div class="progreso-texto">
                    Progreso: <?php echo $porcentaje; ?>% (<?php echo $completados . '/' . $total; ?> temas completados)
                    <?php if ($promedio > 0 && $porcentaje < 100): ?>
                        <span style="font-size: 11px; color: #999; display: block;">
                            Promedio de avance: <?php echo $promedio; ?>%
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="actions">
                    <a href="material-estudio.php?materia_id=<?php echo $materia['id']; ?>" class="btn-sm btn-material">
                        <i class="fas fa-play"></i> Material
                    </a>
                    <a href="evaluaciones.php?materia_id=<?php echo $materia['id']; ?>" class="btn-sm btn-evaluacion">
                        <i class="fas fa-tasks"></i> Evaluaciones
                    </a>
                    <button class="btn-sm btn-ver-materia" onclick="abrirModalInscripcion(<?php echo $materia['id']; ?>)">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                    <button class="btn-sm btn-baja-materia" onclick="abrirModalBaja(<?php echo $materia['id']; ?>, '<?php echo addslashes($materia['nombre']); ?>')">
                        <i class="fas fa-trash"></i> Dar de Baja
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============================================= -->
<!-- MODAL DE INSCRIPCIÓN / VER DETALLE -->
<!-- ============================================= -->
<div class="modal-overlay" id="modalInscripcion">
    <div class="modal-content">
        <div class="modal-header">
            <button class="btn-volver" onclick="cerrarModalInscripcion()" title="Volver atrás">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 id="modalTitulo">Detalle de la Materia</h3>
            <button class="btn-close-modal" onclick="cerrarModalInscripcion()" title="Cerrar">
                &times;
            </button>
        </div>
        <div id="modalDetalleContenido">
            <!-- Se llena con JavaScript -->
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DE CONFIRMACIÓN PARA INSCRIPCIÓN -->
<!-- ============================================= -->
<div class="modal-confirm" id="modalConfirmarInscripcion">
    <div class="modal-content">
        <span class="icono-pregunta"><i class="fas fa-question-circle"></i></span>
        <h3>¿Inscribirse en esta materia?</h3>
        <p id="mensajeConfirmarInscripcion">¿Estás seguro de que deseas inscribirte en esta materia?</p>
        <div class="btn-group">
            <button class="btn-cancelar-inscripcion" onclick="cerrarModalConfirmarInscripcion()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button class="btn-confirmar-inscripcion" id="btnConfirmarInscripcion">
                <i class="fas fa-check"></i> Sí, Inscribirme
            </button>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DE CONFIRMACIÓN PARA DAR DE BAJA -->
<!-- ============================================= -->
<div class="modal-confirm modal-confirm-baja" id="modalBaja">
    <div class="modal-content">
        <span class="icono-warning"><i class="fas fa-exclamation-triangle"></i></span>
        <h3>¿Dar de baja la materia?</h3>
        <p id="mensajeBaja">¿Estás seguro de que deseas dar de baja esta materia? Perderás el acceso a todo su contenido.</p>
        <div class="btn-group">
            <button class="btn-cancelar-baja" onclick="cerrarModalBaja()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button class="btn-confirmar-baja" id="btnConfirmarBaja">
                <i class="fas fa-trash"></i> Sí, Dar de Baja
            </button>
        </div>
    </div>
</div>

<script>
// =============================================
// MODAL DE INSCRIPCIÓN / VER DETALLE
// =============================================
function abrirModalInscripcion(materiaId) {
    var modal = document.getElementById('modalInscripcion');
    var contenido = document.getElementById('modalDetalleContenido');
    var titulo = document.getElementById('modalTitulo');
    
    // Mostrar loading
    contenido.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>';
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Obtener datos via AJAX
    fetch('ajax_get_materia_detalle.php?id=' + materiaId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var m = data.materia;
                var temas = data.temas || [];
                
                titulo.textContent = m.nombre;
                
                var temasHtml = '';
                if (temas.length > 0) {
                    temasHtml = '<div class="temas-modal">';
                    temas.forEach(function(tema) {
                        temasHtml += `
                            <div class="tema-modal-item">
                                <i class="fas fa-tag"></i> ${tema.orden}. ${tema.nombre}
                                ${tema.descripcion ? `<br><small style="color: #999; margin-left: 22px;">${tema.descripcion}</small>` : ''}
                            </div>
                        `;
                    });
                    temasHtml += '</div>';
                } else {
                    temasHtml = '<p style="color: #999; font-style: italic; margin-top: 10px;">No hay temas creados aún</p>';
                }
                
                contenido.innerHTML = `
                    <div class="detalle-materia">
                        <span class="label"><i class="fas fa-book"></i> Nombre</span>
                        <div class="valor">${m.nombre}</div>
                        
                        <span class="label"><i class="fas fa-align-left"></i> Descripción</span>
                        <div class="valor">${m.descripcion || 'Sin descripción'}</div>
                        
                        <span class="label"><i class="fas fa-user-tie"></i> Profesor</span>
                        <div class="valor">${m.profesor}</div>
                        
                        <span class="label"><i class="fas fa-envelope"></i> Correo del profesor</span>
                        <div class="valor">${m.profesor_correo}</div>
                        
                        <span class="label"><i class="fas fa-list"></i> Temas (${temas.length})</span>
                        ${temasHtml}
                        
                        <span class="label"><i class="fas fa-calendar"></i> Creada</span>
                        <div class="valor">${new Date(m.fecha_creacion).toLocaleDateString('es-ES')}</div>
                    </div>
                    ${m.inscrito ? 
                        `<div style="margin-top: 15px; padding: 12px; background: #d4edda; border-radius: 8px; text-align: center; color: #155724;">
                            <i class="fas fa-check-circle"></i> Ya estás inscrito en esta materia
                        </div>` :
                        `<button class="btn-inscribir-modal" onclick="abrirModalConfirmarInscripcion(${m.id}, '${m.nombre}')">
                            <i class="fas fa-user-plus"></i> Inscribirse en esta materia
                        </button>`
                    }
                `;
            } else {
                contenido.innerHTML = `
                    <div style="text-align: center; padding: 30px; color: #e74c3c;">
                        <i class="fas fa-exclamation-circle" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                        <p>Error al cargar los detalles de la materia</p>
                        <p style="font-size: 13px; color: #999;">${data.message || 'Intenta de nuevo'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            contenido.innerHTML = `
                <div style="text-align: center; padding: 30px; color: #e74c3c;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                    <p>Error de conexión</p>
                </div>
            `;
        });
}

function cerrarModalInscripcion() {
    document.getElementById('modalInscripcion').classList.remove('show');
    document.body.style.overflow = '';
}

// =============================================
// MODAL DE CONFIRMACIÓN PARA INSCRIPCIÓN
// =============================================
var materiaInscripcionId = 0;

function abrirModalConfirmarInscripcion(materiaId, nombre) {
    materiaInscripcionId = materiaId;
    document.getElementById('mensajeConfirmarInscripcion').textContent = 
        '¿Estás seguro de que deseas inscribirte en la materia "' + nombre + '"?';
    document.getElementById('modalConfirmarInscripcion').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarModalConfirmarInscripcion() {
    document.getElementById('modalConfirmarInscripcion').classList.remove('show');
    document.body.style.overflow = '';
    materiaInscripcionId = 0;
}

document.getElementById('btnConfirmarInscripcion').addEventListener('click', function() {
    if (materiaInscripcionId > 0) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        form.innerHTML = `
            <input type="hidden" name="action" value="inscribir">
            <input type="hidden" name="materia_id" value="${materiaInscripcionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
});

// =============================================
// MODAL DE CONFIRMACIÓN PARA BAJA
// =============================================
var materiaBajaId = 0;

function abrirModalBaja(materiaId, nombre) {
    materiaBajaId = materiaId;
    document.getElementById('mensajeBaja').textContent = 
        '¿Estás seguro de que deseas dar de baja la materia "' + nombre + '"? Perderás el acceso a todo su contenido.';
    document.getElementById('modalBaja').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarModalBaja() {
    document.getElementById('modalBaja').classList.remove('show');
    document.body.style.overflow = '';
    materiaBajaId = 0;
}

document.getElementById('btnConfirmarBaja').addEventListener('click', function() {
    if (materiaBajaId > 0) {
        window.location.href = 'mis-materias.php?delete=confirm&id=' + materiaBajaId;
    }
});

// =============================================
// CERRAR MODALES AL HACER CLIC FUERA
// =============================================
document.getElementById('modalInscripcion').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalInscripcion();
    }
});

document.getElementById('modalConfirmarInscripcion').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalConfirmarInscripcion();
    }
});

document.getElementById('modalBaja').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalBaja();
    }
});

// =============================================
// CERRAR CON ESC
// =============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalInscripcion();
        cerrarModalConfirmarInscripcion();
        cerrarModalBaja();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>