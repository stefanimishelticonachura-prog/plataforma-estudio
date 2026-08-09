<?php
$page_title = 'Auditoría del Sistema';
$page_icon = 'clipboard-list';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

// Filtros
$filtro_tabla = $_GET['tabla'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

try {
    $sql = "SELECT * FROM Auditoria WHERE 1=1";
    $params = [];
    
    if ($filtro_tabla) {
        $sql .= " AND tabla_afectada = ?";
        $params[] = $filtro_tabla;
    }
    
    if ($filtro_fecha) {
        $sql .= " AND DATE(fecha) = ?";
        $params[] = $filtro_fecha;
    }
    
    $sql .= " ORDER BY fecha DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $auditoria = $stmt->fetchAll();
    
    // Obtener tablas para el filtro
    $stmt_tablas = $pdo->query("SELECT DISTINCT tabla_afectada FROM Auditoria ORDER BY tabla_afectada");
    $tablas = $stmt_tablas->fetchAll();
    
} catch (PDOException $e) {
    $auditoria = [];
    $tablas = [];
    $_SESSION['error'] = 'Error al cargar auditoría';
}
?>

<style>
    .filtros {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filtros .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 150px;
    }
    .filtros .form-group label {
        font-size: 13px;
        margin-bottom: 3px;
    }
    .filtros .form-group select,
    .filtros .form-group input {
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        width: 100%;
    }
    .filtros .btn-filter {
        background: #3498db;
        color: white;
        padding: 8px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
    }
    .filtros .btn-filter:hover {
        background: #2980b9;
    }
    .filtros .btn-clear {
        background: #95a5a6;
        color: white;
        padding: 8px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
    }
    .filtros .btn-clear:hover {
        background: #7f8c8d;
    }
    .auditoria-item {
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 1px 5px rgba(0,0,0,0.05);
        border-left: 4px solid #3498db;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .auditoria-item .info {
        flex: 1;
    }
    .auditoria-item .info .accion {
        font-weight: 600;
        color: #2c3e50;
    }
    .auditoria-item .info .detalle {
        color: #666;
        font-size: 14px;
        margin-top: 3px;
    }
    .auditoria-item .info .detalle i {
        margin: 0 5px;
    }
    .auditoria-item .fecha {
        color: #999;
        font-size: 13px;
    }
    .auditoria-item .badge-tabla {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
    }
</style>

<h3><i class="fas fa-clipboard-list"></i> Registro de Auditoría</h3>

<div class="filtros">
    <div class="form-group">
        <label>Tabla</label>
        <select name="tabla" id="filtroTabla">
            <option value="">Todas</option>
            <?php foreach ($tablas as $tabla): ?>
                <option value="<?php echo htmlspecialchars($tabla['tabla_afectada']); ?>" 
                    <?php echo $filtro_tabla == $tabla['tabla_afectada'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($tabla['tabla_afectada']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Fecha</label>
        <input type="date" id="filtroFecha" value="<?php echo $filtro_fecha; ?>">
    </div>
    <div>
        <button onclick="aplicarFiltros()" class="btn-filter">
            <i class="fas fa-search"></i> Filtrar
        </button>
        <a href="auditoria.php" class="btn-clear">
            <i class="fas fa-times"></i> Limpiar
        </a>
    </div>
</div>

<?php if (empty($auditoria)): ?>
    <p style="text-align: center; color: #999; padding: 30px;">No hay registros de auditoría</p>
<?php else: ?>
    <?php foreach ($auditoria as $item): ?>
        <div class="auditoria-item">
            <div class="info">
                <div class="accion">
                    <?php 
                    $icon = $item['accion'] == 'INSERT' ? 'fa-plus-circle' : ($item['accion'] == 'UPDATE' ? 'fa-edit' : 'fa-trash');
                    $color = $item['accion'] == 'INSERT' ? '#2ecc71' : ($item['accion'] == 'UPDATE' ? '#f39c12' : '#e74c3c');
                    ?>
                    <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                    <?php echo htmlspecialchars($item['accion']); ?>
                    <span class="badge-tabla"><?php echo htmlspecialchars($item['tabla_afectada']); ?></span>
                </div>
                <div class="detalle">
                    <?php if ($item['datos_nuevos']): ?>
                        <?php 
                        $datos = json_decode($item['datos_nuevos'], true);
                        if ($datos) {
                            echo 'Nuevos: ';
                            $parts = [];
                            foreach ($datos as $key => $value) {
                                $parts[] = $key . ': ' . htmlspecialchars($value);
                            }
                            echo implode(' | ', $parts);
                        }
                        ?>
                    <?php endif; ?>
                    <?php if ($item['datos_anteriores']): ?>
                        <?php 
                        $datos = json_decode($item['datos_anteriores'], true);
                        if ($datos) {
                            echo ' | Anteriores: ';
                            $parts = [];
                            foreach ($datos as $key => $value) {
                                $parts[] = $key . ': ' . htmlspecialchars($value);
                            }
                            echo implode(' | ', $parts);
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="fecha">
                <i class="fas fa-clock"></i>
                <?php echo date('d/m/Y H:i:s', strtotime($item['fecha'])); ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function aplicarFiltros() {
    const tabla = document.getElementById('filtroTabla').value;
    const fecha = document.getElementById('filtroFecha').value;
    let url = 'auditoria.php?';
    if (tabla) url += 'tabla=' + tabla + '&';
    if (fecha) url += 'fecha=' + fecha;
    window.location.href = url;
}
</script>

<?php require_once '../../includes/footer.php'; ?>