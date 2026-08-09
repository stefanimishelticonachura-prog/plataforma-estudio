<?php
$page_title = 'Material de Estudio';
$page_icon = 'video';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

$materia_id = $_GET['materia_id'] ?? 0;
$tema_id = $_GET['tema_id'] ?? 0;

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
    .material-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        display: flex;
        gap: 20px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .material-icon {
        width: 60px;
        height: 60px;
        border-radius: 10px;
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
    .material-actions {
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: flex-end;
        min-width: 120px;
    }
    .btn-material {
        background: #3498db;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 5px;
        font-size: 13px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        width: 100%;
    }
    .btn-material:hover {
        background: #2980b9;
    }
    .btn-material.download {
        background: #2ecc71;
    }
    .btn-material.download:hover {
        background: #27ae60;
    }
    .btn-material.video {
        background: #e74c3c;
    }
    .btn-material.video:hover {
        background: #c0392b;
    }
    .btn-material.enlace {
        background: #f39c12;
    }
    .btn-material.enlace:hover {
        background: #e67e22;
    }
    .tamaño-archivo {
        font-size: 11px;
        color: #999;
        text-align: center;
        margin-top: 3px;
    }
    .tema-list {
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .tema-item {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background 0.2s;
    }
    .tema-item:hover {
        background: #f8f9fa;
    }
    .tema-item:last-child {
        border-bottom: none;
    }
    .tema-item .nombre {
        font-weight: 500;
        color: #2c3e50;
    }
    .tema-item .badge-material {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
    }
    .tema-item.active {
        background: #e3f2fd;
        border-left: 3px solid #3498db;
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
    .btn-delete {
        background: #95a5a6;
        color: white;
    }
    .btn-delete:hover {
        background: #7f8c8d;
    }
    .tipo-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
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
</style>

<h3><i class="fas fa-play"></i> Material de Estudio</h3>

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
            <a href="material-estudio.php" class="btn-sm btn-delete">
                <i class="fas fa-times"></i> Limpiar
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($materia_id == 0): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-hand-pointer" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">Selecciona una materia para ver su material de estudio</p>
    </div>
<?php elseif (empty($temas)): ?>
    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
        <i class="fas fa-folder-open" style="font-size: 48px; color: #ccc;"></i>
        <p style="color: #999; margin-top: 15px;">Esta materia no tiene temas aún</p>
    </div>
<?php else: ?>
    <!-- Lista de temas -->
    <div class="tema-list">
        <h4 style="margin-bottom: 10px; color: #2c3e50;">
            <i class="fas fa-list"></i> Temas
        </h4>
        <?php foreach ($temas as $tema): ?>
            <a href="material-estudio.php?materia_id=<?php echo $materia_id; ?>&tema_id=<?php echo $tema['id']; ?>" 
               style="text-decoration: none; display: block;">
                <div class="tema-item <?php echo $tema_id == $tema['id'] ? 'active' : ''; ?>">
                    <span class="nombre">
                        <?php echo htmlspecialchars($tema['nombre']); ?>
                    </span>
                    <span class="badge-material">
                        <i class="fas fa-file-alt"></i> <?php echo $tema['total_materiales']; ?> materiales
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Materiales del tema seleccionado -->
    <?php if ($tema_id > 0): ?>
        <?php if (empty($materiales)): ?>
            <div style="text-align: center; padding: 20px; background: white; border-radius: 10px;">
                <p style="color: #999;">Este tema no tiene materiales aún</p>
            </div>
        <?php else: ?>
            <h4 style="margin: 20px 0 15px 0; color: #2c3e50;">
                <i class="fas fa-file-alt"></i> Materiales disponibles
            </h4>
            <?php foreach ($materiales as $material): ?>
                <?php
                // Determinar icono y colores según tipo
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
                ?>
                <div class="material-card">
                    <div class="material-icon" style="background: <?php echo $color_fondo; ?>; color: <?php echo $color_texto; ?>;">
                        <i class="fas fa-<?php echo $icono; ?>"></i>
                    </div>
                    <div class="material-info">
                        <h4><?php echo htmlspecialchars($material['titulo']); ?></h4>
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
                            <!-- Es una URL (video o enlace) -->
                            <a href="<?php echo htmlspecialchars($material['url']); ?>" target="_blank" class="btn-material <?php echo $material['tipo'] == 'video' ? 'video' : 'enlace'; ?>">
                                <i class="fas fa-<?php echo $material['tipo'] == 'video' ? 'play' : 'external-link-alt'; ?>"></i>
                                <?php echo $material['tipo'] == 'video' ? 'Ver Video' : 'Abrir Enlace'; ?>
                            </a>
                        <?php elseif (!empty($material['archivo_ruta'])): ?>
                            <!-- Es un archivo subido -->
                            <?php 
                            $ruta_archivo = '../../' . $material['archivo_ruta'];
                            $nombre_descarga = $material['archivo_nombre'] ?? 'descargar';
                            ?>
                            <a href="<?php echo $ruta_archivo; ?>" target="_blank" class="btn-material download">
                                <i class="fas fa-download"></i> Descargar
                            </a>
                            <?php if ($material['archivo_tamano']): ?>
                                <div class="tamaño-archivo">
                                    <?php echo number_format($material['archivo_tamano'] / 1024 / 1024, 2); ?> MB
                                </div>
                            <?php endif; ?>
                            <?php if ($material['archivo_nombre']): ?>
                                <div class="tamaño-archivo" style="font-size: 10px; color: #bbb;">
                                    <?php echo htmlspecialchars($material['archivo_nombre']); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">Sin contenido disponible</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 20px; background: white; border-radius: 10px;">
            <p style="color: #999;">Selecciona un tema para ver sus materiales</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>