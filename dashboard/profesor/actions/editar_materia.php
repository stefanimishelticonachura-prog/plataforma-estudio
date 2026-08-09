<?php
session_start();
require_once '../../../config/database.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $estado = $_POST['estado'] ?? 'activo';
    $usuario_id = $_SESSION['usuario_id'];
    
    // Temas
    $temas_ids = $_POST['temas_id'] ?? [];
    $temas_nombres = $_POST['temas_nombre_edit'] ?? [];
    $temas_descripciones = $_POST['temas_descripcion_edit'] ?? [];
    $temas_ordenes = $_POST['temas_orden'] ?? [];
    
    if (empty($nombre)) {
        $_SESSION['error'] = 'El nombre de la materia es obligatorio';
        header('Location: ../mis-materias.php');
        exit();
    }
    
    try {
        // Verificar que la materia pertenece al profesor
        $stmt = $pdo->prepare("SELECT id FROM Materias WHERE id = ? AND id_profesor = ?");
        $stmt->execute([$id, $usuario_id]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'No tienes permiso para editar esta materia';
            header('Location: ../mis-materias.php');
            exit();
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // 1. Actualizar materia
        $stmt = $pdo->prepare("
            UPDATE Materias 
            SET nombre = ?, descripcion = ?, estado = ? 
            WHERE id = ? AND id_profesor = ?
        ");
        $stmt->execute([$nombre, $descripcion, $estado, $id, $usuario_id]);
        
        // 2. Obtener IDs de temas actuales
        $stmt = $pdo->prepare("SELECT id FROM Temas WHERE id_materia = ?");
        $stmt->execute([$id]);
        $temas_actuales = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 3. Eliminar temas que ya no están
        $temas_a_mantener = array_filter($temas_ids, function($tema_id) {
            return $tema_id > 0;
        });
        
        $temas_a_eliminar = array_diff($temas_actuales, $temas_a_mantener);
        if (!empty($temas_a_eliminar)) {
            $placeholders = implode(',', array_fill(0, count($temas_a_eliminar), '?'));
            $stmt = $pdo->prepare("DELETE FROM Temas WHERE id IN ($placeholders)");
            $stmt->execute($temas_a_eliminar);
        }
        
        // 4. Insertar o actualizar temas
        $stmt_insert = $pdo->prepare("
            INSERT INTO Temas (id_materia, nombre, descripcion, orden) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_update = $pdo->prepare("
            UPDATE Temas SET nombre = ?, descripcion = ?, orden = ? 
            WHERE id = ? AND id_materia = ?
        ");
        
        foreach ($temas_nombres as $index => $tema_nombre) {
            $tema_nombre = trim($tema_nombre);
            $tema_descripcion = trim($temas_descripciones[$index] ?? '');
            $tema_orden = $index + 1;
            $tema_id = $temas_ids[$index] ?? 0;
            
            if (empty($tema_nombre)) {
                continue;
            }
            
            if ($tema_id > 0) {
                // Actualizar tema existente
                $stmt_update->execute([$tema_nombre, $tema_descripcion, $tema_orden, $tema_id, $id]);
            } else {
                // Insertar nuevo tema
                $stmt_insert->execute([$id, $tema_nombre, $tema_descripcion, $tema_orden]);
            }
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        $_SESSION['success'] = 'Materia y temas actualizados correctamente';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error al actualizar materia: ' . $e->getMessage();
    }
    
    header('Location: ../mis-materias.php');
    exit();
}
?>