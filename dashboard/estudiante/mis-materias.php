<?php
$page_title = 'Mis Materias';
$page_icon = 'book';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

try {
    // Obtener materias del estudiante
    $stmt = $pdo->prepare("
        SELECT 
            m.*, 
            CONCAT(u.nombre, ' ', u.apellido) as profesor,
            (SELECT COUNT(*) FROM Temas WHERE id_materia = m.id) as total_temas,
            (SELECT COUNT(*) FROM Progreso WHERE id_usuario = ? AND id_tema IN (SELECT id FROM Temas WHERE id_materia = m.id) AND video_visto = 1) as temas_vistos,
            (SELECT COUNT(*) FROM Inscripciones WHERE id_materia = m.id) as total_estudiantes
        FROM Inscripciones i
        JOIN Materias m ON i.id_materia = m.id
        JOIN Usuarios u ON m.id_profesor = u.id
        WHERE i.id_usuario = ? AND m.estado = 'activo'
        ORDER BY m.nombre
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $materias = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $materias = [];
    $_SESSION['error'] = 'Error al cargar materias';
}
?>

<style>
    .materia-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        border-left: 4px solid #3498db;
        transition: transform 0.2s;
    }
    .materia-card:hover {
        transform: translateX(5px);
    }
    .materia-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    .materia-card .header h4 {
        margin: 0;
        color: #2c3e50;
    }
    .materia-card .info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 10px 0;
    }
    .materia-card .info-item {
        font-size: 14px;
        color: #666;
    }
    .materia-card .info-item strong {
        color: #2c3e50;
    }
    .materia-card .progreso-bar {
        margin: 15px 0;
        background: #f0f0f0;
        border-radius: 5px;
        height: 8px;
        overflow: hidden;
    }
    .materia-card .progreso-bar .fill {
        height: 100%;
        background: linear-gradient(90deg, #3498db, #2ecc71);
        transition: width 0.5s;
        border-radius: 5px;
    }
    .materia-card .actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-view {
        background: #3498db;
        color: white;
    }
    .btn-view:hover {
        background: #2980b9;
    }
    .btn-materials {
        background: #9b59b6;
        color: white;
    }
    .btn-materials:hover {
        background: #8e44ad;
    }
    .btn-evaluations {
        background: #e67e22;
        color: white;
    }
    .btn-evaluations:hover {
        background: #d35400;
    }
</style>

<h3><i class="fas fa-book"></i> Mis Materias</h3>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if (empty($materias)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-book-open" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">No estás inscrito en ninguna materia</p>
        <p style="color: #999; font-size: 14px;">Contacta a tu profesor para inscribirte</p>
    </div>
<?php else: ?>
    <?php foreach ($materias as $materia): 
        $total = $materia['total_temas'];
        $vistos = $materia['temas_vistos'];
        $porcentaje = $total > 0 ? round(($vistos / $total) * 100) : 0;
    ?>
        <div class="materia-card">
            <div class="header">
                <h4><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                <span class="badge badge-activo">
                    <i class="fas fa-check-circle"></i> Activa
                </span>
            </div>
            <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars($materia['descripcion']); ?></p>
            <div class="info">
                <div class="info-item">
                    <strong><i class="fas fa-user-tie"></i> Profesor:</strong> 
                    <?php echo htmlspecialchars($materia['profesor']); ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-list"></i> Temas:</strong> 
                    <?php echo $vistos . '/' . $total; ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-users"></i> Estudiantes:</strong> 
                    <?php echo $materia['total_estudiantes']; ?>
                </div>
            </div>
            <div class="progreso-bar">
                <div class="fill" style="width: <?php echo $porcentaje; ?>%;"></div>
            </div>
            <div style="text-align: right; font-size: 13px; color: #666;">
                Progreso: <?php echo $porcentaje; ?>%
            </div>
            <div class="actions">
                <a href="material-estudio.php?materia_id=<?php echo $materia['id']; ?>" class="btn-sm btn-materials">
                    <i class="fas fa-play"></i> Ver Material
                </a>
                <a href="evaluaciones.php?materia_id=<?php echo $materia['id']; ?>" class="btn-sm btn-evaluations">
                    <i class="fas fa-tasks"></i> Evaluaciones
                </a>
                <a href="progreso.php?materia_id=<?php echo $materia['id']; ?>" class="btn-sm btn-view">
                    <i class="fas fa-chart-line"></i> Progreso
                </a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>