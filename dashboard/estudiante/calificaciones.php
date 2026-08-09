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
    .stats-calificaciones {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-item {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        text-align: center;
    }
    .stat-item .numero {
        font-size: 32px;
        font-weight: bold;
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
    .calificacion-item {
        background: white;
        border-radius: 10px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        border-left: 4px solid #3498db;
    }
    .calificacion-item.aprobado {
        border-left-color: #2ecc71;
    }
    .calificacion-item.reprobado {
        border-left-color: #e74c3c;
    }
    .calificacion-item .info {
        flex: 1;
    }
    .calificacion-item .info .titulo {
        font-weight: 600;
        color: #2c3e50;
    }
    .calificacion-item .info .detalles {
        color: #666;
        font-size: 13px;
        margin-top: 3px;
    }
    .calificacion-item .info .detalles span {
        margin-right: 15px;
    }
    .calificacion-item .puntaje {
        font-size: 20px;
        font-weight: bold;
        padding: 5px 15px;
        border-radius: 20px;
    }
    .calificacion-item .puntaje.aprobado {
        color: #2ecc71;
        background: #d4edda;
    }
    .calificacion-item .puntaje.reprobado {
        color: #e74c3c;
        background: #f8d7da;
    }
    .materia-resumen {
        background: white;
        border-radius: 10px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        border-left: 4px solid #3498db;
    }
    .materia-resumen .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .materia-resumen .header h4 {
        margin: 0;
        color: #2c3e50;
    }
    .materia-resumen .stats {
        display: flex;
        gap: 20px;
        margin-top: 10px;
        font-size: 14px;
        color: #666;
    }
    .materia-resumen .stats strong {
        color: #2c3e50;
    }
</style>

<h3><i class="fas fa-star"></i> Mis Calificaciones</h3>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="stats-calificaciones">
    <div class="stat-item">
        <div class="numero"><?php echo $total_evaluaciones; ?></div>
        <div class="label">Total Evaluaciones</div>
    </div>
    <div class="stat-item">
        <div class="numero aprobado"><?php echo $aprobadas; ?></div>
        <div class="label">Aprobadas</div>
    </div>
    <div class="stat-item">
        <div class="numero reprobado"><?php echo $reprobadas; ?></div>
        <div class="label">Reprobadas</div>
    </div>
    <div class="stat-item">
        <div class="numero promedio"><?php echo $promedio_general; ?></div>
        <div class="label">Promedio General</div>
    </div>
</div>

<?php if (empty($calificaciones)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-star" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">No tienes calificaciones aún</p>
        <p style="color: #999; font-size: 14px;">Realiza evaluaciones para obtener calificaciones</p>
    </div>
<?php else: ?>
    <!-- Resumen por Materia -->
    <?php if (!empty($materias_calificaciones)): ?>
        <h4 style="margin: 20px 0 15px 0; color: #2c3e50;">
            <i class="fas fa-book"></i> Resumen por Materia
        </h4>
        <?php foreach ($materias_calificaciones as $materia): 
            $promedio_materia = round($materia['suma'] / $materia['total'], 2);
        ?>
            <div class="materia-resumen">
                <div class="header">
                    <h4><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                    <span class="badge <?php echo $promedio_materia >= 70 ? 'badge-activo' : 'badge-inactivo'; ?>">
                        Promedio: <?php echo $promedio_materia; ?>
                    </span>
                </div>
                <div class="stats">
                    <span><strong>Evaluaciones:</strong> <?php echo $materia['total']; ?></span>
                    <span><strong>Aprobadas:</strong> <?php echo $materia['aprobadas']; ?></span>
                    <span><strong>Reprobadas:</strong> <?php echo $materia['total'] - $materia['aprobadas']; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Detalle de Calificaciones -->
    <h4 style="margin: 20px 0 15px 0; color: #2c3e50;">
        <i class="fas fa-list"></i> Detalle de Calificaciones
    </h4>
    
    <?php foreach ($calificaciones as $cal): ?>
        <div class="calificacion-item <?php echo $cal['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
            <div class="info">
                <div class="titulo"><?php echo htmlspecialchars($cal['evaluacion_titulo']); ?></div>
                <div class="detalles">
                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($cal['materia_nombre']); ?></span>
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cal['tema_nombre']); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($cal['fecha'])); ?></span>
                    <span><i class="fas fa-redo"></i> Intento <?php echo $cal['intento']; ?></span>
                </div>
            </div>
            <div>
                <span class="puntaje <?php echo $cal['aprobado'] ? 'aprobado' : 'reprobado'; ?>">
                    <?php echo $cal['puntaje_obtenido']; ?> / <?php echo $cal['puntaje_maximo']; ?>
                </span>
                <div style="font-size: 12px; text-align: center; margin-top: 3px; color: #999;">
                    Mínimo: <?php echo $cal['puntaje_aprobacion']; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>