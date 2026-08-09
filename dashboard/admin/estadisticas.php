<?php
$page_title = 'Estadísticas del Sistema';
$page_icon = 'chart-pie';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

try {
    // Estadísticas de usuarios por rol
    $stmt = $pdo->query("
        SELECT r.nombre, COUNT(u.id) as total 
        FROM Roles r
        LEFT JOIN Usuarios u ON r.id = u.id_rol AND u.activo = 1
        GROUP BY r.id
    ");
    $usuarios_por_rol = $stmt->fetchAll();
    
    // Estadísticas de materias
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivas
        FROM Materias
    ");
    $materias_stats = $stmt->fetch();
    
    // Materias por profesor
    $stmt = $pdo->query("
        SELECT 
            CONCAT(u.nombre, ' ', u.apellido) as profesor,
            COUNT(m.id) as total_materias
        FROM Usuarios u
        LEFT JOIN Materias m ON u.id = m.id_profesor
        WHERE u.id_rol = 2 AND u.activo = 1
        GROUP BY u.id
        ORDER BY total_materias DESC
        LIMIT 5
    ");
    $materias_por_profesor = $stmt->fetchAll();
    
    // Actividad diaria (últimos 7 días)
    $stmt = $pdo->query("
        SELECT 
            DATE(fecha) as fecha,
            COUNT(*) as total
        FROM Auditoria
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(fecha)
        ORDER BY fecha DESC
    ");
    $actividad_diaria = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $usuarios_por_rol = [];
    $materias_stats = ['total' => 0, 'activas' => 0, 'inactivas' => 0];
    $materias_por_profesor = [];
    $actividad_diaria = [];
    $_SESSION['error'] = 'Error al cargar estadísticas';
}
?>

<style>
    .stats-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    .stats-chart {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .stats-chart h4 {
        margin-bottom: 15px;
        color: #2c3e50;
    }
    .chart-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .chart-bar .label {
        width: 100px;
        font-size: 14px;
        color: #555;
        text-align: right;
    }
    .chart-bar .bar {
        flex: 1;
        height: 25px;
        background: #e0e0e0;
        border-radius: 5px;
        overflow: hidden;
        position: relative;
    }
    .chart-bar .bar .fill {
        height: 100%;
        border-radius: 5px;
        transition: width 0.5s;
    }
    .chart-bar .bar .value {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        font-weight: 600;
        color: #333;
    }
    .stat-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    .stat-summary .item {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        text-align: center;
    }
    .stat-summary .item .number {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
    }
    .stat-summary .item .label {
        color: #666;
        font-size: 14px;
        margin-top: 5px;
    }
    @media (max-width: 768px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
        .stat-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<h3><i class="fas fa-chart-pie"></i> Estadísticas del Sistema</h3>

<div class="stat-summary">
    <div class="item">
        <div class="number"><?php echo array_sum(array_column($usuarios_por_rol, 'total')); ?></div>
        <div class="label">Total Usuarios</div>
    </div>
    <div class="item">
        <div class="number"><?php echo $materias_stats['activas']; ?></div>
        <div class="label">Materias Activas</div>
    </div>
    <div class="item">
        <div class="number"><?php echo count($usuarios_por_rol); ?></div>
        <div class="label">Roles</div>
    </div>
</div>

<div class="stats-container">
    <!-- Usuarios por Rol -->
    <div class="stats-chart">
        <h4><i class="fas fa-users"></i> Usuarios por Rol</h4>
        <?php 
        $max_usuarios = max(array_column($usuarios_por_rol, 'total'));
        $colors = ['#3498db', '#7b1fa2', '#e74c3c'];
        ?>
        <?php foreach ($usuarios_por_rol as $index => $rol): ?>
            <div class="chart-bar">
                <span class="label"><?php echo ucfirst(htmlspecialchars($rol['nombre'])); ?></span>
                <div class="bar">
                    <div class="fill" style="width: <?php echo $max_usuarios > 0 ? ($rol['total'] / $max_usuarios * 100) : 0; ?>%; background: <?php echo $colors[$index] ?? '#3498db'; ?>;">
                        <span class="value"><?php echo $rol['total']; ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Materias por Profesor -->
    <div class="stats-chart">
        <h4><i class="fas fa-chalkboard-teacher"></i> Top Profesores por Materias</h4>
        <?php if (empty($materias_por_profesor)): ?>
            <p style="color: #999; text-align: center; padding: 20px;">No hay datos disponibles</p>
        <?php else: ?>
            <?php 
            $max_materias = max(array_column($materias_por_profesor, 'total_materias'));
            ?>
            <?php foreach ($materias_por_profesor as $profesor): ?>
                <div class="chart-bar">
                    <span class="label" style="width: 120px;"><?php echo htmlspecialchars($profesor['profesor']); ?></span>
                    <div class="bar">
                        <div class="fill" style="width: <?php echo $max_materias > 0 ? ($profesor['total_materias'] / $max_materias * 100) : 0; ?>%; background: #f39c12;">
                            <span class="value"><?php echo $profesor['total_materias']; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Actividad Diaria -->
    <div class="stats-chart" style="grid-column: 1 / -1;">
        <h4><i class="fas fa-calendar-day"></i> Actividad Últimos 7 Días</h4>
        <?php if (empty($actividad_diaria)): ?>
            <p style="color: #999; text-align: center; padding: 20px;">No hay actividad registrada</p>
        <?php else: ?>
            <div style="display: flex; gap: 10px; align-items: flex-end; height: 200px; padding-top: 20px;">
                <?php 
                $max_actividad = max(array_column($actividad_diaria, 'total'));
                $dias = array_reverse($actividad_diaria);
                ?>
                <?php foreach ($dias as $dia): ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%;">
                        <div style="width: 100%; display: flex; justify-content: center; align-items: flex-end; height: 100%;">
                            <div style="width: 30px; background: #3498db; height: <?php echo $max_actividad > 0 ? ($dia['total'] / $max_actividad * 100) : 0; ?>%; border-radius: 5px 5px 0 0; transition: height 0.5s;">
                            </div>
                        </div>
                        <span style="font-size: 11px; color: #666; margin-top: 5px;">
                            <?php echo date('d/m', strtotime($dia['fecha'])); ?>
                        </span>
                        <span style="font-size: 11px; color: #999; font-weight: bold;">
                            <?php echo $dia['total']; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>