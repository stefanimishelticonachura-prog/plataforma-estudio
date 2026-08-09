<?php
session_start();
require_once '../../../config/database.php';

$id = $_GET['id'] ?? 0;
$estado = $_GET['estado'] ?? 'activo';

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE Materias SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        $_SESSION['success'] = 'Materia ' . ($estado == 'activo' ? 'activada' : 'desactivada') . ' correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al cambiar estado: ' . $e->getMessage();
    }
}

header('Location: ../materias.php');
exit();
?>