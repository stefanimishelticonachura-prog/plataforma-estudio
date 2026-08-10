<?php
$page_title = 'Mi Progreso';
$page_icon = 'chart-line';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

$materia_id = $_GET['materia_id'] ?? 0;

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
    
    // Obtener progreso por materia
    if ($materia_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.nombre,
                t.orden,
                p.video_visto,
                p.material_revisado,
                p.evaluacion_completada,
                p.porcentaje,
                (SELECT COUNT(*) FROM MaterialEstudio WHERE id_tema = t.id) as total_materiales
            FROM Temas t
            LEFT JOIN Progreso p ON t.id = p.id_tema AND p.id_usuario = ?
            WHERE t.id_materia = ?
            ORDER BY t.orden
        ");
        $stmt->execute([$usuario_id, $materia_id]);
        $progreso_temas = $stmt->fetchAll();
        
        // Calcular progreso total de la materia
        $total_temas = count($progreso_temas);
        $temas_completados = 0;
        foreach ($progreso_temas as $tema) {
            if ($tema['porcentaje'] >= 100) {
                $temas_completados++;
            }
        }
        $progreso_total = $total_temas > 0 ? round(($temas_completados / $total_temas) * 100) : 0;
    } else {
        $progreso_temas = [];
        $progreso_total = 0;
    }
    
} catch (PDOException $e) {
    $materias = [];
    $progreso_temas = [];
    $progreso_total = 0;
    $_SESSION['error'] = 'Error al cargar progreso';
}
?>

<style>
    /* ===== RESET & BASE ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* ===== CONTENEDOR PRINCIPAL ===== */
    .progreso-container {
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

    /* ===== PROGRESO GENERAL ===== */
    .progreso-general {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        transition: all 0.3s;
    }
    
    .progreso-general:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .progreso-general .numero {
        font-size: 48px;
        font-weight: bold;
        color: #2c3e50;
        text-align: center;
        line-height: 1.2;
    }
    
    .progreso-general .numero .icono {
        font-size: 32px;
        margin-right: 10px;
    }
    
    .progreso-general .label {
        text-align: center;
        color: #666;
        font-size: 16px;
        margin-top: 5px;
        font-weight: 500;
    }
    
    .progreso-general .barra {
        margin-top: 15px;
        background: #f0f0f0;
        border-radius: 10px;
        height: 24px;
        overflow: hidden;
        position: relative;
    }
    
    .progreso-general .barra .fill {
        height: 100%;
        background: linear-gradient(90deg, #3498db, #2ecc71);
        transition: width 0.8s ease;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 13px;
        font-weight: 600;
        min-width: 40px;
    }
    
    .progreso-general .stats {
        display: flex;
        justify-content: space-between;
        margin-top: 12px;
        font-size: 14px;
        color: #666;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .progreso-general .stats span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .progreso-general .stats i {
        color: #3498db;
    }

    /* ===== SECCIÓN TÍTULO ===== */
    .section-title {
        margin: 25px 0 15px 0;
        color: #2c3e50;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        color: #3498db;
    }

    /* ===== TEMA PROGRESO ===== */
    .tema-progreso {
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
        border-left: 3px solid transparent;
    }
    
    .tema-progreso:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transform: translateX(4px);
    }
    
    .tema-progreso.completado {
        border-left-color: #2ecc71;
        background: #f8fff8;
    }
    
    .tema-progreso .info {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
        flex: 1;
    }
    
    .tema-progreso .info .nombre {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .tema-progreso .info .nombre i {
        font-size: 16px;
    }
    
    .tema-progreso .info .nombre i.completado-icon {
        color: #2ecc71;
    }
    
    .tema-progreso .info .nombre i.pendiente-icon {
        color: #ccc;
    }
    
    .tema-progreso .info .detalles {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #666;
        flex-wrap: wrap;
    }
    
    .tema-progreso .info .detalles span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .tema-progreso .info .detalles .completado {
        color: #2ecc71;
    }
    
    .tema-progreso .info .detalles .pendiente {
        color: #e74c3c;
    }
    
    .tema-progreso .info .detalles i {
        font-size: 13px;
    }
    
    .tema-progreso .right-section {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-shrink: 0;
    }
    
    .tema-progreso .barra-mini {
        width: 100px;
        height: 8px;
        background: #f0f0f0;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .tema-progreso .barra-mini .fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.5s;
    }
    
    .tema-progreso .porcentaje {
        font-weight: 700;
        font-size: 18px;
        min-width: 55px;
        text-align: right;
    }
    
    .tema-progreso .porcentaje.completado {
        color: #2ecc71;
    }
    
    .tema-progreso .porcentaje.pendiente {
        color: #f39c12;
    }
    
    .tema-progreso .porcentaje.cero {
        color: #e74c3c;
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
        .progreso-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .progreso-general .numero {
            font-size: 40px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .progreso-container {
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
        
        .progreso-general {
            padding: 20px;
        }
        
        .progreso-general .numero {
            font-size: 36px;
        }
        
        .progreso-general .barra {
            height: 20px;
        }
        
        .progreso-general .barra .fill {
            font-size: 11px;
            min-width: 30px;
        }
        
        .progreso-general .stats {
            font-size: 13px;
            flex-direction: column;
            align-items: center;
        }
        
        .section-title {
            font-size: 16px;
            margin: 20px 0 12px 0;
        }
        
        .tema-progreso {
            padding: 12px 15px;
            flex-direction: column;
            align-items: stretch;
        }
        
        .tema-progreso .info {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .tema-progreso .info .nombre {
            font-size: 14px;
        }
        
        .tema-progreso .info .detalles {
            font-size: 12px;
            gap: 10px;
        }
        
        .tema-progreso .right-section {
            width: 100%;
            justify-content: space-between;
        }
        
        .tema-progreso .barra-mini {
            flex: 1;
            width: auto;
        }
        
        .tema-progreso .porcentaje {
            font-size: 16px;
            min-width: 45px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .progreso-container {
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
        
        .progreso-general {
            padding: 16px;
            border-radius: 10px;
        }
        
        .progreso-general .numero {
            font-size: 30px;
        }
        
        .progreso-general .label {
            font-size: 14px;
        }
        
        .progreso-general .barra {
            height: 16px;
            border-radius: 8px;
        }
        
        .progreso-general .barra .fill {
            font-size: 10px;
            min-width: 25px;
            border-radius: 8px;
        }
        
        .progreso-general .stats {
            font-size: 12px;
        }
        
        .section-title {
            font-size: 14px;
            margin: 15px 0 10px 0;
        }
        
        .tema-progreso {
            padding: 10px 12px;
            border-radius: 10px;
        }
        
        .tema-progreso .info .nombre {
            font-size: 13px;
        }
        
        .tema-progreso .info .detalles {
            font-size: 11px;
            gap: 8px;
        }
        
        .tema-progreso .info .detalles span {
            font-size: 11px;
        }
        
        .tema-progreso .barra-mini {
            height: 6px;
        }
        
        .tema-progreso .porcentaje {
            font-size: 14px;
            min-width: 40px;
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
        .progreso-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .progreso-general .numero {
            font-size: 26px;
        }
        
        .tema-progreso .info .nombre {
            font-size: 12px;
        }
        
        .tema-progreso .porcentaje {
            font-size: 13px;
            min-width: 35px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .progreso-container {
            padding: 10px;
        }
        
        .progreso-general {
            padding: 15px;
        }
        
        .progreso-general .numero {
            font-size: 30px;
        }
        
        .progreso-general .barra {
            height: 16px;
        }
        
        .tema-progreso {
            padding: 10px 15px;
        }
        
        .tema-progreso .info .detalles {
            font-size: 12px;
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

<div class="progreso-container">
    <h3 class="page-title"><i class="fas fa-chart-line"></i> Mi Progreso</h3>

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
                <a href="progreso.php" class="btn-limpiar">
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
            <p>Elige una materia del menú desplegable para ver tu progreso</p>
        </div>
    <?php elseif (empty($progreso_temas)): ?>
        <!-- ESTADO: MATERIA SIN TEMAS -->
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h4>Esta materia no tiene temas</h4>
            <p>El profesor aún no ha creado temas para esta materia</p>
        </div>
    <?php else: ?>
        <!-- PROGRESO GENERAL -->
        <div class="progreso-general">
            <div class="numero">
                <span class="icono">📊</span> <?php echo $progreso_total; ?>%
            </div>
            <div class="label">Progreso total de la materia</div>
            <div class="barra">
                <div class="fill" style="width: <?php echo $progreso_total; ?>%;">
                    <?php echo $progreso_total; ?>%
                </div>
            </div>
            <div class="stats">
                <span><i class="fas fa-check-circle" style="color: #2ecc71;"></i> Temas completados: <strong><?php echo $temas_completados; ?>/<?php echo $total_temas; ?></strong></span>
                <span><i class="fas fa-chart-line" style="color: #3498db;"></i> Progreso: <strong><?php echo $progreso_total; ?>%</strong></span>
                <span><i class="fas fa-clock" style="color: #f39c12;"></i> Pendientes: <strong><?php echo $total_temas - $temas_completados; ?></strong></span>
            </div>
        </div>
        
        <!-- PROGRESO POR TEMA -->
        <h4 class="section-title">
            <i class="fas fa-list"></i> Progreso por Tema
        </h4>
        
        <?php foreach ($progreso_temas as $tema): 
            $completado = $tema['porcentaje'] >= 100;
            $color = $completado ? '#2ecc71' : ($tema['porcentaje'] > 0 ? '#f39c12' : '#e74c3c');
            $clase_porcentaje = $completado ? 'completado' : ($tema['porcentaje'] > 0 ? 'pendiente' : 'cero');
        ?>
            <div class="tema-progreso <?php echo $completado ? 'completado' : ''; ?>">
                <div class="info">
                    <span class="nombre">
                        <i class="fas fa-<?php echo $completado ? 'check-circle' : 'circle'; ?> <?php echo $completado ? 'completado-icon' : 'pendiente-icon'; ?>"></i>
                        <?php echo htmlspecialchars($tema['nombre']); ?>
                    </span>
                    <div class="detalles">
                        <span class="<?php echo $tema['video_visto'] ? 'completado' : 'pendiente'; ?>">
                            <i class="fas fa-video"></i> <?php echo $tema['video_visto'] ? 'Visto' : 'Pendiente'; ?>
                        </span>
                        <span class="<?php echo $tema['material_revisado'] ? 'completado' : 'pendiente'; ?>">
                            <i class="fas fa-file-alt"></i> <?php echo $tema['material_revisado'] ? 'Revisado' : 'Pendiente'; ?>
                        </span>
                        <span class="<?php echo $tema['evaluacion_completada'] ? 'completado' : 'pendiente'; ?>">
                            <i class="fas fa-tasks"></i> <?php echo $tema['evaluacion_completada'] ? 'Completada' : 'Pendiente'; ?>
                        </span>
                    </div>
                </div>
                <div class="right-section">
                    <div class="barra-mini">
                        <div class="fill" style="width: <?php echo $tema['porcentaje']; ?>%; background: <?php echo $color; ?>;"></div>
                    </div>
                    <span class="porcentaje <?php echo $clase_porcentaje; ?>">
                        <?php echo $tema['porcentaje']; ?>%
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>