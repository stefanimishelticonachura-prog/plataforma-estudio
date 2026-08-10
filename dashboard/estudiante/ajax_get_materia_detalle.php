<?php
header('Content-Type: application/json');

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id = $_GET['id'] ?? 0;
$usuario_id = $_SESSION['usuario_id'];

if ($id == 0) {
    echo json_encode(['success' => false, 'message' => 'ID de materia no válido']);
    exit();
}

try {
    // Obtener datos de la materia
    $stmt = $pdo->prepare("
        SELECT m.*, CONCAT(u.nombre, ' ', u.apellido) as profesor, u.correo as profesor_correo
        FROM Materias m
        JOIN Usuarios u ON m.id_profesor = u.id
        WHERE m.id = ? AND m.estado = 'activo'
    ");
    $stmt->execute([$id]);
    $materia = $stmt->fetch();
    
    if (!$materia) {
        echo json_encode(['success' => false, 'message' => 'Materia no encontrada']);
        exit();
    }
    
    // Verificar si el usuario ya está inscrito
    $stmt = $pdo->prepare("SELECT id FROM Inscripciones WHERE id_usuario = ? AND id_materia = ?");
    $stmt->execute([$usuario_id, $id]);
    $inscrito = $stmt->fetch() ? true : false;
    $materia['inscrito'] = $inscrito;
    
    // Obtener temas
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion, orden FROM Temas WHERE id_materia = ? ORDER BY orden");
    $stmt->execute([$id]);
    $temas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'materia' => $materia,
        'temas' => $temas
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>