<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $usuario_id = $_SESSION['usuario_id'];
        
        // Obtener datos de la materia
        $stmt = $pdo->prepare("SELECT * FROM Materias WHERE id = ? AND id_profesor = ?");
        $stmt->execute([$id, $usuario_id]);
        $materia = $stmt->fetch();
        
        if ($materia) {
            // Obtener temas de la materia
            $stmt_temas = $pdo->prepare("SELECT id, nombre, descripcion, orden FROM Temas WHERE id_materia = ? ORDER BY orden");
            $stmt_temas->execute([$id]);
            $temas = $stmt_temas->fetchAll();
            
            echo json_encode([
                'success' => true,
                'materia' => $materia,
                'temas' => $temas
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Materia no encontrada']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID no válido']);
}
?>