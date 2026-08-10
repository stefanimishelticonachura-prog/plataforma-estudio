<?php
// PRIMERO: Procesar acciones ANTES del header
$page_title = 'Mis Materias';
$page_icon = 'book';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// =============================================
// PROCESAR ELIMINACIÓN
// =============================================
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Inscripciones WHERE id_materia = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'No se puede eliminar la materia porque tiene estudiantes inscritos';
            } else {
                $stmt = $pdo->prepare("DELETE FROM Materias WHERE id = ? AND id_profesor = ?");
                $stmt->execute([$id, $usuario_id]);
                $_SESSION['success'] = 'Materia eliminada correctamente';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al eliminar materia: ' . $e->getMessage();
        }
        header('Location: mis-materias.php');
        exit();
    }
}

// =============================================
// PROCESAR TOGGLE DE ESTADO
// =============================================
if (isset($_GET['toggle']) && isset($_GET['estado'])) {
    $id = $_GET['id'] ?? 0;
    $estado = $_GET['estado'] ?? 'activo';
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE Materias SET estado = ? WHERE id = ? AND id_profesor = ?");
            $stmt->execute([$estado, $id, $usuario_id]);
            $_SESSION['success'] = 'Materia ' . ($estado == 'activo' ? 'activada' : 'desactivada') . ' correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al cambiar estado: ' . $e->getMessage();
        }
        header('Location: mis-materias.php');
        exit();
    }
}

// =============================================
// OBTENER MATERIAS DEL PROFESOR
// =============================================
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               (SELECT COUNT(*) FROM Inscripciones WHERE id_materia = m.id) as estudiantes_inscritos,
               (SELECT COUNT(*) FROM Temas WHERE id_materia = m.id) as total_temas
        FROM Materias m
        WHERE m.id_profesor = ?
        ORDER BY m.fecha_creacion DESC
    ");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias = [];
    $_SESSION['error'] = 'Error al cargar materias';
}

// =============================================
// OBTENER DATOS DE PROGRESO PARA EL MODAL
// =============================================
$materia_progreso_id = $_GET['progreso'] ?? 0;
$estudiantes_progreso = [];
$temas_materia = [];

if ($materia_progreso_id > 0) {
    try {
        // Verificar que la materia pertenece al profesor
        $stmt = $pdo->prepare("SELECT id FROM Materias WHERE id = ? AND id_profesor = ?");
        $stmt->execute([$materia_progreso_id, $usuario_id]);
        if ($stmt->fetch()) {
            // Obtener temas de la materia
            $stmt = $pdo->prepare("
                SELECT id, nombre, orden FROM Temas 
                WHERE id_materia = ? 
                ORDER BY orden
            ");
            $stmt->execute([$materia_progreso_id]);
            $temas_materia = $stmt->fetchAll();
            
            // Obtener TODOS los estudiantes inscritos con su progreso
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
                    p.porcentaje
                FROM Inscripciones i
                JOIN Usuarios u ON i.id_usuario = u.id
                LEFT JOIN Progreso p ON p.id_usuario = u.id AND p.id_tema IN (
                    SELECT id FROM Temas WHERE id_materia = ?
                )
                WHERE i.id_materia = ? AND u.activo = 1
                ORDER BY u.apellido, u.nombre
            ");
            $stmt->execute([$materia_progreso_id, $materia_progreso_id]);
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
                        'porcentaje' => $row['porcentaje'] ?? 0
                    ];
                }
            }
            $estudiantes_progreso = array_values($estudiantes_temp);
        }
    } catch (PDOException $e) {
        $estudiantes_progreso = [];
        $temas_materia = [];
    }
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    /* ===== CONTENEDOR PRINCIPAL ===== */
    .materias-container {
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
        color: #9b59b6;
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

    .alert i { font-size: 20px; }
    .alert-error { background-color: #fee; color: #c33; border: 1px solid #fcc; }
    .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

    /* ===== MATERIA CARD ===== */
    .materia-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 20px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
        border-left: 4px solid #9b59b6;
        transition: all 0.3s;
    }
    .materia-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .materia-card .materia-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .materia-card .materia-header h4 {
        margin: 0;
        color: var(--text-primary);
        font-size: 18px;
    }
    .materia-card .materia-header h4 i {
        color: #9b59b6;
        margin-right: 8px;
    }
    .materia-card .materia-descripcion {
        color: var(--text-secondary);
        margin-bottom: 10px;
        font-size: 14px;
    }
    .materia-card .materia-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 10px 0;
    }
    .materia-card .materia-stats .stat {
        font-size: 14px;
        color: var(--text-secondary);
    }
    .materia-card .materia-stats .stat strong {
        color: var(--text-primary);
    }
    .materia-card .materia-stats .stat i {
        width: 18px;
        color: #9b59b6;
    }

    /* ===== BADGES ===== */
    .badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .badge-activo { background: #d4edda; color: #155724; }
    .badge-inactivo { background: #f8d7da; color: #721c24; }

    [data-theme="dark"] .badge-activo { background: #1B2D24; color: #6EE7B7; }
    [data-theme="dark"] .badge-inactivo { background: #2D1B1B; color: #FCA5A5; }

    /* ===== BOTONES ===== */
    .materia-actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn-sm {
        padding: 6px 14px;
        font-size: 13px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
        font-weight: 500;
    }
    .btn-sm:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-view { background: #2ecc71; color: white; }
    .btn-view:hover { background: #27ae60; }
    .btn-edit { background: #3498db; color: white; }
    .btn-edit:hover { background: #2980b9; }
    .btn-toggle { background: #f39c12; color: white; }
    .btn-toggle:hover { background: #e67e22; }
    .btn-delete { background: #e74c3c; color: white; }
    .btn-delete:hover { background: #c0392b; }
    .btn-progreso { background: #9b59b6; color: white; }
    .btn-progreso:hover { background: #8e44ad; }

    /* ===== LISTA TEMAS ===== */
    .lista-temas {
        margin-top: 10px;
        padding: 10px;
        background: var(--bg-input);
        border-radius: 5px;
        border-left: 3px solid #9b59b6;
    }
    .lista-temas .titulo {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 13px;
        display: block;
        margin-bottom: 5px;
    }
    .tema-tag {
        display: inline-block;
        background: #e3f2fd;
        color: #1976d2;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 12px;
        margin: 2px 4px 2px 0;
    }
    .tema-tag .orden { color: #999; font-weight: normal; }
    .sin-temas { color: #999; font-size: 13px; font-style: italic; }

    /* ===== MODALES ===== */
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
        backdrop-filter: blur(4px);
    }
    .modal-overlay.show { display: flex; }
    
    .modal-content {
        background: var(--bg-card);
        padding: 30px;
        border-radius: 15px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalIn 0.3s ease;
    }
    @keyframes modalIn {
        from { transform: translateY(-30px) scale(0.95); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    .modal-content h3 {
        margin-bottom: 15px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal-content .btn-group {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    .modal-content .btn-group button {
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    .modal-content .btn-group button:hover { transform: translateY(-2px); }
    .btn-cancel-modal { background: #95a5a6; color: white; }
    .btn-cancel-modal:hover { background: #7f8c8d; }

    /* ===== PROGRESO MODAL ===== */
    .progreso-modal-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin-top: 10px;
    }
    .progreso-modal-table th {
        background: var(--bg-input);
        padding: 8px 10px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .progreso-modal-table td {
        padding: 6px 10px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .progreso-modal-table tr:hover td { background: var(--bg-input); }
    
    .est-nombre { font-weight: 500; color: var(--text-primary); }
    .est-correo { color: var(--text-muted); font-size: 12px; display: block; }
    
    .badge-progreso-modal {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
    }
    .badge-progreso-modal.alto { background: #d4edda; color: #155724; }
    .badge-progreso-modal.medio { background: #fff3cd; color: #856404; }
    .badge-progreso-modal.bajo { background: #f8d7da; color: #721c24; }
    
    .barra-progreso-modal {
        width: 100%;
        height: 6px;
        background: #f0f0f0;
        border-radius: 3px;
        overflow: hidden;
        min-width: 60px;
    }
    .barra-progreso-modal .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s;
        background: linear-gradient(90deg, #9b59b6, #3498db);
    }
    
    .tema-header-col {
        font-size: 12px;
        color: var(--text-secondary);
        text-align: center;
        min-width: 40px;
    }
    
    .leyenda-modal {
        margin-top: 15px;
        padding: 10px;
        background: var(--bg-input);
        border-radius: 8px;
        font-size: 13px;
        color: var(--text-secondary);
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .leyenda-modal .item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .leyenda-modal .item .icono { font-size: 16px; }
    .leyenda-modal .item .icono.verde { color: #2ecc71; }
    .leyenda-modal .item .icono.azul { color: #3498db; }
    .leyenda-modal .item .icono.naranja { color: #f39c12; }
    .leyenda-modal .item .icono.gris { color: #ccc; }
    
    .sin-estudiantes-modal {
        text-align: center;
        padding: 30px;
        color: #999;
    }
    .sin-estudiantes-modal i {
        font-size: 40px;
        display: block;
        margin-bottom: 10px;
        color: #ccc;
    }

    /* ===== FORMULARIO EDITAR ===== */
    .form-group { margin-bottom: 15px; }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 14px;
    }
    .form-group label .required { color: #e74c3c; }
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        background: var(--bg-input);
        color: var(--text-primary);
        font-family: inherit;
    }
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #9b59b6;
    }
    .form-group textarea { resize: vertical; min-height: 60px; }

    .tema-item-edit {
        background: var(--bg-input);
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
    }
    .tema-item-edit .tema-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        flex-wrap: wrap;
        gap: 5px;
    }
    .tema-item-edit .tema-header .tema-numero {
        font-weight: 600;
        color: #9b59b6;
        font-size: 13px;
    }
    .tema-item-edit input,
    .tema-item-edit textarea {
        width: 100%;
        padding: 6px 10px;
        border: 2px solid var(--border-color);
        border-radius: 5px;
        font-size: 13px;
        margin-bottom: 6px;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: inherit;
    }
    .tema-item-edit input:focus,
    .tema-item-edit textarea:focus {
        outline: none;
        border-color: #9b59b6;
    }
    .tema-item-edit textarea { resize: vertical; min-height: 40px; }
    .tema-item-edit .btn-remove-tema {
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 3px 10px;
        cursor: pointer;
        font-size: 12px;
        transition: background 0.2s;
    }
    .tema-item-edit .btn-remove-tema:hover { background: #c0392b; }
    .tema-item-edit .tema-descripcion-label {
        font-size: 12px;
        color: var(--text-muted);
        display: block;
        margin-bottom: 3px;
    }
    .btn-add-tema-edit {
        background: #9b59b6;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 8px 16px;
        cursor: pointer;
        font-size: 13px;
        width: 100%;
        margin-top: 10px;
        transition: all 0.3s;
    }
    .btn-add-tema-edit:hover {
        background: #8e44ad;
        transform: translateY(-2px);
    }
    .btn-save-modal {
        flex: 1;
        background: #3498db;
        color: white;
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    .btn-save-modal:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: var(--shadow);
    }
    .empty-state i {
        font-size: 64px;
        color: #ccc;
        display: block;
        margin-bottom: 20px;
    }
    .empty-state h4 {
        color: var(--text-secondary);
        margin-bottom: 10px;
        font-size: 20px;
    }
    .empty-state p {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 15px;
    }
    .btn-primary {
        background: #9b59b6;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background: #8e44ad;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(155, 89, 182, 0.4);
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .materias-container { padding: 15px; }
        .page-title { font-size: 22px; }
        .materia-card { padding: 16px; }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .materias-container { padding: 12px; }
        .page-title { font-size: 20px; }
        .materia-card { padding: 14px; }
        .materia-card .materia-header { flex-direction: column; align-items: flex-start; }
        .materia-card .materia-header h4 { font-size: 16px; }
        .materia-card .materia-stats { grid-template-columns: 1fr 1fr; }
        .btn-sm { padding: 5px 12px; font-size: 12px; flex: 1; justify-content: center; }
        .modal-content { padding: 20px; margin: 10px; }
        .progreso-modal-table { font-size: 12px; }
        .progreso-modal-table th, .progreso-modal-table td { padding: 4px 6px; }
        .barra-progreso-modal { min-width: 40px; }
        .materia-actions { flex-direction: column; }
        .materia-actions .btn-sm { width: 100%; justify-content: center; }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .materias-container { padding: 8px; }
        .page-title { font-size: 17px; }
        .page-title i { font-size: 16px; }
        .materia-card { padding: 12px; border-radius: 10px; }
        .materia-card .materia-header h4 { font-size: 14px; }
        .materia-card .materia-stats { grid-template-columns: 1fr; }
        .materia-card .materia-stats .stat { font-size: 13px; }
        .btn-sm { font-size: 11px; padding: 4px 10px; min-width: 50px; }
        .modal-content { padding: 15px; }
        .modal-content h3 { font-size: 17px; }
        .tema-tag { font-size: 10px; padding: 2px 8px; }
        .lista-temas { padding: 6px; }
        .lista-temas .titulo { font-size: 12px; }
        .empty-state { padding: 40px 15px; }
        .empty-state i { font-size: 48px; }
        .empty-state h4 { font-size: 17px; }
        .empty-state p { font-size: 13px; }
        .alert { padding: 10px 14px; font-size: 13px; border-radius: 8px; }
        .alert i { font-size: 16px; }
        .progreso-modal-table { font-size: 11px; }
        .progreso-modal-table th, .progreso-modal-table td { padding: 3px 4px; }
        .est-correo { font-size: 10px; }
        .tema-header-col { font-size: 10px; min-width: 30px; }
        .leyenda-modal { font-size: 11px; gap: 10px; }
        .btn-save-modal { font-size: 13px; padding: 8px 20px; }
        .btn-add-tema-edit { font-size: 12px; padding: 6px 12px; }
    }

    /* ===== RESPONSIVE - MÓVILES MUY PEQUEÑOS ===== */
    @media (max-width: 360px) {
        .materias-container { padding: 4px; }
        .page-title { font-size: 15px; }
        .materia-card .materia-header h4 { font-size: 13px; }
        .modal-content { padding: 10px; }
        .modal-content h3 { font-size: 15px; }
        .progreso-modal-table { font-size: 10px; }
        .tema-header-col { font-size: 9px; min-width: 25px; }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .materias-container { padding: 10px; }
        .materia-card { padding: 12px; margin-bottom: 12px; }
        .materia-card .materia-stats { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
        .modal-content { max-height: 95vh; }
        .progreso-modal-table { font-size: 11px; }
        .progreso-modal-table th, .progreso-modal-table td { padding: 3px 6px; }
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
    html { scroll-behavior: smooth; }

    /* ===== SELECTION ===== */
    ::selection { background: #9b59b6; color: white; }

    /* ===== UTILITY ===== */
    .hidden { display: none !important; }
</style>

<div class="materias-container">
    <h3 class="page-title"><i class="fas fa-book"></i> Mis Materias</h3>

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

    <?php if (empty($materias)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h4>No tienes materias creadas aún</h4>
            <p>Comienza creando tu primera materia para organizar tus clases</p>
            <a href="crear-materia.php" class="btn-primary">
                <i class="fas fa-plus-circle"></i> Crear Primera Materia
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($materias as $materia): 
            $temas_materia = [];
            try {
                $stmt_temas = $pdo->prepare("SELECT id, nombre, descripcion, orden FROM Temas WHERE id_materia = ? ORDER BY orden");
                $stmt_temas->execute([$materia['id']]);
                $temas_materia = $stmt_temas->fetchAll();
            } catch (PDOException $e) {
                $temas_materia = [];
            }
        ?>
            <div class="materia-card">
                <div class="materia-header">
                    <h4><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($materia['nombre']); ?></h4>
                    <span class="badge <?php echo $materia['estado'] == 'activo' ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <?php echo $materia['estado'] == 'activo' ? '📈 En progreso' : '✅ Concluida'; ?>
                    </span>
                </div>
                <p class="materia-descripcion"><?php echo htmlspecialchars($materia['descripcion']); ?></p>
                
                <div class="materia-stats">
                    <div class="stat">
                        <strong><i class="fas fa-users"></i> Estudiantes:</strong> <?php echo $materia['estudiantes_inscritos']; ?>
                    </div>
                    <div class="stat">
                        <strong><i class="fas fa-list"></i> Temas:</strong> <?php echo $materia['total_temas']; ?>
                    </div>
                    <div class="stat">
                        <strong><i class="fas fa-calendar"></i> Creada:</strong> <?php echo date('d/m/Y', strtotime($materia['fecha_creacion'])); ?>
                    </div>
                </div>

                <div class="lista-temas">
                    <span class="titulo"><i class="fas fa-tag"></i> Temas de la materia:</span>
                    <?php if (empty($temas_materia)): ?>
                        <span class="sin-temas">No hay temas creados aún</span>
                    <?php else: ?>
                        <?php foreach ($temas_materia as $tema): ?>
                            <span class="tema-tag">
                                <span class="orden"><?php echo $tema['orden']; ?>.</span>
                                <?php echo htmlspecialchars($tema['nombre']); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="materia-actions">
                    <?php if ($materia['estudiantes_inscritos'] > 0): ?>
                        <a href="mis-materias.php?progreso=<?php echo $materia['id']; ?>" class="btn-sm btn-progreso">
                            <i class="fas fa-chart-line"></i> Ver Progreso
                        </a>
                    <?php endif; ?>
                    <button class="btn-sm btn-view" onclick="verMateria(<?php echo $materia['id']; ?>)">
                        <i class="fas fa-eye"></i> Ver
                    </button>
                    <button class="btn-sm btn-edit" onclick="editarMateria(<?php echo $materia['id']; ?>)">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn-sm btn-toggle" onclick="abrirModalToggle(<?php echo $materia['id']; ?>, '<?php echo $materia['estado']; ?>')">
                        <i class="fas <?php echo $materia['estado'] == 'activo' ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                        <?php echo $materia['estado'] == 'activo' ? 'Concluir' : 'Reabrir'; ?>
                    </button>
                    <button class="btn-sm btn-delete" onclick="abrirModalEliminar(<?php echo $materia['id']; ?>, '<?php echo addslashes($materia['nombre']); ?>')">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal de Confirmación para Eliminar -->
<div id="modalEliminar" class="modal-overlay">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> ¿Eliminar Materia?</h3>
        <p id="mensajeEliminar">¿Estás seguro de que deseas eliminar esta materia? Esta acción no se puede deshacer.</p>
        <p style="font-size: 13px; color: var(--text-muted);">Si tiene estudiantes inscritos, no se podrá eliminar.</p>
        <div class="btn-group">
            <button class="btn-confirm" onclick="confirmarEliminar()" style="background: #e74c3c; color: white;">
                <i class="fas fa-trash"></i> Sí, Eliminar
            </button>
            <button class="btn-cancel-modal" onclick="cerrarModal('modalEliminar')">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Activar/Desactivar -->
<div id="modalToggle" class="modal-overlay">
    <div class="modal-content">
        <h3><i class="fas fa-question-circle" style="color: #f39c12;"></i> <span id="tituloToggle">¿Concluir Materia?</span></h3>
        <p id="mensajeToggle">Al concluir la materia, los estudiantes ya no podrán acceder a ella, pero todas las calificaciones y datos se mantendrán.</p>
        <div class="btn-group">
            <button class="btn-confirm-toggle" onclick="confirmarToggle()" style="background: #f39c12; color: white;">
                <i class="fas fa-check"></i> Sí, Confirmar
            </button>
            <button class="btn-cancel-modal" onclick="cerrarModal('modalToggle')">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal para Ver Materia -->
<div id="modalVer" class="modal-overlay">
    <div class="modal-content" style="max-width: 700px; text-align: left;">
        <h3><i class="fas fa-info-circle" style="color: #3498db;"></i> Detalles de la Materia</h3>
        <div id="detallesMateria">
            <p style="color: var(--text-muted); text-align: center;">Cargando...</p>
        </div>
        <div class="btn-group">
            <button class="btn-cancel-modal" onclick="cerrarModal('modalVer')">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal para Editar Materia -->
<div id="modalEditar" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px; text-align: left; max-height: 90vh; overflow-y: auto;">
        <h3><i class="fas fa-edit" style="color: #3498db;"></i> Editar Materia</h3>
        <form id="formEditarMateria" method="POST" action="actions/editar_materia.php">
            <input type="hidden" name="id" id="editId">
            
            <div class="form-group">
                <label>Nombre <span class="required">*</span></label>
                <input type="text" name="nombre" id="editNombre" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" id="editDescripcion" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Estado</label>
                <select name="estado" id="editEstado">
                    <option value="activo">Activo (En progreso)</option>
                    <option value="inactivo">Inactivo (Concluida)</option>
                </select>
            </div>

            <hr style="margin: 20px 0; border-color: var(--border-color);">

            <div class="form-group">
                <label><i class="fas fa-list"></i> Temas de la materia</label>
                <div id="editTemasContainer">
                    <p style="color: var(--text-muted);">Cargando temas...</p>
                </div>
                <button type="button" class="btn-add-tema-edit" onclick="agregarTemaEditar()">
                    <i class="fas fa-plus"></i> Agregar Tema
                </button>
            </div>
            
            <div class="btn-group" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="submit" class="btn-save-modal">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
                <button type="button" class="btn-cancel-modal" onclick="cerrarModal('modalEditar')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================= -->
<!-- MODAL DE PROGRESO -->
<!-- ============================================= -->
<div id="modalProgreso" class="modal-overlay <?php echo $materia_progreso_id > 0 ? 'show' : ''; ?>">
    <div class="modal-content" style="max-width: 900px; text-align: left;">
        <h3><i class="fas fa-chart-line" style="color: #9b59b6;"></i> Progreso de Estudiantes</h3>
        
        <?php if ($materia_progreso_id > 0): ?>
            <?php 
            $nombre_materia = '';
            foreach ($materias as $m) {
                if ($m['id'] == $materia_progreso_id) {
                    $nombre_materia = $m['nombre'];
                    break;
                }
            }
            ?>
            <p style="color: var(--text-secondary); margin-bottom: 15px; font-size: 14px;">
                <strong><?php echo htmlspecialchars($nombre_materia); ?></strong> 
                - Progreso de cada estudiante en los temas
            </p>
            
            <?php if (empty($estudiantes_progreso)): ?>
                <div class="sin-estudiantes-modal">
                    <i class="fas fa-user-graduate"></i>
                    <p>No hay estudiantes inscritos en esta materia</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="progreso-modal-table">
                        <thead>
                            <tr>
                                <th style="min-width: 150px;">Estudiante</th>
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
                                        <span class="est-nombre"><?php echo htmlspecialchars($est['nombre']); ?></span>
                                        <span class="est-correo"><?php echo htmlspecialchars($est['correo']); ?></span>
                                    </td>
                                    <?php 
                                    $total_porcentaje = 0;
                                    $temas_contados = 0;
                                    foreach ($temas_materia as $tema):
                                        $progreso_tema = $est['temas'][$tema['id']] ?? null;
                                        $porcentaje_tema = $progreso_tema ? $progreso_tema['porcentaje'] : 0;
                                        $total_porcentaje += $porcentaje_tema;
                                        $temas_contados++;
                                        
                                        $video = $progreso_tema ? $progreso_tema['video_visto'] : 0;
                                        $material = $progreso_tema ? $progreso_tema['material_revisado'] : 0;
                                        $evaluacion = $progreso_tema ? $progreso_tema['evaluacion_completada'] : 0;
                                        
                                        $iconos = [];
                                        if ($video) $iconos[] = '🎬';
                                        if ($material) $iconos[] = '📄';
                                        if ($evaluacion) $iconos[] = '✅';
                                        $icono_texto = !empty($iconos) ? implode(' ', $iconos) : '⬜';
                                    ?>
                                        <td style="text-align: center; font-size: 14px;">
                                            <span title="Video: <?php echo $video ? '✅' : '❌'; ?> | Material: <?php echo $material ? '✅' : '❌'; ?> | Evaluación: <?php echo $evaluacion ? '✅' : '❌'; ?>">
                                                <?php echo $icono_texto; ?>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <?php 
                                        $porcentaje_total = $temas_contados > 0 ? round(($total_porcentaje / $temas_contados)) : 0;
                                        $clase = $porcentaje_total >= 80 ? 'alto' : ($porcentaje_total >= 50 ? 'medio' : 'bajo');
                                        ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div class="barra-progreso-modal">
                                                <div class="fill" style="width: <?php echo $porcentaje_total; ?>%;"></div>
                                            </div>
                                            <span class="badge-progreso-modal <?php echo $clase; ?>">
                                                <?php echo $porcentaje_total; ?>%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="leyenda-modal">
                    <span class="item">
                        <span class="icono verde">🎬</span> Video visto
                    </span>
                    <span class="item">
                        <span class="icono azul">📄</span> Material revisado
                    </span>
                    <span class="item">
                        <span class="icono naranja">✅</span> Evaluación completada
                    </span>
                    <span class="item">
                        <span class="icono gris">⬜</span> Nada completado
                    </span>
                    <span class="item">
                        <span style="font-weight: 500;">💡</span> Pasa el mouse sobre los íconos para ver detalles
                    </span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="btn-group" style="margin-top: 20px;">
            <a href="mis-materias.php" class="btn-cancel-modal" style="text-decoration: none; text-align: center;">
                <i class="fas fa-times"></i> Cerrar
            </a>
        </div>
    </div>
</div>

<script>
var accionId = 0;
var accionTipo = '';
var accionEstado = '';
var contadorTemasEditar = 0;

function verMateria(id) {
    fetch('get_materia.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var m = data.materia;
                var temas = data.temas || [];
                
                var htmlTemas = '';
                if (temas.length > 0) {
                    temas.forEach(function(tema) {
                        htmlTemas += `
                            <div style="background: var(--bg-input); padding: 10px 15px; border-radius: 5px; margin-bottom: 8px; border-left: 3px solid #9b59b6;">
                                <span style="color: var(--text-muted); font-size: 12px; float: right;">#${tema.orden}</span>
                                <div style="font-weight: 600; color: var(--text-primary);">${tema.nombre}</div>
                                ${tema.descripcion ? `<div style="color: var(--text-secondary); font-size: 14px; margin-top: 3px;">${tema.descripcion}</div>` : ''}
                            </div>
                        `;
                    });
                } else {
                    htmlTemas = '<p style="color: var(--text-muted); text-align: center;">No hay temas en esta materia</p>';
                }
                
                document.getElementById('detallesMateria').innerHTML = `
                    <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-input); border-radius: 5px;">
                        <strong style="color: var(--text-primary);">Nombre:</strong>
                        <span style="color: var(--text-secondary);">${m.nombre}</span>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-input); border-radius: 5px;">
                        <strong style="color: var(--text-primary);">Descripción:</strong>
                        <span style="color: var(--text-secondary);">${m.descripcion || 'Sin descripción'}</span>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-input); border-radius: 5px;">
                        <strong style="color: var(--text-primary);">Estado:</strong>
                        <span class="badge ${m.estado == 'activo' ? 'badge-activo' : 'badge-inactivo'}">
                            ${m.estado.charAt(0).toUpperCase() + m.estado.slice(1)}
                        </span>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-input); border-radius: 5px;">
                        <strong style="color: var(--text-primary);">Fecha de creación:</strong>
                        <span style="color: var(--text-secondary);">${new Date(m.fecha_creacion).toLocaleDateString('es-ES')}</span>
                    </div>
                    <div style="padding: 10px; background: var(--bg-input); border-radius: 5px; border-left-color: #9b59b6;">
                        <strong style="color: var(--text-primary); display: block; margin-bottom: 10px;"><i class="fas fa-list"></i> Temas (${temas.length}):</strong>
                        ${htmlTemas}
                    </div>
                `;
            } else {
                document.getElementById('detallesMateria').innerHTML = `
                    <p style="color: #e74c3c; text-align: center;">Error al cargar los detalles</p>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detallesMateria').innerHTML = `
                <p style="color: #e74c3c; text-align: center;">Error de conexión</p>
            `;
        });
    
    document.getElementById('modalVer').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function editarMateria(id) {
    fetch('get_materia.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var m = data.materia;
                var temas = data.temas || [];
                
                document.getElementById('editId').value = m.id;
                document.getElementById('editNombre').value = m.nombre;
                document.getElementById('editDescripcion').value = m.descripcion || '';
                document.getElementById('editEstado').value = m.estado;
                
                var container = document.getElementById('editTemasContainer');
                container.innerHTML = '';
                contadorTemasEditar = 0;
                
                if (temas.length > 0) {
                    temas.forEach(function(tema) {
                        contadorTemasEditar++;
                        agregarTemaEditarHTML(container, tema.id, tema.nombre, tema.descripcion, tema.orden);
                    });
                } else {
                    contadorTemasEditar++;
                    agregarTemaEditarHTML(container, 0, '', '', 1);
                }
                
                document.getElementById('modalEditar').classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                alert('Error al cargar los datos de la materia');
            }
        })
        .catch(error => {
            alert('Error de conexión');
        });
}

function agregarTemaEditarHTML(container, id, nombre, descripcion, orden) {
    var div = document.createElement('div');
    div.className = 'tema-item-edit';
    div.id = 'edit-tema-' + contadorTemasEditar;
    div.innerHTML = `
        <div class="tema-header">
            <span class="tema-numero">Tema #${contadorTemasEditar}</span>
            <button type="button" class="btn-remove-tema" onclick="eliminarTemaEditar(${contadorTemasEditar})">
                <i class="fas fa-times"></i> Eliminar
            </button>
        </div>
        <input type="hidden" name="temas_id[]" value="${id}">
        <input type="text" name="temas_nombre_edit[]" placeholder="Nombre del tema *" class="tema-input" value="${nombre}" required>
        <span class="tema-descripcion-label"><i class="fas fa-info-circle"></i> Descripción del tema (opcional)</span>
        <textarea name="temas_descripcion_edit[]" placeholder="Breve descripción del tema" class="tema-descripcion">${descripcion || ''}</textarea>
        <input type="hidden" name="temas_orden[]" value="${orden}">
    `;
    container.appendChild(div);
    actualizarBotonesEliminarEditar();
}

function agregarTemaEditar() {
    var container = document.getElementById('editTemasContainer');
    contadorTemasEditar++;
    agregarTemaEditarHTML(container, 0, '', '', contadorTemasEditar);
}

function eliminarTemaEditar(id) {
    var temaItem = document.getElementById('edit-tema-' + id);
    var container = document.getElementById('editTemasContainer');
    
    if (container.children.length > 1) {
        temaItem.remove();
        actualizarBotonesEliminarEditar();
        actualizarNumerosEditar();
    } else {
        alert('Debe haber al menos un tema en la materia');
    }
}

function actualizarBotonesEliminarEditar() {
    var items = document.querySelectorAll('#editTemasContainer .tema-item-edit');
    var botones = document.querySelectorAll('#editTemasContainer .btn-remove-tema');
    
    if (items.length <= 1) {
        botones.forEach(function(btn) {
            btn.style.display = 'none';
        });
    } else {
        botones.forEach(function(btn) {
            btn.style.display = 'inline-block';
        });
    }
}

function actualizarNumerosEditar() {
    var items = document.querySelectorAll('#editTemasContainer .tema-item-edit');
    items.forEach(function(item, index) {
        var numero = item.querySelector('.tema-numero');
        if (numero) {
            numero.textContent = 'Tema #' + (index + 1);
        }
    });
}

function abrirModalEliminar(id, nombre) {
    accionId = id;
    accionTipo = 'eliminar';
    document.getElementById('mensajeEliminar').innerHTML = 
        '¿Estás seguro de que deseas eliminar la materia <strong>"' + nombre + '"</strong>? Esta acción no se puede deshacer.';
    document.getElementById('modalEliminar').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function abrirModalToggle(id, estado) {
    accionId = id;
    accionTipo = 'toggle';
    accionEstado = estado;
    
    var nuevoEstado = estado == 'activo' ? 'inactivo' : 'activo';
    var titulo = estado == 'activo' ? '¿Concluir Materia?' : '¿Reabrir Materia?';
    var mensaje = estado == 'activo' ? 
        'Al concluir la materia, los estudiantes ya no podrán acceder a ella, pero todas las calificaciones y datos se mantendrán.' :
        'Al reabrir la materia, los estudiantes podrán acceder nuevamente a todo el contenido.';
    
    document.getElementById('tituloToggle').textContent = titulo;
    document.getElementById('mensajeToggle').textContent = mensaje;
    document.getElementById('modalToggle').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function confirmarEliminar() {
    if (accionId > 0) {
        window.location.href = 'mis-materias.php?delete=confirm&id=' + accionId;
    }
    cerrarModal('modalEliminar');
}

function confirmarToggle() {
    if (accionId > 0) {
        var nuevoEstado = accionEstado == 'activo' ? 'inactivo' : 'activo';
        window.location.href = 'mis-materias.php?toggle=1&id=' + accionId + '&estado=' + nuevoEstado;
    }
    cerrarModal('modalToggle');
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
    accionId = 0;
    accionTipo = '';
    accionEstado = '';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('show');
        document.body.style.overflow = '';
        accionId = 0;
        accionTipo = '';
        accionEstado = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        });
        accionId = 0;
        accionTipo = '';
        accionEstado = '';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>