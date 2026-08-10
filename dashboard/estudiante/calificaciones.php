<?php
$page_title = 'Mis Calificaciones';
$page_icon = 'star';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

try {
    // Obtener todas las calificaciones del estudiante
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            e.titulo as evaluacion_titulo,
            e.puntaje_maximo,
            e.puntaje_aprobacion,
            t.nombre as tema_nombre,
            m.nombre as materia_nombre,
            m.id as materia_id
        FROM ResultadosEvaluacion r
        JOIN Evaluaciones e ON r.id_evaluacion = e.id
        JOIN Temas t ON e.id_tema = t.id
        JOIN Materias m ON t.id_materia = m.id
        WHERE r.id_usuario = ?
        ORDER BY r.fecha DESC
    ");
    $stmt->execute([$usuario_id]);
    $calificaciones = $stmt->fetchAll();
    
    // Calcular estadísticas
    $total_evaluaciones = count($calificaciones);
    $aprobadas = 0;
    $reprobadas = 0;
    $suma_puntajes = 0;
    $materias_calificaciones = [];
    
    foreach ($calificaciones as $cal) {
        if ($cal['aprobado']) {
            $aprobadas++;
        } else {
            $reprobadas++;
        }
        $suma_puntajes += $cal['puntaje_obtenido'];
        
        // Agrupar por materia
        if (!isset($materias_calificaciones[$cal['materia_id']])) {
            $materias_calificaciones[$cal['materia_id']] = [
                'nombre' => $cal['materia_nombre'],
                'total' => 0,
                'suma' => 0,
                'aprobadas' => 0
            ];
        }
        $materias_calificaciones[$cal['materia_id']]['total']++;
        $materias_calificaciones[$cal['materia_id']]['suma'] += $cal['puntaje_obtenido'];
        if ($cal['aprobado']) {
            $materias_calificaciones[$cal['materia_id']]['aprobadas']++;
        }
    }
    
    $promedio_general = $total_evaluaciones > 0 ? round($suma_puntajes / $total_evaluaciones, 2) : 0;
    
} catch (PDOException $e) {
    $calificaciones = [];
    $total_evaluaciones = 0;
    $aprobadas = 0;
    $reprobadas = 0;
    $promedio_general = 0;
    $materias_calificaciones = [];
    $_SESSION['error'] = 'Error al cargar calificaciones';
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
    .calificaciones-container {
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

    /* ===== STATS ===== */
    .stats-calificaciones {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-item {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        text-align: center;
        transition: all 0.3s;
    }
    
    .stat-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-item .numero {
        font-size: 32px;
        font-weight: bold;
        line-height: 1.2;
    }
    
    .stat-item .label {
        color: #666;
        font-size: 14px;
        margin-top: 5px;
    }
    
    .stat-item .numero.aprobado {
        color: #2ecc71;
    }
    
    .stat-item .numero.reprobado {
        color: #e74c3c;
    }
    
    .stat-item .numero.promedio {
        color: #3498db;
    }
    
    .stat-item .numero.total {
        color: #2c3e50;
    }
    
    .stat-item .icon-stat {
        font-size: 28px;
        margin-bottom: 8px;
        display: block;
    }
    .stat-item .icon-stat.aprobado { color: #2ecc71; }
    .stat-item .icon-stat.reprobado { color: #e74c3c; }
    .stat-item .icon-stat.promedio { color: #3498db; }
    .stat-item .icon-stat.total { color: #9b59b6; }

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

    /* ===== MATERIA RESUMEN ===== */
    .materia-resumen {
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        border-left: 4px solid #3498db;
        transition: all 0.3s;
    }
    
    .materia-resumen:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transform: translateX(4px);
    }
    
    .materia-resumen .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .materia-resumen .header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .materia-resumen .header h4 i {
        color: #3498db;
    }
    
    .materia-resumen .stats {
        display: flex;
        gap: 20px;
        margin-top: 10px;
        font-size: 14px;
        color: #666;
        flex-wrap: wrap;
    }
    
    .materia-resumen .stats strong {
        color: #2c3e50;
    }
    
    .materia-resumen .stats .stat-item-mini {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .materia-resumen .stats .stat-item-mini i {
        color: #3498db;
        font-size: 13px;
    }

    /* ===== CALIFICACIÓN ITEM ===== */
    .calificacion-item {
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
        border-left: 4px solid #3498db;
        transition: all 0.3s;
    }
    
    .calificacion-item:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transform: translateX(4px);
    }
    
    .calificacion-item.aprobado {
        border-left-color: #2ecc71;
    }
    
    .calificacion-item.reprobado {
        border-left-color: #e74c3c;
    }
    
    .calificacion-item .info {
        flex: 1;
        min-width: 0;
    }
    
    .calificacion-item .info .titulo {
        font-weight: 600;
        color: #2c3e50;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .calificacion-item .info .titulo .badge-estado {
        font-size: 11px;
        padding: 2px 10px;
        border-radius: 12px;
        font-weight: 500;
    }
    
    .calificacion-item .info .titulo .badge-estado.aprobado {
        background: #d4edda;
        color: #155724;
    }
    
    .calificacion-item .info .titulo .badge-estado.reprobado {
        background: #f8d7da;
        color: #721c24;
    }
    
    .calificacion-item .info .detalles {
        color: #666;
        font-size: 13px;
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .calificacion-item .info .detalles span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .calificacion-item .info .detalles span i {
        color: #3498db;
        font-size: 12px;
    }
    
    .calificacion-item .puntaje-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
    }
    
    .calificacion-item .puntaje {
        font-size: 22px;
        font-weight: bold;
        padding: 5px 18px;
        border-radius: 20px;
        min-width: 70px;
        text-align: center;
    }
    
    .calificacion-item .puntaje.aprobado {
        color: #2ecc71;
        background: #d4edda;
    }
    
    .calificacion-item .puntaje.reprobado {
        color: #e74c3c;
        background: #f8d7da;
    }
    
    .calificacion-item .puntaje-minimo {
        font-size: 11px;
        color: #999;
        margin-top: 3px;
        text-align: center;
    }

    /* ===== BADGES ===== */
    .badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-activo {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-inactivo {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
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
        .calificaciones-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .stats-calificaciones {
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
        }
        
        .stat-item .numero {
            font-size: 28px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .calificaciones-container {
            padding: 12px;
        }
        
        .page-title {
            font-size: 20px;
        }
        
        .stats-calificaciones {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-item {
            padding: 15px;
        }
        
        .stat-item .numero {
            font-size: 24px;
        }
        
        .stat-item .label {
            font-size: 12px;
        }
        
        .stat-item .icon-stat {
            font-size: 22px;
        }
        
        .section-title {
            font-size: 16px;
            margin: 20px 0 12px 0;
        }
        
        .materia-resumen {
            padding: 12px 15px;
        }
        
        .materia-resumen .header h4 {
            font-size: 14px;
        }
        
        .materia-resumen .stats {
            font-size: 13px;
            gap: 12px;
        }
        
        .calificacion-item {
            padding: 12px 15px;
            flex-direction: column;
            align-items: stretch;
        }
        
        .calificacion-item .info .titulo {
            font-size: 14px;
        }
        
        .calificacion-item .info .detalles {
            font-size: 12px;
            flex-direction: column;
            gap: 4px;
        }
        
        .calificacion-item .puntaje-container {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }
        
        .calificacion-item .puntaje {
            font-size: 18px;
            padding: 4px 14px;
            min-width: 60px;
        }
        
        .calificacion-item .puntaje-minimo {
            margin-top: 0;
            font-size: 11px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .calificaciones-container {
            padding: 8px;
        }
        
        .page-title {
            font-size: 17px;
        }
        
        .page-title i {
            font-size: 16px;
        }
        
        .stats-calificaciones {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .stat-item {
            padding: 12px;
            border-radius: 10px;
        }
        
        .stat-item .numero {
            font-size: 20px;
        }
        
        .stat-item .label {
            font-size: 11px;
        }
        
        .stat-item .icon-stat {
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .section-title {
            font-size: 14px;
            margin: 15px 0 10px 0;
        }
        
        .materia-resumen {
            padding: 10px 12px;
            border-radius: 10px;
        }
        
        .materia-resumen .header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .materia-resumen .header h4 {
            font-size: 13px;
        }
        
        .materia-resumen .stats {
            font-size: 12px;
            gap: 8px;
            flex-direction: column;
        }
        
        .calificacion-item {
            padding: 10px 12px;
            border-radius: 10px;
        }
        
        .calificacion-item .info .titulo {
            font-size: 13px;
        }
        
        .calificacion-item .info .titulo .badge-estado {
            font-size: 10px;
            padding: 1px 8px;
        }
        
        .calificacion-item .info .detalles {
            font-size: 11px;
        }
        
        .calificacion-item .puntaje {
            font-size: 16px;
            padding: 3px 12px;
            min-width: 50px;
        }
        
        .calificacion-item .puntaje-minimo {
            font-size: 10px;
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
        .calificaciones-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .stats-calificaciones {
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        
        .stat-item {
            padding: 10px;
        }
        
        .stat-item .numero {
            font-size: 18px;
        }
        
        .stat-item .label {
            font-size: 10px;
        }
        
        .calificacion-item .info .titulo {
            font-size: 12px;
        }
        
        .calificacion-item .puntaje {
            font-size: 14px;
            padding: 2px 10px;
            min-width: 45px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .calificaciones-container {
            padding: 10px;
        }
        
        .stats-calificaciones {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            padding: 12px;
        }
        
        .stat-item .numero {
            font-size: 22px;
        }
        
        .section-title {
            margin: 15px 0 10px 0;
        }
        
        .materia-resumen {
            padding: 10px 15px;
            margin-bottom: 10px;
        }
        
        .calificacion-item {
            padding: 10px 15px;
            margin-bottom: 8px;
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

<div class="calificaciones-container">
    <h3 class="page-title"><i class="fas fa-star"></i> Mis Calificaciones</h3>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($calificaciones)): ?>
        <!-- ESTADO: SIN CALIFICACIONES -->
        <div class="empty-state">
            <i class="fas fa-star"></i>
            <h4>No tienes calificaciones aún</h4>
            <p>Realiza evaluaciones para obtener calificaciones y ver tu progreso</p>
        </div>
    <?php else: ?>
        <!-- ESTADÍSTICAS -->
        <div class="stats-calificaciones">
            <div class="stat-item">
                <span class="icon-stat total"><i class="fas fa-clipboard-list"></i></span>
                <div class="numero total"><?php echo $total_evaluaciones; ?></div>
                <div class="label">Total Evaluaciones</div>
            </div>
            <div class="stat-item">
                <span class="icon-stat aprobado"><i class="fas fa-check-circle"></i></span>
                <div class="numero aprobado"><?php echo $aprobadas; ?></div>
                <div class="label">Aprobadas</div>
            </div>
            <div class="stat-item">
                <span class="icon-stat reprobado"><i class="fas fa-times-circle"></i></span>
                <div class="numero reprobado"><?php echo $reprobadas; ?></div>
                <div class="label">Reprobadas</div>
            </div>
            <div class="stat-item">
                <span class="icon-stat promedio"><i class="fas fa-chart-line"></i></span>
                <div class="numero promedio"><?php echo $promedio_general; ?></div>
                <div class="label">Promedio General</div>
            </div>
        </div>

        <!-- RESUMEN POR MATERIA -->
        <?php if (!empty($materias_calificaciones)): ?>
            <h4 class="section-title">
                <i class="fas fa-book"></i> Resumen por Materia
            </h4>
            <?php foreach ($materias_calificaciones as $materia): 
                $promedio_materia = round($materia['suma'] / $materia['total'], 2);
            ?>
                <div class="materia-resumen">
                    <div class="header">
                        <h4><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($materia['nombre']); ?></h4>
                        <span class="badge <?php echo $promedio_materia >= 70 ? 'badge-activo' : 'badge-inactivo'; ?>">
                            <i class="fas fa-star"></i> Promedio: <?php echo $promedio_materia; ?>
                        </span>
                    </div>
                    <div class="stats">
                        <span class="stat-item-mini">
                            <i class="fas fa-clipboard-list"></i>
                            <strong>Evaluaciones:</strong> <?php echo $materia['total']; ?>
                        </span>
                        <span class="stat-item-mini" style="color: #2ecc71;">
                            <i class="fas fa-check-circle"></i>
                            <strong>Aprobadas:</strong> <?php echo $materia['aprobadas']; ?>
                        </span>
                        <span class="stat-item-mini" style="color: #e74c3c;">
                            <i class="fas fa-times-circle"></i>
                            <strong>Reprobadas:</strong> <?php echo $materia['total'] - $materia['aprobadas']; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- DETALLE DE CALIFICACIONES -->
        <h4 class="section-title">
            <i class="fas fa-list"></i> Detalle de Calificaciones
        </h4>
        
        <?php foreach ($calificaciones as $cal): ?>
            <div class="calificacion-item <?php echo $cal['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
                <div class="info">
                    <div class="titulo">
                        <?php echo htmlspecialchars($cal['evaluacion_titulo']); ?>
                        <span class="badge-estado <?php echo $cal['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
                            <?php echo $cal['aprobado'] ? '✅ Aprobado' : '❌ Reprobado'; ?>
                        </span>
                    </div>
                    <div class="detalles">
                        <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($cal['materia_nombre']); ?></span>
                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cal['tema_nombre']); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($cal['fecha'])); ?></span>
                        <span><i class="fas fa-redo"></i> Intento <?php echo $cal['intento']; ?></span>
                    </div>
                </div>
                <div class="puntaje-container">
                    <span class="puntaje <?php echo $cal['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
                        <?php echo $cal['puntaje_obtenido']; ?> / <?php echo $cal['puntaje_maximo']; ?>
                    </span>
                    <span class="puntaje-minimo">
                        <i class="fas fa-flag-checkered"></i> Mínimo: <?php echo $cal['puntaje_aprobacion']; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>