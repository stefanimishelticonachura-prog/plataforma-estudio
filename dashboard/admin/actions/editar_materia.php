<?php
session_start();
require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_profesor = $_POST['id_profesor'];
    $estado = $_POST['estado'] ?? 'activo';
    
    if (empty($nombre) || empty($id_profesor)) {
        $_SESSION['error'] = 'El nombre y el profesor son obligatorios';
        header('Location: ../materias.php');
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE Materias 
            SET nombre = ?, descripcion = ?, id_profesor = ?, estado = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $descripcion, $id_profesor, $estado, $id]);
        
        $_SESSION['success'] = 'Materia actualizada correctamente';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al actualizar materia: ' . $e->getMessage();
    }
    
    header('Location: ../materias.php');
    exit();
}
?>