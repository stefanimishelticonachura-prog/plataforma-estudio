<?php
session_start();
require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $id_rol = $_POST['id_rol'];
    $activo = $_POST['activo'] ?? 1;
    
    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($correo)) {
        $_SESSION['error'] = 'Los campos nombre, apellido y correo son obligatorios';
        header('Location: ../usuarios.php');
        exit();
    }
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Correo electrónico inválido';
        header('Location: ../usuarios.php');
        exit();
    }
    
    try {
        // Verificar si el correo ya existe (excepto el mismo usuario)
        $stmt = $pdo->prepare("SELECT id FROM Usuarios WHERE correo = ? AND id != ?");
        $stmt->execute([$correo, $id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'El correo ya está registrado por otro usuario';
            header('Location: ../usuarios.php');
            exit();
        }
        
        // Actualizar usuario
        $stmt = $pdo->prepare("
            UPDATE Usuarios 
            SET nombre = ?, apellido = ?, correo = ?, id_rol = ?, activo = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $apellido, $correo, $id_rol, $activo, $id]);
        
        $_SESSION['success'] = 'Usuario actualizado correctamente';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al actualizar usuario: ' . $e->getMessage();
    }
    
    header('Location: ../usuarios.php');
    exit();
}
?>