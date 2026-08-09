<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($correo) || empty($password)) {
        $_SESSION['error'] = 'Por favor, complete todos los campos';
        header('Location: index.php');
        exit();
    }
    
    try {
        // Buscar usuario por correo
        $stmt = $pdo->prepare("
            SELECT u.*, r.nombre as rol_nombre 
            FROM Usuarios u
            JOIN Roles r ON u.id_rol = r.id
            WHERE u.correo = ? AND u.activo = 1
        ");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Verificar si el usuario está bloqueado
            if ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time()) {
                $_SESSION['error'] = 'Cuenta bloqueada temporalmente. Intente más tarde.';
                header('Location: index.php');
                exit();
            }
            
            // Verificar contraseña (password es 'password' en los datos de prueba)
            if (password_verify($password, $usuario['password'])) {
                // Login exitoso
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                $_SESSION['usuario_rol'] = $usuario['rol_nombre'];
                $_SESSION['usuario_rol_id'] = $usuario['id_rol'];
                
                // Resetear intentos fallidos
                $stmt = $pdo->prepare("UPDATE Usuarios SET intentos_fallidos = 0, ultimo_acceso = NOW() WHERE id = ?");
                $stmt->execute([$usuario['id']]);
                
                // Registrar acceso exitoso en IntentosAcceso
                $stmt = $pdo->prepare("
                    INSERT INTO IntentosAcceso (correo, ip_usuario, exito) 
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([$correo, $_SERVER['REMOTE_ADDR']]);
                
                $_SESSION['success'] = "✅ ¡Bienvenido! Usuario con rol '{$usuario['rol_nombre']}' se logueó correctamente.";
                header('Location: dashboard.php');
                exit();
            } else {
                // Contraseña incorrecta - incrementar intentos
                $stmt = $pdo->prepare("
                    UPDATE Usuarios 
                    SET intentos_fallidos = intentos_fallidos + 1,
                        bloqueado_hasta = CASE 
                            WHEN intentos_fallidos + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                            ELSE NULL 
                        END
                    WHERE id = ?
                ");
                $stmt->execute([$usuario['id']]);
                
                // Registrar acceso fallido
                $stmt = $pdo->prepare("
                    INSERT INTO IntentosAcceso (correo, ip_usuario, exito) 
                    VALUES (?, ?, 0)
                ");
                $stmt->execute([$correo, $_SERVER['REMOTE_ADDR']]);
                
                $_SESSION['error'] = '❌ Contraseña incorrecta';
                header('Location: index.php');
                exit();
            }
        } else {
            // Usuario no encontrado
            $_SESSION['error'] = '❌ Usuario no encontrado o inactivo';
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error en el sistema. Intente más tarde.';
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>