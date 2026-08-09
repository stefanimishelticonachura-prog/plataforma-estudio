<?php
session_start();
require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $_SESSION['error'] = 'Todos los campos son obligatorios';
        header('Location: ../perfil.php');
        exit();
    }
    
    if ($password_nueva !== $password_confirmar) {
        $_SESSION['error'] = 'Las contraseñas no coinciden';
        header('Location: ../perfil.php');
        exit();
    }
    
    if (strlen($password_nueva) < 8) {
        $_SESSION['error'] = 'La nueva contraseña debe tener al menos 8 caracteres';
        header('Location: ../perfil.php');
        exit();
    }
    
    try {
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM Usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
            $_SESSION['error'] = 'Contraseña actual incorrecta';
            header('Location: ../perfil.php');
            exit();
        }
        
        // Actualizar contraseña
        $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE Usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$password_hash, $usuario_id]);
        
        $_SESSION['success'] = 'Contraseña actualizada correctamente';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al cambiar contraseña: ' . $e->getMessage();
    }
    
    header('Location: ../perfil.php');
    exit();
}
?>