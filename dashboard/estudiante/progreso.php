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
    .progreso-general {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .progreso-general .numero {
        font-size: 48px;
        font-weight: bold;
        color: #2c3e50;
        text-align: center;
    }
    .progreso-general .label {
        text-align: center;
        color: #666;
        font-size: 16px;
        margin-top: 5px;
    }
    .progreso-general .barra {
        margin-top: 15px;
        background: #f0f0f0;
        border-radius: 10px;
        height: 20px;
        overflow: hidden;
    }
    .progreso-general .barra .fill {
        height: 100%;
        background: linear-gradient(90deg, #3498db, #2ecc71);
        transition: width 0.5s;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 13px;
        font-weight: 500;
    }
    .tema-progreso {
        background: white;
        border-radius: 10px;
        padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .tema-progreso .info {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }
    .tema-progreso .info .nombre {
        font-weight: 500;
        color: #2c3e50;
    }
    .tema-progreso .info .detalles {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #666;
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
    .tema-progreso .porcentaje {
        font-weight: bold;
        font-size: 18px;
        min-width: 60px;
        text-align: right;
    }
    .tema-progreso .porcentaje.completado {
        color: #2ecc71;
    }
    .tema-progreso .porcentaje.pendiente {
        color: #f39c12;
    }
    .tema-progreso .barra-mini {
        width: 100px;
        height: 6px;
        background: #f0f0f0;
        border-radius: 3px;
        overflow: hidden;
    }
    .tema-progreso .barra-mini .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s;
    }
</style>

<h3><i class="fas fa-chart-line"></i> Mi Progreso</h3>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="filtro-container">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <label style="font-weight: 500;">
            <i class="fas fa-book"></i> Materia:
        </label>
        <select name="materia_id" onchange="this.form.submit()">
            <option value="">-- Seleccionar materia --</option>
            <?php foreach ($materias as $materia): ?>
                <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($materia['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($materia_id > 0): ?>
            <a href="progreso.php" class="btn-sm btn-delete" style="background: #95a5a6; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;">
                <i class="fas fa-times"></i> Limpiar
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($materia_id == 0): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-hand-pointer" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">Selecciona una materia para ver tu progreso</p>
    </div>
<?php elseif (empty($progreso_temas)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">Esta materia no tiene temas aún</p>
    </div>
<?php else: ?>
    <!-- Progreso General -->
    <div class="progreso-general">
        <div class="numero"><?php echo $progreso_total; ?>%</div>
        <div class="label">Progreso total de la materia</div>
        <div class="barra">
            <div class="fill" style="width: <?php echo $progreso_total; ?>%;">
                <?php echo $progreso_total; ?>%
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px; color: #666;">
            <span>Temas completados: <?php echo $temas_completados; ?>/<?php echo $total_temas; ?></span>
            <span>Progreso: <?php echo $progreso_total; ?>%</span>
        </div>
    </div>
    
    <!-- Progreso por Tema -->
    <h4 style="margin: 20px 0 15px 0; color: #2c3e50;">
        <i class="fas fa-list"></i> Progreso por Tema
    </h4>
    
    <?php foreach ($progreso_temas as $tema): ?>
        <?php 
        $completado = $tema['porcentaje'] >= 100;
        $color = $completado ? '#2ecc71' : '#f39c12';
        ?>
        <div class="tema-progreso">
            <div class="info">
                <span class="nombre">
                    <i class="fas fa-<?php echo $completado ? 'check-circle' : 'circle'; ?>" 
                       style="color: <?php echo $color; ?>;"></i>
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
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="barra-mini">
                    <div class="fill" style="width: <?php echo $tema['porcentaje']; ?>%; background: <?php echo $color; ?>;"></div>
                </div>
                <span class="porcentaje <?php echo $completado ? 'completado' : 'pendiente'; ?>">
                    <?php echo $tema['porcentaje']; ?>%
                </span>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>