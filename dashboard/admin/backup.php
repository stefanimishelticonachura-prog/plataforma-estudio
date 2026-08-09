<?php
$page_title = 'Backup de Base de Datos';
$page_icon = 'database';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

// Eliminar backup
if (isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    $backup_name = $_POST['backup'];
    $backup_path = __DIR__ . '/../../backups/' . $backup_name;
    
    if (file_exists($backup_path)) {
        if (unlink($backup_path)) {
            $_SESSION['success'] = 'Backup eliminado correctamente';
        } else {
            $_SESSION['error'] = 'Error al eliminar el backup';
        }
    } else {
        $_SESSION['error'] = 'El backup no existe';
    }
    header('Location: backup.php');
    exit();
}

// Procesar backup con PHP puro
if (isset($_POST['action']) && $_POST['action'] === 'backup') {
    try {
        // Directorio de backups
        $backup_dir = __DIR__ . '/../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Crear backup usando PHP
        $backup_content = "-- Backup de Base de Datos\n";
        $backup_content .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Base de datos: " . $dbname . "\n\n";
        
        // Obtener todas las tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tablas as $tabla) {
            // Estructura de la tabla
            $stmt = $pdo->query("SHOW CREATE TABLE `$tabla`");
            $crear = $stmt->fetch();
            $backup_content .= "DROP TABLE IF EXISTS `$tabla`;\n";
            $backup_content .= $crear['Create Table'] . ";\n\n";
            
            // Datos de la tabla
            $stmt = $pdo->query("SELECT * FROM `$tabla`");
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($datos)) {
                $columnas = array_keys($datos[0]);
                $columnas_escaped = array_map(function($col) {
                    return "`$col`";
                }, $columnas);
                
                $backup_content .= "INSERT INTO `$tabla` (" . implode(', ', $columnas_escaped) . ") VALUES\n";
                
                $valores = [];
                foreach ($datos as $fila) {
                    $fila_escapada = array_map(function($valor) use ($pdo) {
                        if ($valor === null) return 'NULL';
                        return $pdo->quote($valor);
                    }, array_values($fila));
                    $valores[] = "(" . implode(', ', $fila_escapada) . ")";
                }
                
                $backup_content .= implode(",\n", $valores) . ";\n\n";
            }
        }
        
        // Guardar archivo SQL
        file_put_contents($filepath, $backup_content);
        
        // Comprimir backup
        $zip = new ZipArchive();
        $zip_file = $backup_dir . 'backup_' . date('Y-m-d') . '.zip';
        
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filepath, $filename);
            $zip->close();
            unlink($filepath); // Eliminar archivo SQL
            
            $_SESSION['success'] = 'Backup creado correctamente: ' . basename($zip_file);
        } else {
            $_SESSION['error'] = 'Error al comprimir el backup';
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    header('Location: backup.php');
    exit();
}

// Listar backups existentes
$backup_dir = __DIR__ . '/../../backups/';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $backups[] = [
                'nombre' => $file,
                'tamaño' => filesize($backup_dir . $file),
                'fecha' => filemtime($backup_dir . $file)
            ];
        }
    }
    rsort($backups);
}
?>

<style>
    .backup-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .backup-card .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .backup-card .header h4 {
        color: #2c3e50;
        margin: 0;
    }
    .btn-backup {
        background: #2ecc71;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-backup:hover {
        background: #27ae60;
    }
    .btn-download {
        background: #3498db;
        color: white;
        padding: 6px 15px;
        border: none;
        border-radius: 5px;
        font-size: 13px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .btn-download:hover {
        background: #2980b9;
    }
    .btn-delete-backup {
        background: #e74c3c;
        color: white;
        padding: 6px 15px;
        border: none;
        border-radius: 5px;
        font-size: 13px;
        cursor: pointer;
    }
    .btn-delete-backup:hover {
        background: #c0392b;
    }
    .backup-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .backup-item:last-child {
        border-bottom: none;
    }
    .backup-item .info {
        display: flex;
        gap: 20px;
        align-items: center;
    }
    .backup-item .info .nombre {
        font-weight: 500;
        color: #2c3e50;
    }
    .backup-item .info .tamaño {
        color: #666;
        font-size: 13px;
    }
    .backup-item .info .fecha {
        color: #999;
        font-size: 13px;
    }
    .backup-item .acciones {
        display: flex;
        gap: 10px;
    }
    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-warning i {
        margin-right: 10px;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-success i {
        margin-right: 10px;
    }
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-error i {
        margin-right: 10px;
    }
</style>

<h3><i class="fas fa-database"></i> Backup de Base de Datos</h3>

<div class="alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Recomendación:</strong> Realiza backups periódicos para proteger los datos del sistema.
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<div class="backup-card">
    <div class="header">
        <h4><i class="fas fa-plus-circle"></i> Crear Nuevo Backup</h4>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="backup">
            <button type="submit" class="btn-backup">
                <i class="fas fa-cloud-upload-alt"></i> Crear Backup Ahora
            </button>
        </form>
    </div>
    <p style="color: #666; font-size: 14px;">
        El backup se creará en formato comprimido (.zip) y se guardará en el servidor.
        Incluye todas las tablas de la base de datos.
    </p>
</div>

<div class="backup-card">
    <h4><i class="fas fa-list"></i> Backups Disponibles</h4>
    <?php if (empty($backups)): ?>
        <p style="text-align: center; color: #999; padding: 20px;">No hay backups disponibles</p>
    <?php else: ?>
        <?php foreach ($backups as $backup): ?>
            <div class="backup-item">
                <div class="info">
                    <span class="nombre"><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($backup['nombre']); ?></span>
                    <span class="tamaño"><?php echo number_format($backup['tamaño'] / 1024, 2); ?> KB</span>
                    <span class="fecha"><?php echo date('d/m/Y H:i:s', $backup['fecha']); ?></span>
                </div>
                <div class="acciones">
                    <a href="../../backups/<?php echo urlencode($backup['nombre']); ?>" download class="btn-download">
                        <i class="fas fa-download"></i> Descargar
                    </a>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Estás seguro de eliminar este backup?');">
                        <input type="hidden" name="action" value="delete_backup">
                        <input type="hidden" name="backup" value="<?php echo htmlspecialchars($backup['nombre']); ?>">
                        <button type="submit" class="btn-delete-backup">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>