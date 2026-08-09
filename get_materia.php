<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Materias WHERE id = ?");
        $stmt->execute([$id]);
        $materia = $stmt->fetch();
        
        if ($materia) {
            echo json_encode([
                'success' => true,
                'materia' => $materia
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