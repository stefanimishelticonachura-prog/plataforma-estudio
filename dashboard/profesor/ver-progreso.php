<?php
$page_title = 'Progreso de Estudiantes';
$page_icon = 'chart-line';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$materia_id = $_GET['materia_id'] ?? 0;

// =============================================
// PROCESAR MARCAR TEMA COMPLETADO
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_tema') {
    $tema_id = $_POST['tema_id'];
    $estudiante_id = $_POST['estudiante_id'];
    $completado = $_POST['completado'] ?? 1;
    
    try {
        // Verificar que el profesor tiene acceso a esta materia
        $stmt = $pdo->prepare("
            SELECT t.id_materia FROM Temas t WHERE t.id = ?
        ");
        $stmt->execute([$tema_id]);
        $tema = $stmt->fetch();
        
        if (!$tema) {
            echo json_encode(['success' => false, 'message' => 'Tema no encontrado']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT id FROM Materias WHERE id = ? AND id_profesor = ?
        ");
        $stmt->execute([$tema['id_materia'], $usuario_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'No tienes permiso']);
            exit();
        }
        
        // Actualizar o insertar progreso
        $stmt = $pdo->prepare("
            INSERT INTO Progreso (id_usuario, id_tema, tema_completado_por_profesor) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            tema_completado_por_profesor = ?
        ");
        $stmt->execute([$estudiante_id, $tema_id, $completado, $completado]);
        
        echo json_encode(['success' => true]);
        exit();
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// =============================================
// OBTENER MATERIAS DEL PROFESOR
// =============================================
try {
    $stmt = $pdo->prepare("
        SELECT id, nombre FROM Materias 
        WHERE id_profesor = ? AND estado = 'activo'
        ORDER BY nombre
    ");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias = [];
}

// =============================================
// OBTENER DATOS DE PROGRESO
// =============================================
$estudiantes_progreso = [];
$temas_materia = [];

if ($materia_id > 0) {
    try {
        // Obtener temas de la materia
        $stmt = $pdo->prepare("
            SELECT id, nombre, orden FROM Temas 
            WHERE id_materia = ? 
            ORDER BY orden
        ");
        $stmt->execute([$materia_id]);
        $temas_materia = $stmt->fetchAll();
        
        // Obtener estudiantes inscritos con su progreso
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nombre,
                u.apellido,
                u.correo,
                p.id_tema,
                p.video_visto,
                p.material_revisado,
                p.evaluacion_completada,
                p.tema_completado_por_profesor,
                p.porcentaje
            FROM Inscripciones i
            JOIN Usuarios u ON i.id_usuario = u.id
            LEFT JOIN Progreso p ON p.id_usuario = u.id AND p.id_tema IN (
                SELECT id FROM Temas WHERE id_materia = ?
            )
            WHERE i.id_materia = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->execute([$materia_id, $materia_id]);
        $resultados = $stmt->fetchAll();
        
        // Agrupar por estudiante
        $estudiantes_temp = [];
        foreach ($resultados as $row) {
            $id = $row['id'];
            if (!isset($estudiantes_temp[$id])) {
                $estudiantes_temp[$id] = [
                    'id' => $row['id'],
                    'nombre' => $row['nombre'] . ' ' . $row['apellido'],
                    'correo' => $row['correo'],
                    'temas' => []
                ];
            }
            if ($row['id_tema']) {
                $estudiantes_temp[$id]['temas'][$row['id_tema']] = [
                    'video_visto' => $row['video_visto'] ?? 0,
                    'material_revisado' => $row['material_revisado'] ?? 0,
                    'evaluacion_completada' => $row['evaluacion_completada'] ?? 0,
                    'completado_por_profesor' => $row['tema_completado_por_profesor'] ?? 0,
                    'porcentaje' => $row['porcentaje'] ?? 0
                ];
            }
        }
        $estudiantes_progreso = array_values($estudiantes_temp);
        
    } catch (PDOException $e) {
        $estudiantes_progreso = [];
        $temas_materia = [];
        $_SESSION['error'] = 'Error al cargar progreso';
    }
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    .container-progreso {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .filtro-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .filtro-container select {
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        min-width: 250px;
    }
    .filtro-container select:focus {
        outline: none;
        border-color: #9b59b6;
    }
    
    .progreso-tabla {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    .progreso-tabla table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .progreso-tabla th {
        background: #f8f9fa;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .progreso-tabla td {
        padding: 8px 12px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    .progreso-tabla tr:hover td {
        background: #f8f9fa;
    }
    
    .estudiante-nombre {
        font-weight: 500;
        color: #2c3e50;
    }
    .estudiante-correo {
        color: #999;
        font-size: 12px;
        display: block;
    }
    
    .btn-toggle-tema {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 18px;
        padding: 4px 8px;
        border-radius: 5px;
        transition: all 0.2s;
    }
    .btn-toggle-tema:hover {
        background: #f0f0f0;
    }
    .btn-toggle-tema.completado {
        color: #2ecc71;
    }
    .btn-toggle-tema.pendiente {
        color: #ccc;
    }
    .btn-toggle-tema.pendiente:hover {
        color: #f39c12;
    }
    
    .badge-progreso {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
    }
    .badge-progreso.alto {
        background: #d4edda;
        color: #155724;
    }
    .badge-progreso.medio {
        background: #fff3cd;
        color: #856404;
    }
    .badge-progreso.bajo {
        background: #f8d7da;
        color: #721c24;
    }
    
    .barra-progreso {
        width: 100%;
        height: 6px;
        background: #f0f0f0;
        border-radius: 3px;
        overflow: hidden;
        min-width: 60px;
    }
    .barra-progreso .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s;
        background: linear-gradient(90deg, #9b59b6, #3498db);
    }
    
    .btn-ver-estudiante {
        background: #3498db;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 4px 12px;
        cursor: pointer;
        font-size: 12px;
        transition: background 0.2s;
    }
    .btn-ver-estudiante:hover {
        background: #2980b9;
    }
    
    .tema-header-col {
        font-size: 12px;
        color: #555;
        text-align: center;
        min-width: 40px;
    }
    
    .sin-datos {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    .sin-datos i {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
        color: #ccc;
    }
    
    .leyenda {
        margin-top: 15px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 13px;
        color: #666;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .leyenda .item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .leyenda .item .icono {
        font-size: 18px;
    }
    .leyenda .item .icono.verde { color: #2ecc71; }
    .leyenda .item .icono.gris { color: #ccc; }
    
    @media (max-width: 768px) {
        .progreso-tabla {
            padding: 10px;
        }
        .progreso-tabla table {
            font-size: 12px;
        }
        .progreso-tabla th,
        .progreso-tabla td {
            padding: 6px 8px;
        }
        .btn-toggle-tema {
            font-size: 16px;
            padding: 2px 6px;
        }
        .barra-progreso {
            min-width: 40px;
        }
    }
</style>

<div class="container-progreso">
    <h3><i class="fas fa-chart-line"></i> Progreso de Estudiantes</h3>

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

    <div class="filtro-container">
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <label style="font-weight: 500; margin-right: 10px;">
                <i class="fas fa-book"></i> Seleccionar Materia:
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
                <a href="ver-progreso.php" class="btn-sm btn-delete" style="background: #95a5a6; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($materia_id == 0): ?>
        <div class="sin-datos">
            <i class="fas fa-hand-pointer"></i>
            <p>Selecciona una materia para ver el progreso de los estudiantes</p>
        </div>
    <?php elseif (empty($estudiantes_progreso)): ?>
        <div class="sin-datos">
            <i class="fas fa-user-graduate"></i>
            <p>No hay estudiantes inscritos en esta materia</p>
        </div>
    <?php else: ?>
        <div class="progreso-tabla">
            <table>
                <thead>
                    <tr>
                        <th style="min-width: 180px;">Estudiante</th>
                        <?php foreach ($temas_materia as $tema): ?>
                            <th class="tema-header-col" title="<?php echo htmlspecialchars($tema['nombre']); ?>">
                                T<?php echo $tema['orden']; ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="min-width: 80px;">Progreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes_progreso as $est): ?>
                        <tr>
                            <td>
                                <span class="estudiante-nombre"><?php echo htmlspecialchars($est['nombre']); ?></span>
                                <span class="estudiante-correo"><?php echo htmlspecialchars($est['correo']); ?></span>
                            </td>
                            <?php 
                            $total_porcentaje = 0;
                            $temas_contados = 0;
                            foreach ($temas_materia as $tema):
                                $progreso_tema = $est['temas'][$tema['id']] ?? null;
                                $completado = $progreso_tema && $progreso_tema['completado_por_profesor'];
                                if ($completado) {
                                    $total_porcentaje += 100;
                                }
                                $temas_contados++;
                            ?>
                                <td style="text-align: center;">
                                    <button class="btn-toggle-tema <?php echo $completado ? 'completado' : 'pendiente'; ?>" 
                                            onclick="toggleTema(<?php echo $tema['id']; ?>, <?php echo $est['id']; ?>, <?php echo $completado ? '0' : '1'; ?>)"
                                            title="<?php echo $completado ? 'Marcar como pendiente' : 'Marcar como completado'; ?>">
                                        <i class="fas fa-<?php echo $completado ? 'check-circle' : 'circle'; ?>"></i>
                                    </button>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <?php 
                                $porcentaje_total = $temas_contados > 0 ? round(($total_porcentaje / $temas_contados)) : 0;
                                $clase = $porcentaje_total >= 80 ? 'alto' : ($porcentaje_total >= 50 ? 'medio' : 'bajo');
                                ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="barra-progreso">
                                        <div class="fill" style="width: <?php echo $porcentaje_total; ?>%;"></div>
                                    </div>
                                    <span class="badge-progreso <?php echo $clase; ?>">
                                        <?php echo $porcentaje_total; ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="leyenda">
                <span class="item">
                    <span class="icono verde"><i class="fas fa-check-circle"></i></span>
                    Tema completado
                </span>
                <span class="item">
                    <span class="icono gris"><i class="fas fa-circle"></i></span>
                    Tema pendiente
                </span>
                <span class="item">
                    <span style="font-weight: 500;">💡</span>
                    Haz clic en los círculos para marcar/desmarcar temas
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleTema(temaId, estudianteId, completado) {
    // Mostrar feedback visual
    var btn = event.target.closest('.btn-toggle-tema');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=marcar_tema&tema_id=' + temaId + '&estudiante_id=' + estudianteId + '&completado=' + completado
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recargar la página para ver los cambios
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
            if (btn) {
                btn.innerHTML = completado ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-circle"></i>';
                btn.disabled = false;
            }
        }
    })
    .catch(error => {
        alert('Error de conexión');
        if (btn) {
            btn.innerHTML = completado ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-circle"></i>';
            btn.disabled = false;
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>