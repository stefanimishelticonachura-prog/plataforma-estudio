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

$materia_id = $_GET['materia_id'] ?? 0;
$usuario_id = $_SESSION['usuario_id'];

if ($materia_id == 0) {
    echo json_encode(['success' => false, 'message' => 'ID de materia no válido']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id FROM Materias WHERE id = ? AND id_profesor = ?");
    $stmt->execute([$materia_id, $usuario_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para ver los temas de esta materia']);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT id, nombre FROM Temas WHERE id_materia = ? ORDER BY orden");
    $stmt->execute([$materia_id]);
    $temas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'temas' => $temas
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>