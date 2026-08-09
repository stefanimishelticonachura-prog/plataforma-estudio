<?php
session_start();
require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $id_rol = $_POST['id_rol'];
    $activo = $_POST['activo'] ?? 1;
    
    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($correo) || empty($password)) {
        $_SESSION['error'] = 'Todos los campos son obligatorios';
        header('Location: ../usuarios.php');
        exit();
    }
    
    // Validar correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Correo electrónico inválido';
        header('Location: ../usuarios.php');
        exit();
    }
    
    try {
        // Verificar si el correo ya existe
        $stmt = $pdo->prepare("SELECT id FROM Usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'El correo ya está registrado';
            header('Location: ../usuarios.php');
            exit();
        }
        
        // Hashear contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertar usuario
        $stmt = $pdo->prepare("
            INSERT INTO Usuarios (nombre, apellido, correo, password, id_rol, activo) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $apellido, $correo, $password_hash, $id_rol, $activo]);
        
        $_SESSION['success'] = 'Usuario creado correctamente';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al crear usuario: ' . $e->getMessage();
    }
    
    header('Location: ../usuarios.php');
    exit();
}
?>