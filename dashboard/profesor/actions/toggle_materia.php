<?php
session_start();
require_once '../../../config/database.php';

$id = $_GET['id'] ?? 0;
$estado = $_GET['estado'] ?? 'activo';
$usuario_id = $_SESSION['usuario_id'];

if ($id > 0) {
    try {
        // Verificar que la materia pertenece al profesor
        $stmt = $pdo->prepare("SELECT id FROM Materias WHERE id = ? AND id_profesor = ?");
        $stmt->execute([$id, $usuario_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE Materias SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
            $_SESSION['success'] = 'Materia ' . ($estado == 'activo' ? 'activada' : 'desactivada') . ' correctamente';
        } else {
            $_SESSION['error'] = 'No tienes permiso para modificar esta materia';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al cambiar estado: ' . $e->getMessage();
    }
}

header('Location: ../mis-materias.php');
exit();
?>