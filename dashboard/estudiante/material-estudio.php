<?php
// =============================================
// 1. PRIMERO: Configuración y procesamiento (ANTES del header)
// =============================================
$page_title = 'Material de Estudio';
$page_icon = 'video';

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
$tema_id = $_GET['tema_id'] ?? 0;

// =============================================
// 2. PROCESAR ACCIÓN DE MATERIAL (marcar como visto) - ANTES DEL HEADER
// =============================================
if (isset($_GET['action']) && $_GET['action'] === 'ver' && isset($_GET['material_id'])) {
    $material_id = $_GET['material_id'];
    $materia_id_redirect = $_GET['materia_id'] ?? 0;
    $tema_id_redirect = $_GET['tema_id'] ?? 0;
    
    try {
        // Obtener información del material
        $stmt = $pdo->prepare("
            SELECT me.*, t.id as tema_id 
            FROM MaterialEstudio me
            JOIN Temas t ON me.id_tema = t.id
            WHERE me.id = ?
        ");
        $stmt->execute([$material_id]);
        $material = $stmt->fetch();
        
        if ($material) {
            // Determinar qué campo actualizar
            $campo = 'material_revisado'; // Por defecto
            if ($material['tipo'] == 'video') {
                $campo = 'video_visto';
            }
            
            // Verificar si ya existe registro de progreso
            $stmt = $pdo->prepare("
                SELECT id FROM Progreso WHERE id_usuario = ? AND id_tema = ?
            ");
            $stmt->execute([$usuario_id, $material['tema_id']]);
            $existe = $stmt->fetch();
            
            if ($existe) {
                // Actualizar existente
                $stmt = $pdo->prepare("
                    UPDATE Progreso 
                    SET $campo = 1, fecha_actualizacion = NOW()
                    WHERE id_usuario = ? AND id_tema = ?
                ");
                $stmt->execute([$usuario_id, $material['tema_id']]);
            } else {
                // Insertar nuevo
                $video = ($campo == 'video_visto') ? 1 : 0;
                $material_revisado = ($campo == 'material_revisado') ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO Progreso (id_usuario, id_tema, video_visto, material_revisado, evaluacion_completada)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmt->execute([$usuario_id, $material['tema_id'], $video, $material_revisado]);
            }
            
            // Redirigir al material correspondiente
            if (!empty($material['url'])) {
                // Redirigir a la URL externa
                header('Location: ' . $material['url']);
            } elseif (!empty($material['archivo_ruta'])) {
                // Redirigir al archivo
                header('Location: ../../' . $material['archivo_ruta']);
            } else {
                // Volver a la página de materiales
                header('Location: material-estudio.php?materia_id=' . $materia_id_redirect . '&tema_id=' . $tema_id_redirect);
            }
            exit();
        }
    } catch (PDOException $e) {
        // Si hay error, redirigir de vuelta
        header('Location: material-estudio.php?materia_id=' . $materia_id . '&tema_id=' . $tema_id);
        exit();
    }
}

// =============================================
// 3. OBTENER DATOS
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
    
    // Obtener temas de la materia seleccionada
    if ($materia_id > 0) {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM MaterialEstudio WHERE id_tema = t.id) as total_materiales
            FROM Temas t
            WHERE t.id_materia = ?
            ORDER BY t.orden
        ");
        $stmt->execute([$materia_id]);
        $temas = $stmt->fetchAll();
    } else {
        $temas = [];
    }
    
    // Obtener materiales del tema seleccionado
    if ($tema_id > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM MaterialEstudio 
            WHERE id_tema = ?
            ORDER BY orden
        ");
        $stmt->execute([$tema_id]);
        $materiales = $stmt->fetchAll();
    } else {
        $materiales = [];
    }
    
} catch (PDOException $e) {
    $materias = [];
    $temas = [];
    $materiales = [];
    $_SESSION['error'] = 'Error al cargar datos';
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
    .material-container {
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
        transition: border-color 0.3s;
        background: white;
        cursor: pointer;
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
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 500;
    }
    
    .filtro-container form .btn-limpiar:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }

    /* ===== TEMA LIST ===== */
    .tema-list {
        background: white;
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    
    .tema-list h4 {
        margin-bottom: 10px;
        color: #2c3e50;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .tema-list h4 i {
        color: #3498db;
    }
    
    .tema-item {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        border-radius: 6px;
    }
    
    .tema-item:hover {
        background: #f8f9fa;
        transform: translateX(4px);
    }
    
    .tema-item:last-child {
        border-bottom: none;
    }
    
    .tema-item .nombre {
        font-weight: 500;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .tema-item .nombre i {
        color: #3498db;
        font-size: 14px;
    }
    
    .tema-item .nombre .completado-icon {
        color: #2ecc71;
    }
    
    .tema-item .badge-material {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .tema-item .badge-completado {
        background: #d4edda;
        color: #155724;
    }
    
    .tema-item.active {
        background: #e3f2fd;
        border-left: 3px solid #3498db;
        border-radius: 6px;
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

    /* ===== MATERIAL CARD ===== */
    .material-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        display: flex;
        gap: 20px;
        align-items: flex-start;
        flex-wrap: wrap;
        transition: all 0.3s;
        border-left: 3px solid transparent;
    }
    
    .material-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .material-card.visto {
        border-left-color: #2ecc71;
        background: #f8fff8;
    }
    
    .material-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        flex-shrink: 0;
    }
    
    .material-info {
        flex: 1;
        min-width: 200px;
    }
    
    .material-info h4 {
        margin: 0 0 5px 0;
        color: #2c3e50;
        font-size: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .material-info h4 .badge-visto {
        display: inline-block;
        background: #d4edda;
        color: #155724;
        padding: 1px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 500;
    }
    
    .material-info p {
        color: #666;
        margin: 5px 0;
        font-size: 14px;
    }
    
    .material-info .meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 8px;
        font-size: 13px;
        color: #999;
    }
    
    .material-info .meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .material-info .meta span i {
        color: #3498db;
        font-size: 13px;
    }
    
    .material-actions {
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: flex-end;
        min-width: 120px;
    }
    
    .btn-material {
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        width: 100%;
        transition: all 0.3s;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .btn-material:hover:not(:disabled) {
        transform: scale(1.02);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    .btn-material:disabled {
        opacity: 0.7;
        cursor: default;
        transform: none !important;
        box-shadow: none !important;
    }
    
    .btn-material.download {
        background: #2ecc71;
    }
    .btn-material.download:hover:not(:disabled) {
        background: #27ae60;
    }
    
    .btn-material.video {
        background: #e74c3c;
    }
    .btn-material.video:hover:not(:disabled) {
        background: #c0392b;
    }
    
    .btn-material.enlace {
        background: #f39c12;
    }
    .btn-material.enlace:hover:not(:disabled) {
        background: #e67e22;
    }
    
    .btn-material.visto {
        background: #95a5a6;
        cursor: default;
        opacity: 0.7;
    }
    .btn-material.visto:hover {
        transform: none;
        box-shadow: none;
    }
    
    .tamaño-archivo {
        font-size: 11px;
        color: #999;
        text-align: center;
        margin-top: 3px;
    }

    /* ===== TIPOS BADGE ===== */
    .tipo-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    .tipo-video {
        background: #fce4ec;
        color: #c62828;
    }
    .tipo-documento {
        background: #f3e5f5;
        color: #7b1fa2;
    }
    .tipo-presentacion {
        background: #fff3e0;
        color: #e65100;
    }
    .tipo-imagen {
        background: #e8f5e9;
        color: #2e7d32;
    }
    .tipo-enlace {
        background: #e3f2fd;
        color: #1976d2;
    }
    .tipo-otro {
        background: #eceff1;
        color: #546e7a;
    }

    /* ===== PROGRESO BAR ===== */
    .progreso-container {
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #3498db;
    }
    
    .progreso-container .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .progreso-container .header span {
        font-weight: 500;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .progreso-container .header span i {
        color: #3498db;
    }
    
    .progreso-container .header .porcentaje {
        font-weight: 600;
    }
    
    .progreso-container .barra {
        margin-top: 8px;
        background: #e0e0e0;
        border-radius: 5px;
        height: 8px;
        overflow: hidden;
    }
    
    .progreso-container .barra .fill {
        height: 100%;
        border-radius: 5px;
        transition: width 0.5s;
    }
    
    .progreso-container .detalles {
        margin-top: 5px;
        font-size: 12px;
        color: #999;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .progreso-container .detalles span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .progreso-container .detalles .check {
        color: #2ecc71;
    }
    .progreso-container .detalles .empty {
        color: #ccc;
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .material-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .material-card {
            padding: 16px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .material-container {
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
        
        .tema-item {
            padding: 8px 12px;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .tema-item .nombre {
            font-size: 14px;
        }
        
        .tema-item .badge-material {
            font-size: 11px;
        }
        
        .material-card {
            flex-direction: column;
            padding: 15px;
        }
        
        .material-icon {
            width: 50px;
            height: 50px;
            font-size: 22px;
        }
        
        .material-info h4 {
            font-size: 15px;
        }
        
        .material-info .meta {
            font-size: 12px;
            gap: 10px;
        }
        
        .material-actions {
            width: 100%;
            align-items: stretch;
        }
        
        .btn-material {
            width: 100%;
        }
        
        .progreso-container .header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .progreso-container .detalles {
            flex-direction: column;
            gap: 5px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .material-container {
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
        
        .tema-list {
            padding: 10px;
        }
        
        .tema-list h4 {
            font-size: 14px;
        }
        
        .tema-item {
            padding: 6px 10px;
        }
        
        .tema-item .nombre {
            font-size: 13px;
        }
        
        .tema-item .badge-material {
            font-size: 10px;
            padding: 1px 8px;
        }
        
        .material-card {
            padding: 12px;
            border-radius: 10px;
        }
        
        .material-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .material-info h4 {
            font-size: 14px;
        }
        
        .material-info h4 .badge-visto {
            font-size: 9px;
            padding: 0px 8px;
        }
        
        .material-info p {
            font-size: 13px;
        }
        
        .material-info .meta {
            font-size: 11px;
            gap: 8px;
        }
        
        .btn-material {
            font-size: 12px;
            padding: 6px 16px;
        }
        
        .progreso-container {
            padding: 12px;
        }
        
        .progreso-container .header span {
            font-size: 13px;
        }
        
        .progreso-container .header .porcentaje {
            font-size: 14px;
        }
        
        .progreso-container .detalles {
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
        .material-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .tema-item .nombre {
            font-size: 12px;
        }
        
        .material-info h4 {
            font-size: 13px;
        }
        
        .btn-material {
            font-size: 11px;
            padding: 5px 12px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .material-container {
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
            min-width: 150px;
            flex: 1;
        }
        
        .tema-list {
            padding: 10px;
        }
        
        .material-card {
            padding: 12px;
        }
        
        .progreso-container {
            padding: 10px;
        }
        
        .progreso-container .detalles {
            flex-direction: row;
            flex-wrap: wrap;
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

<div class="material-container">
    <h3 class="page-title"><i class="fas fa-play"></i> Material de Estudio</h3>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- FILTRO -->
    <div class="filtro-container">
        <form method="GET">
            <label for="materia_id">
                <i class="fas fa-book"></i> Materia:
            </label>
            <select name="materia_id" id="materia_id" onchange="this.form.submit()">
                <option value="">-- Seleccionar materia --</option>
                <?php foreach ($materias as $materia): ?>
                    <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($materia['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($materia_id > 0): ?>
                <a href="material-estudio.php" class="btn-limpiar">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($materia_id == 0): ?>
        <!-- ESTADO: SIN MATERIA SELECCIONADA -->
        <div class="empty-state">
            <i class="fas fa-hand-pointer"></i>
            <h4>Selecciona una materia</h4>
            <p>Elige una materia del menú desplegable para ver su material de estudio</p>
        </div>
    <?php elseif (empty($temas)): ?>
        <!-- ESTADO: MATERIA SIN TEMAS -->
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h4>Esta materia no tiene temas</h4>
            <p>El profesor aún no ha creado temas para esta materia</p>
        </div>
    <?php else: ?>
        <!-- LISTA DE TEMAS -->
        <div class="tema-list">
            <h4><i class="fas fa-list"></i> Temas</h4>
            <?php foreach ($temas as $tema): 
                // Verificar si el estudiante ya completó este tema (progreso >= 33.33%)
                $tema_completado = false;
                try {
                    $stmt = $pdo->prepare("
                        SELECT porcentaje FROM Progreso 
                        WHERE id_usuario = ? AND id_tema = ?
                    ");
                    $stmt->execute([$usuario_id, $tema['id']]);
                    $progreso = $stmt->fetch();
                    if ($progreso && $progreso['porcentaje'] >= 33.33) {
                        $tema_completado = true;
                    }
                } catch (PDOException $e) {
                    $tema_completado = false;
                }
            ?>
                <a href="material-estudio.php?materia_id=<?php echo $materia_id; ?>&tema_id=<?php echo $tema['id']; ?>" 
                   class="tema-item <?php echo $tema_id == $tema['id'] ? 'active' : ''; ?>">
                    <span class="nombre">
                        <?php if ($tema_completado): ?>
                            <i class="fas fa-check-circle completado-icon"></i>
                        <?php else: ?>
                            <i class="fas fa-circle" style="color: #ccc; font-size: 12px;"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($tema['nombre']); ?>
                    </span>
                    <span class="badge-material <?php echo $tema_completado ? 'badge-completado' : ''; ?>">
                        <i class="fas fa-file-alt"></i> <?php echo $tema['total_materiales']; ?> materiales
                        <?php if ($tema_completado): ?>
                            <i class="fas fa-check-circle" style="margin-left: 5px;"></i>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- MATERIALES DEL TEMA SELECCIONADO -->
        <?php if ($tema_id > 0): ?>
            <?php if (empty($materiales)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <i class="fas fa-file-alt" style="font-size: 48px;"></i>
                    <h4 style="font-size: 17px;">Este tema no tiene materiales</h4>
                    <p>El profesor aún no ha agregado materiales a este tema</p>
                </div>
            <?php else: ?>
                <h4 style="margin: 20px 0 15px 0; color: #2c3e50; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-alt" style="color: #3498db;"></i> Materiales disponibles
                </h4>
                
                <?php foreach ($materiales as $material): 
                    // Verificar si el estudiante ya vio este material
                    $ya_visto = false;
                    try {
                        $stmt = $pdo->prepare("
                            SELECT id FROM Progreso 
                            WHERE id_usuario = ? AND id_tema = ?
                        ");
                        $stmt->execute([$usuario_id, $tema_id]);
                        $progreso = $stmt->fetch();
                        
                        if ($progreso) {
                            if ($material['tipo'] == 'video') {
                                $stmt2 = $pdo->prepare("SELECT video_visto FROM Progreso WHERE id_usuario = ? AND id_tema = ?");
                                $stmt2->execute([$usuario_id, $tema_id]);
                                $p = $stmt2->fetch();
                                if ($p && $p['video_visto'] == 1) $ya_visto = true;
                            } else {
                                $stmt2 = $pdo->prepare("SELECT material_revisado FROM Progreso WHERE id_usuario = ? AND id_tema = ?");
                                $stmt2->execute([$usuario_id, $tema_id]);
                                $p = $stmt2->fetch();
                                if ($p && $p['material_revisado'] == 1) $ya_visto = true;
                            }
                        }
                    } catch (PDOException $e) {
                        $ya_visto = false;
                    }
                    
                    $iconos = [
                        'video' => 'play-circle',
                        'documento' => 'file-pdf',
                        'presentacion' => 'file-powerpoint',
                        'imagen' => 'image',
                        'enlace' => 'link',
                        'otro' => 'file'
                    ];
                    $colores_fondo = [
                        'video' => '#fce4ec',
                        'documento' => '#f3e5f5',
                        'presentacion' => '#fff3e0',
                        'imagen' => '#e8f5e9',
                        'enlace' => '#e3f2fd',
                        'otro' => '#eceff1'
                    ];
                    $colores_texto = [
                        'video' => '#c62828',
                        'documento' => '#7b1fa2',
                        'presentacion' => '#e65100',
                        'imagen' => '#2e7d32',
                        'enlace' => '#1976d2',
                        'otro' => '#546e7a'
                    ];
                    $tipo_clase = [
                        'video' => 'tipo-video',
                        'documento' => 'tipo-documento',
                        'presentacion' => 'tipo-presentacion',
                        'imagen' => 'tipo-imagen',
                        'enlace' => 'tipo-enlace',
                        'otro' => 'tipo-otro'
                    ];
                    
                    $icono = $iconos[$material['tipo']] ?? 'file';
                    $color_fondo = $colores_fondo[$material['tipo']] ?? '#eceff1';
                    $color_texto = $colores_texto[$material['tipo']] ?? '#546e7a';
                    $tipo_clase_texto = $tipo_clase[$material['tipo']] ?? 'tipo-otro';
                    $clase_visto = $ya_visto ? 'visto' : '';
                    $btn_clase = $ya_visto ? 'visto' : '';
                    $btn_texto = $ya_visto ? '✅ Visto' : 'Ver';
                ?>
                    <div class="material-card <?php echo $clase_visto; ?>">
                        <div class="material-icon" style="background: <?php echo $color_fondo; ?>; color: <?php echo $color_texto; ?>;">
                            <i class="fas fa-<?php echo $icono; ?>"></i>
                        </div>
                        <div class="material-info">
                            <h4>
                                <?php echo htmlspecialchars($material['titulo']); ?>
                                <?php if ($ya_visto): ?>
                                    <span class="badge-visto"><i class="fas fa-check-circle"></i> Visto</span>
                                <?php endif; ?>
                            </h4>
                            <p><?php echo htmlspecialchars($material['descripcion']); ?></p>
                            <div class="meta">
                                <span><span class="tipo-badge <?php echo $tipo_clase_texto; ?>"><?php echo ucfirst($material['tipo']); ?></span></span>
                                <?php if ($material['duracion_minutos']): ?>
                                    <span><i class="fas fa-clock"></i> <?php echo $material['duracion_minutos']; ?> min</span>
                                <?php endif; ?>
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($material['fecha_subida'])); ?></span>
                                <?php if ($material['archivo_tamano']): ?>
                                    <span><i class="fas fa-weight"></i> <?php echo number_format($material['archivo_tamano'] / 1024 / 1024, 2); ?> MB</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="material-actions">
                            <?php if (!empty($material['url'])): ?>
                                <?php if ($material['tipo'] == 'video'): ?>
                                    <a href="material-estudio.php?action=ver&material_id=<?php echo $material['id']; ?>&materia_id=<?php echo $materia_id; ?>&tema_id=<?php echo $tema_id; ?>" class="btn-material video <?php echo $btn_clase; ?>" <?php echo $ya_visto ? 'disabled' : ''; ?>>
                                        <i class="fas fa-play"></i>
                                        <?php echo $btn_texto; ?>
                                    </a>
                                <?php else: ?>
                                    <a href="material-estudio.php?action=ver&material_id=<?php echo $material['id']; ?>&materia_id=<?php echo $materia_id; ?>&tema_id=<?php echo $tema_id; ?>" class="btn-material enlace <?php echo $btn_clase; ?>" <?php echo $ya_visto ? 'disabled' : ''; ?>>
                                        <i class="fas fa-external-link-alt"></i>
                                        <?php echo $btn_texto; ?>
                                    </a>
                                <?php endif; ?>
                            <?php elseif (!empty($material['archivo_ruta'])): ?>
                                <a href="material-estudio.php?action=ver&material_id=<?php echo $material['id']; ?>&materia_id=<?php echo $materia_id; ?>&tema_id=<?php echo $tema_id; ?>" class="btn-material download <?php echo $btn_clase; ?>" <?php echo $ya_visto ? 'disabled' : ''; ?>>
                                    <i class="fas fa-download"></i>
                                    <?php echo $btn_texto; ?>
                                </a>
                                <?php if ($material['archivo_tamano']): ?>
                                    <div class="tamaño-archivo">
                                        <?php echo number_format($material['archivo_tamano'] / 1024 / 1024, 2); ?> MB
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999; font-size: 12px;">Sin contenido disponible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- PROGRESO DEL TEMA -->
                <?php 
                $progreso_tema = 0;
                try {
                    $stmt = $pdo->prepare("SELECT porcentaje FROM Progreso WHERE id_usuario = ? AND id_tema = ?");
                    $stmt->execute([$usuario_id, $tema_id]);
                    $p = $stmt->fetch();
                    if ($p) {
                        $progreso_tema = round($p['porcentaje'], 0);
                    }
                } catch (PDOException $e) {
                    $progreso_tema = 0;
                }
                
                $color_progreso = $progreso_tema >= 80 ? '#2ecc71' : ($progreso_tema >= 50 ? '#f39c12' : '#e74c3c');
                ?>
                <div class="progreso-container">
                    <div class="header">
                        <span><i class="fas fa-chart-line"></i> Progreso de este tema</span>
                        <span class="porcentaje" style="color: <?php echo $color_progreso; ?>;">
                            <?php echo $progreso_tema; ?>%
                        </span>
                    </div>
                    <div class="barra">
                        <div class="fill" style="width: <?php echo $progreso_tema; ?>%; background: linear-gradient(90deg, #3498db, #2ecc71);"></div>
                    </div>
                    <div class="detalles">
                        <span><span class="<?php echo $progreso_tema >= 33.33 ? 'check' : 'empty'; ?>"><?php echo $progreso_tema >= 33.33 ? '✅' : '⬜'; ?></span> Video: <?php echo $progreso_tema >= 33.33 ? 'Visto' : 'Pendiente'; ?></span>
                        <span><span class="<?php echo $progreso_tema >= 66.67 ? 'check' : 'empty'; ?>"><?php echo $progreso_tema >= 66.67 ? '✅' : '⬜'; ?></span> Material: <?php echo $progreso_tema >= 66.67 ? 'Revisado' : 'Pendiente'; ?></span>
                        <span><span class="<?php echo $progreso_tema >= 100 ? 'check' : 'empty'; ?>"><?php echo $progreso_tema >= 100 ? '✅' : '⬜'; ?></span> Evaluación: <?php echo $progreso_tema >= 100 ? 'Completada' : 'Pendiente'; ?></span>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state" style="padding: 30px;">
                <i class="fas fa-hand-pointer" style="font-size: 48px;"></i>
                <h4 style="font-size: 17px;">Selecciona un tema</h4>
                <p>Elige un tema de la lista para ver sus materiales</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>