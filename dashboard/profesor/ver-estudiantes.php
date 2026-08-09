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

<h3><i class="fas fa-users"></i> Ver Estudiantes</h3>

<div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <label style="font-weight: 500; margin-right: 10px;">
            <i class="fas fa-book"></i> Seleccionar Materia:
        </label>
        <select name="materia_id" onchange="this.form.submit()" style="padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-width: 250px;">
            <option value="">-- Selecciona una materia --</option>
            <?php foreach ($materias as $materia): ?>
                <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($materia['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if ($materia_id == 0): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-hand-pointer" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">Selecciona una materia para ver sus estudiantes</p>
    </div>
<?php elseif (empty($estudiantes)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-user-graduate" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">No hay estudiantes inscritos en esta materia</p>
    </div>
<?php else: ?>
    <div style="margin-bottom: 15px;">
        <strong>Total de estudiantes:</strong> <?php echo count($estudiantes); ?>
    </div>
    
    <?php foreach ($estudiantes as $estudiante): ?>
        <div style="background: white; border-radius: 10px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <span style="font-weight: 600; color: #2c3e50;">
                    <?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']); ?>
                </span>
                <span style="color: #666; font-size: 14px;">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($estudiante['correo']); ?>
                </span>
                <span style="color: #999; font-size: 13px;">
                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($estudiante['fecha_registro'])); ?>
                </span>
            </div>
            <div>
                <?php if ($estudiante['promedio'] !== null): ?>
                    <?php 
                    $promedio = round($estudiante['promedio'], 2);
                    $clase = $promedio >= 70 ? 'background: #d4edda; color: #155724;' : ($promedio >= 50 ? 'background: #fff3cd; color: #856404;' : 'background: #f8d7da; color: #721c24;');
                    ?>
                    <span style="font-weight: bold; padding: 5px 15px; border-radius: 20px; font-size: 14px; <?php echo $clase; ?>">
                        <i class="fas fa-star"></i> <?php echo $promedio; ?>
                    </span>
                <?php else: ?>
                    <span style="background: #e9ecef; color: #6c757d; padding: 5px 15px; border-radius: 20px; font-size: 14px;">
                        <i class="fas fa-minus"></i> Sin calificaciones
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>