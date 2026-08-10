<?php
$page_title = 'Gestión de Materiales';
$page_icon = 'file-alt';

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
// PROCESAR ACCIONES
// =============================================

// ELIMINAR MATERIAL
if (isset($_GET['delete']) && $_GET['delete'] == 'confirm') {
    $id = $_GET['id'] ?? 0;
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT archivo_ruta FROM MaterialEstudio WHERE id = ?");
            $stmt->execute([$id]);
            $material = $stmt->fetch();
            
            if ($material && !empty($material['archivo_ruta'])) {
                $ruta_archivo = __DIR__ . '/../../' . $material['archivo_ruta'];
                if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM MaterialEstudio WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = 'Material eliminado correctamente';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al eliminar material: ' . $e->getMessage();
        }
        header('Location: subir-material.php');
        exit();
    }
}

// PROCESAR CREACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $id_tema = $_POST['id_tema'];
    $tipo = $_POST['tipo'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $url = trim($_POST['url']);
    $duracion_minutos = $_POST['duracion_minutos'] ?? null;
    $orden = $_POST['orden'] ?? 0;
    
    $archivo_ruta = null;
    $archivo_nombre = null;
    $archivo_tipo = null;
    $archivo_tamano = null;
    
    $error = false;
    
    if (empty($id_tema) || empty($tipo) || empty($titulo)) {
        $_SESSION['error'] = 'El tema, tipo y título son obligatorios';
        $error = true;
    }
    
    if (!$error && ($tipo == 'video' || $tipo == 'enlace') && empty($url)) {
        $_SESSION['error'] = 'Para videos y enlaces, la URL es obligatoria';
        $error = true;
    }
    
    if (!$error && isset($_FILES['archivo']) && $_FILES['archivo']['error'] != 4) {
        $archivo = $_FILES['archivo'];
        
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir el archivo';
            $error = true;
        } elseif ($archivo['size'] > 10485760) {
            $_SESSION['error'] = 'El archivo no puede superar los 10MB';
            $error = true;
        } else {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $tipos_permitidos = [];
            
            switch ($tipo) {
                case 'documento':
                    $tipos_permitidos = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];
                    break;
                case 'presentacion':
                    $tipos_permitidos = ['ppt', 'pptx', 'pdf'];
                    break;
                case 'imagen':
                    $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    break;
                case 'otro':
                    $tipos_permitidos = ['zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                    break;
                default:
                    $tipos_permitidos = [];
            }
            
            if (!in_array($extension, $tipos_permitidos)) {
                $_SESSION['error'] = 'Tipo de archivo no permitido';
                $error = true;
            } else {
                $nombre_base = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($archivo['name'], PATHINFO_FILENAME));
                $nombre_final = $nombre_base . '_' . time() . '.' . $extension;
                $upload_dir = __DIR__ . '/../../uploads/materiales/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ruta_destino = $upload_dir . $nombre_final;
                
                if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    $archivo_ruta = 'uploads/materiales/' . $nombre_final;
                    $archivo_nombre = $archivo['name'];
                    $archivo_tipo = $archivo['type'];
                    $archivo_tamano = $archivo['size'];
                } else {
                    $_SESSION['error'] = 'Error al guardar el archivo';
                    $error = true;
                }
            }
        }
    }
    
    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO MaterialEstudio (
                    id_tema, tipo, titulo, descripcion, url, 
                    archivo_ruta, archivo_nombre, archivo_tipo, archivo_tamano,
                    duracion_minutos, orden
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_tema, $tipo, $titulo, $descripcion, $url,
                $archivo_ruta, $archivo_nombre, $archivo_tipo, $archivo_tamano,
                $duracion_minutos, $orden
            ]);
            
            $_SESSION['success'] = 'Material creado correctamente';
            header('Location: subir-material.php');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al crear material: ' . $e->getMessage();
        }
    }
}

// PROCESAR EDICIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $id = $_POST['id'];
    $id_tema = $_POST['id_tema'];
    $tipo = $_POST['tipo'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $url = trim($_POST['url']);
    $duracion_minutos = $_POST['duracion_minutos'] ?? null;
    $orden = $_POST['orden'] ?? 0;
    $mantener_archivo = isset($_POST['mantener_archivo']);
    
    $archivo_ruta = null;
    $archivo_nombre = null;
    $archivo_tipo = null;
    $archivo_tamano = null;
    
    $error = false;
    
    if (empty($id_tema) || empty($tipo) || empty($titulo)) {
        $_SESSION['error'] = 'El tema, tipo y título son obligatorios';
        $error = true;
    }
    
    if (!$error && ($tipo == 'video' || $tipo == 'enlace') && empty($url)) {
        $_SESSION['error'] = 'Para videos y enlaces, la URL es obligatoria';
        $error = true;
    }
    
    if (!$error && isset($_FILES['archivo']) && $_FILES['archivo']['error'] != 4) {
        $archivo = $_FILES['archivo'];
        
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Error al subir el archivo';
            $error = true;
        } elseif ($archivo['size'] > 10485760) {
            $_SESSION['error'] = 'El archivo no puede superar los 10MB';
            $error = true;
        } else {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $tipos_permitidos = [];
            
            switch ($tipo) {
                case 'documento':
                    $tipos_permitidos = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];
                    break;
                case 'presentacion':
                    $tipos_permitidos = ['ppt', 'pptx', 'pdf'];
                    break;
                case 'imagen':
                    $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    break;
                case 'otro':
                    $tipos_permitidos = ['zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                    break;
                default:
                    $tipos_permitidos = [];
            }
            
            if (!in_array($extension, $tipos_permitidos)) {
                $_SESSION['error'] = 'Tipo de archivo no permitido';
                $error = true;
            } else {
                if (!$mantener_archivo) {
                    $stmt = $pdo->prepare("SELECT archivo_ruta FROM MaterialEstudio WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old && !empty($old['archivo_ruta'])) {
                        $ruta_old = __DIR__ . '/../../' . $old['archivo_ruta'];
                        if (file_exists($ruta_old)) {
                            unlink($ruta_old);
                        }
                    }
                }
                
                $nombre_base = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($archivo['name'], PATHINFO_FILENAME));
                $nombre_final = $nombre_base . '_' . time() . '.' . $extension;
                $upload_dir = __DIR__ . '/../../uploads/materiales/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ruta_destino = $upload_dir . $nombre_final;
                
                if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    $archivo_ruta = 'uploads/materiales/' . $nombre_final;
                    $archivo_nombre = $archivo['name'];
                    $archivo_tipo = $archivo['type'];
                    $archivo_tamano = $archivo['size'];
                } else {
                    $_SESSION['error'] = 'Error al guardar el archivo';
                    $error = true;
                }
            }
        }
    } elseif (!$error && $mantener_archivo) {
        $stmt = $pdo->prepare("SELECT archivo_ruta, archivo_nombre, archivo_tipo, archivo_tamano FROM MaterialEstudio WHERE id = ?");
        $stmt->execute([$id]);
        $existente = $stmt->fetch();
        if ($existente) {
            $archivo_ruta = $existente['archivo_ruta'];
            $archivo_nombre = $existente['archivo_nombre'];
            $archivo_tipo = $existente['archivo_tipo'];
            $archivo_tamano = $existente['archivo_tamano'];
        }
    }
    
    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                UPDATE MaterialEstudio SET
                    id_tema = ?, tipo = ?, titulo = ?, descripcion = ?, url = ?,
                    archivo_ruta = ?, archivo_nombre = ?, archivo_tipo = ?, archivo_tamano = ?,
                    duracion_minutos = ?, orden = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $id_tema, $tipo, $titulo, $descripcion, $url,
                $archivo_ruta, $archivo_nombre, $archivo_tipo, $archivo_tamano,
                $duracion_minutos, $orden, $id
            ]);
            
            $_SESSION['success'] = 'Material actualizado correctamente';
            header('Location: subir-material.php');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error al actualizar material: ' . $e->getMessage();
        }
    }
}

// =============================================
// OBTENER DATOS PARA EL FORMULARIO
// =============================================

try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM Materias WHERE id_profesor = ? AND estado = 'activo' ORDER BY nombre");
    $stmt->execute([$usuario_id]);
    $materias = $stmt->fetchAll();
} catch (PDOException $e) {
    $materias = [];
}

// Obtener temas para el modal
$temas_modal = [];
if (isset($_GET['materia_modal']) && $_GET['materia_modal'] > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM Temas WHERE id_materia = ? ORDER BY orden");
        $stmt->execute([$_GET['materia_modal']]);
        $temas_modal = $stmt->fetchAll();
    } catch (PDOException $e) {
        $temas_modal = [];
    }
}

// =============================================
// OBTENER LISTA DE MATERIALES AGRUPADOS
// =============================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            me.*,
            t.nombre as tema_nombre,
            t.id as tema_id,
            m.nombre as materia_nombre,
            m.id as materia_id
        FROM MaterialEstudio me
        JOIN Temas t ON me.id_tema = t.id
        JOIN Materias m ON t.id_materia = m.id
        WHERE m.id_profesor = ?
        ORDER BY m.nombre, t.orden, me.orden
    ");
    $stmt->execute([$usuario_id]);
    $materiales = $stmt->fetchAll();
} catch (PDOException $e) {
    $materiales = [];
}

// Agrupar materiales por materia
$materiales_por_materia = [];
foreach ($materiales as $mat) {
    if (!isset($materiales_por_materia[$mat['materia_id']])) {
        $materiales_por_materia[$mat['materia_id']] = [
            'nombre' => $mat['materia_nombre'],
            'temas' => []
        ];
    }
    if (!isset($materiales_por_materia[$mat['materia_id']]['temas'][$mat['tema_id']])) {
        $materiales_por_materia[$mat['materia_id']]['temas'][$mat['tema_id']] = [
            'nombre' => $mat['tema_nombre'],
            'materiales' => []
        ];
    }
    $materiales_por_materia[$mat['materia_id']]['temas'][$mat['tema_id']]['materiales'][] = $mat;
}

// Para edición: obtener datos del material a editar
$material_editar = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM MaterialEstudio WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $material_editar = $stmt->fetch();
    } catch (PDOException $e) {
        $material_editar = null;
    }
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    /* ===== RESET & BASE ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* ===== CONTENEDOR PRINCIPAL ===== */
    .gestion-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        width: 100%;
    }
    
    .gestion-container h3 {
        font-size: 24px;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .gestion-container h3 i {
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

    .alert i {
        font-size: 20px;
    }

    .alert-error {
        background-color: #fee;
        color: #c33;
        border: 1px solid #fcc;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    /* ===== MATERIAS GRID ===== */
    .materias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }
    
    .materia-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        border-top: 4px solid #9b59b6;
    }
    
    .materia-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 25px rgba(0,0,0,0.12);
    }
    
    .materia-header {
        background: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .materia-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .materia-header h4 i {
        color: #9b59b6;
    }
    
    .materia-header .badge-materia {
        background: #9b59b6;
        color: white;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 12px;
    }
    
    .materia-body {
        padding: 15px 20px;
    }
    
    /* ===== TEMA ITEM ===== */
    .tema-item {
        margin-bottom: 15px;
        border-left: 3px solid #3498db;
        padding-left: 12px;
    }
    
    .tema-item:last-child {
        margin-bottom: 0;
    }
    
    .tema-item .tema-titulo {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .tema-item .tema-titulo .badge-tema {
        background: #e3f2fd;
        color: #1976d2;
        padding: 1px 10px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: normal;
    }
    
    /* ===== MATERIAL ITEM ===== */
    .material-item {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        transition: background 0.2s;
        flex-wrap: wrap;
    }
    
    .material-item:hover {
        background: #f0f0f0;
    }
    
    .material-item .material-info {
        flex: 1;
        min-width: 0;
    }
    
    .material-item .material-info .titulo {
        font-size: 13px;
        font-weight: 500;
        color: #2c3e50;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .material-item .material-info .subtitulo {
        font-size: 11px;
        color: #999;
        display: block;
        margin-top: 2px;
    }
    
    .material-item .material-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    
    .material-item .material-actions .btn-sm {
        padding: 3px 8px;
        font-size: 11px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        transition: all 0.2s;
    }
    
    .material-item .material-actions .btn-sm:hover {
        transform: scale(1.05);
    }
    
    .btn-view-sm { background: #2ecc71; color: white; }
    .btn-view-sm:hover { background: #27ae60; }
    .btn-edit-sm { background: #3498db; color: white; }
    .btn-edit-sm:hover { background: #2980b9; }
    .btn-delete-sm { background: #e74c3c; color: white; }
    .btn-delete-sm:hover { background: #c0392b; }
    
    .badge-tipo {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 500;
    }
    .badge-tipo.video { background: #fce4ec; color: #c62828; }
    .badge-tipo.documento { background: #f3e5f5; color: #7b1fa2; }
    .badge-tipo.presentacion { background: #fff3e0; color: #e65100; }
    .badge-tipo.imagen { background: #e8f5e9; color: #2e7d32; }
    .badge-tipo.enlace { background: #e3f2fd; color: #1976d2; }
    .badge-tipo.otro { background: #eceff1; color: #546e7a; }
    
    .sin-materiales {
        color: #999;
        font-size: 13px;
        font-style: italic;
        padding: 8px 0;
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .empty-state i {
        font-size: 64px;
        color: #ccc;
        display: block;
        margin-bottom: 20px;
    }
    
    .empty-state h4 {
        color: #666;
        margin-bottom: 10px;
        font-size: 20px;
    }
    
    .empty-state p {
        color: #999;
        font-size: 14px;
    }

    /* ===== BOTÓN FLOTANTE ===== */
    .btn-flotante {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #9b59b6;
        color: white;
        border: none;
        border-radius: 50px;
        padding: 15px 25px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(155, 89, 182, 0.4);
        transition: all 0.3s;
        z-index: 999;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-flotante:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 30px rgba(155, 89, 182, 0.5);
        background: #8e44ad;
    }
    
    .btn-flotante:active {
        transform: scale(0.95);
    }
    
    .btn-flotante i {
        font-size: 20px;
    }

    /* ===== MODAL ===== */
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
    
    .modal-overlay.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 15px;
        max-width: 650px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        position: relative;
        animation: modalIn 0.3s ease;
    }
    
    @keyframes modalIn {
        from { transform: translateY(-30px) scale(0.95); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    
    .modal-content .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }
    
    .modal-content .modal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-content .modal-header h3 i {
        color: #9b59b6;
    }
    
    .modal-content .modal-header .btn-close-modal {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        transition: color 0.3s;
        line-height: 1;
        padding: 0 10px;
    }
    
    .modal-content .modal-header .btn-close-modal:hover {
        color: #333;
    }
    
    .modal-content .form-group {
        margin-bottom: 15px;
    }
    
    .modal-content .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #555;
        font-size: 14px;
    }
    
    .modal-content .form-group label .required {
        color: #e74c3c;
    }
    
    .modal-content .form-group input,
    .modal-content .form-group textarea,
    .modal-content .form-group select {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .modal-content .form-group input:focus,
    .modal-content .form-group textarea:focus,
    .modal-content .form-group select:focus {
        outline: none;
        border-color: #9b59b6;
    }
    
    .modal-content .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    
    .modal-content .form-group input[type="file"] {
        padding: 8px;
        border: 2px dashed #e0e0e0;
        background: #f8f9fa;
        cursor: pointer;
    }
    
    .modal-content .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .modal-content .btn-submit-modal {
        background: #9b59b6;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.3s, transform 0.2s;
        width: 100%;
        margin-top: 10px;
    }
    
    .modal-content .btn-submit-modal:hover {
        background: #8e44ad;
        transform: scale(1.01);
    }
    
    .modal-content .btn-submit-modal:active {
        transform: scale(0.98);
    }
    
    .modal-content .info-box {
        background: #f8f9fa;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #9b59b6;
        font-size: 13px;
    }
    
    .modal-content .info-box i {
        color: #9b59b6;
        margin-right: 8px;
    }
    
    .modal-content .file-info {
        font-size: 13px;
        color: #666;
        margin-top: 5px;
    }
    
    .modal-content .tipo-help {
        font-size: 12px;
        color: #999;
        margin-top: 3px;
    }
    
    .modal-content .url-field,
    .modal-content .file-field,
    .modal-content .duracion-field {
        display: none;
    }
    
    .modal-content .url-field.show,
    .modal-content .file-field.show,
    .modal-content .duracion-field.show {
        display: block;
    }
    
    .modal-content .check-mantener {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 5px;
    }
    
    .modal-content .check-mantener input[type="checkbox"] {
        width: auto;
        margin: 0;
    }
    
    .modal-content .check-mantener label {
        font-size: 13px;
        color: #666;
        cursor: pointer;
        margin: 0;
    }
    
    .modal-content .loading-temas {
        color: #999;
        font-size: 13px;
        padding: 5px 0;
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .gestion-container {
            padding: 15px;
        }
        
        .gestion-container h3 {
            font-size: 22px;
        }
        
        .materias-grid {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .gestion-container {
            padding: 12px;
        }
        
        .gestion-container h3 {
            font-size: 20px;
        }
        
        .materias-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .materia-header {
            padding: 12px 15px;
        }
        
        .materia-header h4 {
            font-size: 16px;
        }
        
        .materia-body {
            padding: 12px 15px;
        }
        
        .tema-item {
            padding-left: 8px;
            margin-bottom: 12px;
        }
        
        .tema-item .tema-titulo {
            font-size: 13px;
        }
        
        .material-item {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            padding: 10px 12px;
        }
        
        .material-item .material-info .titulo {
            font-size: 12px;
        }
        
        .material-item .material-info .subtitulo {
            font-size: 10px;
        }
        
        .material-item .material-actions {
            justify-content: flex-end;
            width: 100%;
        }
        
        .material-item .material-actions .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }
        
        .modal-content {
            padding: 20px;
            max-height: 95vh;
        }
        
        .modal-content .modal-header h3 {
            font-size: 17px;
        }
        
        .modal-content .form-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        
        .modal-content .form-group input,
        .modal-content .form-group textarea,
        .modal-content .form-group select {
            font-size: 13px;
            padding: 8px;
        }
        
        .btn-flotante {
            bottom: 20px;
            right: 20px;
            padding: 12px 18px;
            font-size: 14px;
        }
        
        .btn-flotante span {
            display: none;
        }
        
        .btn-flotante i {
            font-size: 24px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .gestion-container {
            padding: 8px;
        }
        
        .gestion-container h3 {
            font-size: 17px;
        }
        
        .materia-card {
            border-radius: 10px;
        }
        
        .materia-header {
            padding: 10px 12px;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .materia-header h4 {
            font-size: 14px;
        }
        
        .materia-header .badge-materia {
            font-size: 10px;
        }
        
        .materia-body {
            padding: 10px 12px;
        }
        
        .tema-item {
            padding-left: 6px;
        }
        
        .tema-item .tema-titulo {
            font-size: 12px;
        }
        
        .tema-item .tema-titulo .badge-tema {
            font-size: 10px;
        }
        
        .material-item {
            padding: 8px 10px;
        }
        
        .material-item .material-info .titulo {
            font-size: 11px;
        }
        
        .material-item .material-info .subtitulo {
            font-size: 9px;
        }
        
        .material-item .material-actions .btn-sm {
            font-size: 10px;
            padding: 3px 8px;
        }
        
        .badge-tipo {
            font-size: 8px;
            padding: 1px 6px;
        }
        
        .modal-content {
            padding: 15px;
            border-radius: 10px;
        }
        
        .modal-content .modal-header {
            flex-wrap: wrap;
        }
        
        .modal-content .modal-header h3 {
            font-size: 15px;
        }
        
        .modal-content .modal-header .btn-close-modal {
            font-size: 24px;
        }
        
        .modal-content .form-group {
            margin-bottom: 10px;
        }
        
        .modal-content .form-group label {
            font-size: 12px;
        }
        
        .modal-content .form-group input,
        .modal-content .form-group textarea,
        .modal-content .form-group select {
            font-size: 12px;
            padding: 6px;
        }
        
        .modal-content .btn-submit-modal {
            font-size: 14px;
            padding: 10px;
        }
        
        .modal-content .info-box {
            font-size: 12px;
            padding: 10px 12px;
        }
        
        .btn-flotante {
            bottom: 15px;
            right: 15px;
            padding: 10px 14px;
        }
        
        .btn-flotante i {
            font-size: 20px;
        }
        
        .empty-state {
            padding: 40px 15px;
        }
        
        .empty-state i {
            font-size: 48px;
        }
        
        .empty-state h4 {
            font-size: 17px;
        }
        
        .empty-state p {
            font-size: 13px;
        }
        
        .alert {
            padding: 10px 14px;
            font-size: 13px;
            border-radius: 8px;
        }
        
        .alert i {
            font-size: 16px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES MUY PEQUEÑOS ===== */
    @media (max-width: 360px) {
        .gestion-container {
            padding: 4px;
        }
        
        .gestion-container h3 {
            font-size: 15px;
        }
        
        .materia-header h4 {
            font-size: 13px;
        }
        
        .material-item .material-info .titulo {
            font-size: 10px;
        }
        
        .material-item .material-actions .btn-sm {
            font-size: 9px;
            padding: 2px 6px;
        }
        
        .modal-content {
            padding: 10px;
        }
        
        .modal-content .modal-header h3 {
            font-size: 13px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .gestion-container {
            padding: 10px;
        }
        
        .materias-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
        
        .modal-content {
            max-height: 95vh;
        }
        
        .modal-content .form-group {
            margin-bottom: 8px;
        }
        
        .modal-content .form-group input,
        .modal-content .form-group textarea,
        .modal-content .form-group select {
            padding: 6px;
        }
        
        .btn-flotante {
            bottom: 15px;
            right: 15px;
            padding: 10px 16px;
        }
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
    html {
        scroll-behavior: smooth;
    }

    /* ===== SELECTION ===== */
    ::selection {
        background: #9b59b6;
        color: white;
    }

    /* ===== UTILITY ===== */
    .hidden {
        display: none !important;
    }
</style>

<div class="gestion-container">
    <h3><i class="fas fa-file-alt"></i> Mis Materiales de Estudio</h3>

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

    <!-- ============================================= -->
    <!-- VISTA POR MATERIAS -->
    <!-- ============================================= -->
    <?php if (empty($materiales_por_materia)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h4>No tienes materiales creados</h4>
            <p>Haz clic en el botón <strong>"+ Nuevo Material"</strong> para comenzar</p>
        </div>
    <?php else: ?>
        <div class="materias-grid">
            <?php foreach ($materiales_por_materia as $materia_id => $materia): ?>
                <div class="materia-card">
                    <div class="materia-header">
                        <h4><i class="fas fa-book"></i><?php echo htmlspecialchars($materia['nombre']); ?></h4>
                        <span class="badge-materia">
                            <?php 
                            $total_materiales = 0;
                            foreach ($materia['temas'] as $tema) {
                                $total_materiales += count($tema['materiales']);
                            }
                            echo $total_materiales . ' materiales';
                            ?>
                        </span>
                    </div>
                    <div class="materia-body">
                        <?php foreach ($materia['temas'] as $tema_id => $tema): ?>
                            <div class="tema-item">
                                <div class="tema-titulo">
                                    <span><i class="fas fa-tag" style="color: #3498db; margin-right: 5px;"></i><?php echo htmlspecialchars($tema['nombre']); ?></span>
                                    <span class="badge-tema"><?php echo count($tema['materiales']); ?> materiales</span>
                                </div>
                                
                                <?php if (empty($tema['materiales'])): ?>
                                    <div class="sin-materiales">No hay materiales en este tema</div>
                                <?php else: ?>
                                    <?php foreach ($tema['materiales'] as $mat): ?>
                                        <div class="material-item">
                                            <div class="material-info">
                                                <span class="titulo">
                                                    <span class="badge-tipo <?php echo $mat['tipo']; ?>"><?php echo ucfirst($mat['tipo']); ?></span>
                                                    <?php echo htmlspecialchars($mat['titulo']); ?>
                                                </span>
                                                <?php if ($mat['descripcion']): ?>
                                                    <span class="subtitulo"><?php echo htmlspecialchars(substr($mat['descripcion'], 0, 60)) . (strlen($mat['descripcion']) > 60 ? '...' : ''); ?></span>
                                                <?php endif; ?>
                                                <?php if ($mat['archivo_tamano']): ?>
                                                    <span class="subtitulo" style="font-size: 10px; color: #bbb;">
                                                        <?php echo number_format($mat['archivo_tamano'] / 1024 / 1024, 2); ?> MB
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="material-actions">
                                                <?php if (!empty($mat['url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($mat['url']); ?>" target="_blank" class="btn-sm btn-view-sm" title="Ver">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                <?php elseif (!empty($mat['archivo_ruta'])): ?>
                                                    <a href="../../<?php echo htmlspecialchars($mat['archivo_ruta']); ?>" target="_blank" class="btn-sm btn-view-sm" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="subir-material.php?edit=<?php echo $mat['id']; ?>" class="btn-sm btn-edit-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="subir-material.php?delete=confirm&id=<?php echo $mat['id']; ?>" class="btn-sm btn-delete-sm" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar este material?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================= -->
<!-- BOTÓN FLOTANTE PARA CREAR MATERIAL -->
<!-- ============================================= -->
<button class="btn-flotante" onclick="abrirModal()">
    <i class="fas fa-plus-circle"></i>
    <span>Nuevo Material</span>
</button>

<!-- ============================================= -->
<!-- MODAL PARA CREAR/EDITAR MATERIAL -->
<!-- ============================================= -->
<div class="modal-overlay" id="modalMaterial">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-<?php echo $material_editar ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $material_editar ? 'Editar Material' : 'Nuevo Material'; ?></h3>
            <button class="btn-close-modal" onclick="cerrarModal()">&times;</button>
        </div>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Tips:</strong> Videos y enlaces usan URL. Documentos, imágenes y otros se suben como archivos.
        </div>

        <form method="POST" enctype="multipart/form-data" id="formMaterialModal">
            <input type="hidden" name="action" value="<?php echo $material_editar ? 'editar' : 'crear'; ?>">
            <?php if ($material_editar): ?>
                <input type="hidden" name="id" value="<?php echo $material_editar['id']; ?>">
            <?php endif; ?>

            <!-- Materia -->
            <div class="form-group">
                <label>Materia <span class="required">*</span></label>
                <select id="materiaSelectModal" name="materia_id" required onchange="cargarTemasModal(this.value)">
                    <option value="">Seleccionar materia...</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?php echo $materia['id']; ?>" 
                            <?php 
                            if ($material_editar) {
                                $stmt = $pdo->prepare("SELECT t.id_materia FROM Temas t WHERE t.id = ?");
                                $stmt->execute([$material_editar['id_tema']]);
                                $materia_edit = $stmt->fetch();
                                echo ($materia_edit && $materia_edit['id_materia'] == $materia['id']) ? 'selected' : '';
                            }
                            ?>>
                            <?php echo htmlspecialchars($materia['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tema -->
            <div class="form-group">
                <label>Tema <span class="required">*</span></label>
                <select id="temaSelectModal" name="id_tema" required>
                    <option value="">Primero selecciona una materia...</option>
                    <?php if ($material_editar): ?>
                        <?php 
                        $stmt = $pdo->prepare("SELECT id, nombre FROM Temas WHERE id = ?");
                        $stmt->execute([$material_editar['id_tema']]);
                        $tema_edit = $stmt->fetch();
                        if ($tema_edit): ?>
                            <option value="<?php echo $tema_edit['id']; ?>" selected>
                                <?php echo htmlspecialchars($tema_edit['nombre']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
                <div id="loadingTemasModal" class="loading-temas" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Cargando temas...
                </div>
            </div>

            <!-- Tipo -->
            <div class="form-group">
                <label>Tipo de Material <span class="required">*</span></label>
                <select id="tipoSelectModal" name="tipo" required onchange="mostrarCampoPorTipoModal()">
                    <option value="">Seleccionar tipo...</option>
                    <option value="video" <?php echo ($material_editar && $material_editar['tipo'] == 'video') ? 'selected' : ''; ?>>🎬 Video (URL)</option>
                    <option value="documento" <?php echo ($material_editar && $material_editar['tipo'] == 'documento') ? 'selected' : ''; ?>>📄 Documento (PDF, Word, Excel)</option>
                    <option value="presentacion" <?php echo ($material_editar && $material_editar['tipo'] == 'presentacion') ? 'selected' : ''; ?>>📊 Presentación (PPT, PPTX)</option>
                    <option value="imagen" <?php echo ($material_editar && $material_editar['tipo'] == 'imagen') ? 'selected' : ''; ?>>🖼️ Imagen (JPG, PNG, GIF)</option>
                    <option value="enlace" <?php echo ($material_editar && $material_editar['tipo'] == 'enlace') ? 'selected' : ''; ?>>🔗 Enlace (URL)</option>
                    <option value="otro" <?php echo ($material_editar && $material_editar['tipo'] == 'otro') ? 'selected' : ''; ?>>📁 Otro (ZIP, etc.)</option>
                </select>
                <div class="tipo-help" id="tipoHelpModal">Selecciona un tipo para ver las opciones</div>
            </div>

            <!-- Campo URL -->
            <div class="form-group url-field <?php echo ($material_editar && ($material_editar['tipo'] == 'video' || $material_editar['tipo'] == 'enlace')) ? 'show' : ''; ?>" id="urlFieldModal">
                <label>URL <span class="required">*</span></label>
                <input type="url" name="url" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo $material_editar ? htmlspecialchars($material_editar['url']) : ''; ?>">
                <div class="file-info"><i class="fas fa-info-circle"></i> Ingresa la URL del video o enlace</div>
            </div>

            <!-- Campo Archivo -->
            <div class="form-group file-field <?php echo ($material_editar && ($material_editar['tipo'] == 'documento' || $material_editar['tipo'] == 'presentacion' || $material_editar['tipo'] == 'imagen' || $material_editar['tipo'] == 'otro')) ? 'show' : ''; ?>" id="fileFieldModal">
                <label>Archivo <span class="required">*</span></label>
                <input type="file" name="archivo" id="archivoInputModal" onchange="mostrarInfoArchivoModal()">
                <div class="file-info" id="fileInfoModal">
                    <i class="fas fa-info-circle"></i> Máximo 10MB
                    <?php if ($material_editar && !empty($material_editar['archivo_nombre'])): ?>
                        <br><i class="fas fa-file"></i> Actual: <?php echo htmlspecialchars($material_editar['archivo_nombre']); ?>
                    <?php endif; ?>
                </div>
                <?php if ($material_editar && !empty($material_editar['archivo_ruta'])): ?>
                    <div class="check-mantener">
                        <input type="checkbox" name="mantener_archivo" id="mantenerArchivoModal" checked>
                        <label for="mantenerArchivoModal">Mantener archivo actual</label>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Título -->
            <div class="form-group">
                <label>Título <span class="required">*</span></label>
                <input type="text" name="titulo" required placeholder="Ej: Introducción a HTML" value="<?php echo $material_editar ? htmlspecialchars($material_editar['titulo']) : ''; ?>">
            </div>

            <!-- Descripción -->
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" placeholder="Breve descripción del material"><?php echo $material_editar ? htmlspecialchars($material_editar['descripcion']) : ''; ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group duracion-field <?php echo ($material_editar && $material_editar['tipo'] == 'video') ? 'show' : ''; ?>" id="duracionFieldModal">
                    <label>Duración (minutos)</label>
                    <input type="number" name="duracion_minutos" placeholder="Solo para videos" min="0" value="<?php echo $material_editar ? $material_editar['duracion_minutos'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Orden</label>
                    <input type="number" name="orden" value="<?php echo $material_editar ? $material_editar['orden'] : '0'; ?>" min="0">
                </div>
            </div>

            <button type="submit" class="btn-submit-modal">
                <i class="fas fa-save"></i> <?php echo $material_editar ? 'Actualizar Material' : 'Crear Material'; ?>
            </button>
        </form>
    </div>
</div>

<script>
// =============================================
// FUNCIONES PARA EL MODAL
// =============================================

function abrirModal() {
    document.getElementById('modalMaterial').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarModal() {
    document.getElementById('modalMaterial').classList.remove('show');
    document.body.style.overflow = '';
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalMaterial').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});

// =============================================
// FUNCIONES PARA CARGAR TEMAS EN EL MODAL
// =============================================

function cargarTemasModal(materiaId) {
    var select = document.getElementById('temaSelectModal');
    var loading = document.getElementById('loadingTemasModal');
    
    if (!materiaId) {
        select.innerHTML = '<option value="">Primero selecciona una materia...</option>';
        return;
    }
    
    loading.style.display = 'block';
    select.disabled = true;
    
    fetch('ajax_get_temas.php?materia_id=' + materiaId)
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">Seleccionar tema...</option>';
            
            if (data.success && data.temas.length > 0) {
                data.temas.forEach(function(tema) {
                    var option = document.createElement('option');
                    option.value = tema.id;
                    option.textContent = tema.nombre;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No hay temas en esta materia</option>';
            }
            
            loading.style.display = 'none';
            select.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            select.innerHTML = '<option value="">Error al cargar temas</option>';
            loading.style.display = 'none';
            select.disabled = false;
        });
}

// =============================================
// FUNCIONES PARA MOSTRAR/OCULTAR CAMPOS EN MODAL
// =============================================

function mostrarCampoPorTipoModal() {
    var tipo = document.getElementById('tipoSelectModal').value;
    var urlField = document.getElementById('urlFieldModal');
    var fileField = document.getElementById('fileFieldModal');
    var duracionField = document.getElementById('duracionFieldModal');
    var tipoHelp = document.getElementById('tipoHelpModal');
    
    urlField.classList.remove('show');
    fileField.classList.remove('show');
    duracionField.classList.remove('show');
    
    if (tipo === 'video' || tipo === 'enlace') {
        urlField.classList.add('show');
        tipoHelp.textContent = tipo === 'video' ? '🎬 Ingresa la URL del video (YouTube, Vimeo, etc.)' : '🔗 Ingresa la URL del enlace';
        if (tipo === 'video') {
            duracionField.classList.add('show');
        }
    } else if (tipo === 'documento' || tipo === 'presentacion' || tipo === 'imagen' || tipo === 'otro') {
        fileField.classList.add('show');
        var tipos = {
            'documento': '📄 PDF, Word, Excel',
            'presentacion': '📊 PPT, PPTX',
            'imagen': '🖼️ JPG, PNG, GIF, WebP',
            'otro': '📁 ZIP, RAR, 7Z, etc.'
        };
        tipoHelp.textContent = 'Selecciona un archivo: ' + (tipos[tipo] || '');
    } else {
        tipoHelp.textContent = 'Selecciona un tipo para ver las opciones';
    }
}

function mostrarInfoArchivoModal() {
    var input = document.getElementById('archivoInputModal');
    var info = document.getElementById('fileInfoModal');
    
    if (input.files && input.files[0]) {
        var file = input.files[0];
        var size = (file.size / 1024 / 1024).toFixed(2);
        info.innerHTML = '<i class="fas fa-check-circle" style="color: #2ecc71;"></i> ' + 
                         file.name + ' (' + size + ' MB)';
    }
}

// =============================================
// INICIALIZAR
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    // Si hay edición, abrir modal
    <?php if ($material_editar): ?>
        abrirModal();
        // Cargar temas de la materia seleccionada
        var materiaSelect = document.getElementById('materiaSelectModal');
        if (materiaSelect.value) {
            cargarTemasModal(materiaSelect.value);
        }
    <?php endif; ?>
    
    // Mostrar campos según tipo en modal
    mostrarCampoPorTipoModal();
});

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>