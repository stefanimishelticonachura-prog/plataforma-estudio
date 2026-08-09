<?php
session_start();
require_once '../../../config/database.php';

$id = $_GET['id'] ?? 0;
$estado = $_GET['estado'] ?? 1;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE Usuarios SET activo = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        $_SESSION['success'] = 'Usuario ' . ($estado ? 'activado' : 'desactivado') . ' correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al cambiar estado: ' . $e->getMessage();
    }
}

header('Location: ../usuarios.php');
exit();
?>