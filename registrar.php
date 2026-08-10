<?php
// Incluir la conexión a la base de datos
require_once 'config/database.php';

// Verificar que la conexión existe (usando $pdo que es la variable que defines en database.php)
if (!isset($pdo)) {
    die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
}

// Si ya está logueado, redirigir al dashboard del estudiante
if (isset($_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT r.nombre FROM Usuarios u JOIN Roles r ON u.id_rol = r.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $rol = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rol) {
            if ($rol['nombre'] === 'estudiante') {
                header('Location: dashboard/estudiante/index.php');
            } elseif ($rol['nombre'] === 'profesor') {
                header('Location: dashboard/profesor/index.php');
            } elseif ($rol['nombre'] === 'admin') {
                header('Location: dashboard/admin/index.php');
            } else {
                header('Location: dashboard.php');
            }
        } else {
            header('Location: index.php');
        }
    } catch (PDOException $e) {
        header('Location: index.php');
    }
    exit();
}

$error = '';
$success = '';
$nombre = '';
$apellido = '';
$correo = '';

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $terminos = isset($_POST['terminos']) ? true : false;

    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($correo) || empty($password) || empty($confirmar_password)) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!$terminos) {
        $error = 'Debes aceptar los términos y condiciones.';
    } elseif (strlen($nombre) < 2 || strlen($apellido) < 2) {
        $error = 'El nombre y apellido deben tener al menos 2 caracteres.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, ingresa un correo electrónico válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirmar_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            // Verificar si el correo ya existe
            $stmt = $pdo->prepare("SELECT id FROM Usuarios WHERE correo = ?");
            $stmt->execute([$correo]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Este correo electrónico ya está registrado.';
            } else {
                // Obtener el ID del rol 'estudiante'
                $stmt = $pdo->prepare("SELECT id FROM Roles WHERE nombre = 'estudiante'");
                $stmt->execute();
                $rol = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$rol) {
                    $error = 'Error: El rol "estudiante" no existe en la base de datos.';
                } else {
                    $id_rol = $rol['id'];
                    
                    // Hashear la contraseña con bcrypt
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar el nuevo usuario
                    $stmt = $pdo->prepare("
                        INSERT INTO Usuarios (nombre, apellido, correo, password, id_rol, activo) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    
                    if ($stmt->execute([$nombre, $apellido, $correo, $password_hash, $id_rol])) {
                        $success = '¡Registro exitoso! Ahora puedes iniciar sesión.';
                        // Limpiar campos
                        $nombre = $apellido = $correo = '';
                        
                        // Redirigir al login después de 2 segundos
                        header("Refresh: 2; url=index.php");
                    } else {
                        $error = 'Error al registrar el usuario. Por favor, intenta de nuevo.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Error en la base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEDACADEMY - Registro de Estudiante</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== CSS VARIABLES ===== */
        :root {
            --primary: #0A8E8C;
            --primary-dark: #06706E;
            --primary-light: #E8F5F4;
            --primary-gradient: linear-gradient(135deg, #0A8E8C 0%, #06706E 100%);
            
            --bg-main: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-input: #F7FAFC;
            
            --text-primary: #1A202C;
            --text-secondary: #4A5568;
            --text-muted: #A0AEC0;
            
            --border-color: #E2E8F0;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            --shadow-hover: 0 30px 60px -12px rgba(10, 142, 140, 0.3);
            
            --radius: 20px;
            --transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===== DARK MODE ===== */
        [data-theme="dark"] {
            --bg-main: #0D1117;
            --bg-card: #161B22;
            --bg-input: #0D1117;
            
            --text-primary: #F0F6FC;
            --text-secondary: #C9D1D9;
            --text-muted: #8B949E;
            
            --border-color: #30363D;
            --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            --shadow-hover: 0 30px 60px -12px rgba(10, 142, 140, 0.2);
            
            --primary-light: #1A2E2D;
        }

        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background var(--transition), color var(--transition);
            margin: 0;
        }

        /* ===== MAIN CONTAINER ===== */
        .register-wrapper {
            width: 100%;
            max-width: 1200px;
            min-height: 650px;
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            transition: all var(--transition);
            position: relative;
        }

        .register-wrapper:hover {
            box-shadow: var(--shadow-hover);
        }

        /* ===== LEFT SIDE - IMAGE ===== */
        .register-image {
            background: var(--primary-gradient);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            min-height: 400px;
        }

        .register-image::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: floatBg 15s ease-in-out infinite;
        }

        .register-image::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 70%;
            height: 70%;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            animation: floatBg 20s ease-in-out infinite reverse;
        }

        @keyframes floatBg {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -30px) scale(1.1); }
        }

        /* Logo en la imagen */
        .image-logo {
            position: relative;
            z-index: 2;
        }

        .image-logo h1 {
            color: #FFFFFF;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .image-logo h1 span {
            display: block;
            font-weight: 300;
            font-size: 1.1rem;
            letter-spacing: 6px;
            opacity: 0.8;
            margin-top: 8px;
        }

        .image-logo .subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 1rem;
            font-weight: 300;
            margin-top: 12px;
            letter-spacing: 2px;
        }

        /* Contenido central de la imagen */
        .image-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .image-content .big-icon {
            font-size: 5rem;
            color: rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .image-content h2 {
            color: #FFFFFF;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .image-content p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.05rem;
            font-weight: 300;
            max-width: 80%;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Badge inferior */
        .image-badge {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            font-weight: 300;
        }

        .image-badge .dot {
            width: 8px;
            height: 8px;
            background: #4ADE80;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        /* ===== RIGHT SIDE - FORM ===== */
        .register-form {
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--bg-card);
            transition: background var(--transition);
        }

        /* Toggle Theme */
        .theme-toggle {
            align-self: flex-end;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all var(--transition);
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }

        .theme-toggle:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 142, 140, 0.15);
        }

        .theme-toggle i {
            font-size: 1.1rem;
            transition: all var(--transition);
        }

        .theme-toggle .toggle-track {
            width: 40px;
            height: 22px;
            background: var(--border-color);
            border-radius: 50px;
            position: relative;
            transition: background var(--transition);
            flex-shrink: 0;
        }

        .theme-toggle .toggle-thumb {
            width: 18px;
            height: 18px;
            background: var(--primary);
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: all var(--transition);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        [data-theme="dark"] .theme-toggle .toggle-thumb {
            left: 20px;
        }

        [data-theme="dark"] .theme-toggle .toggle-track {
            background: var(--primary);
        }

        /* Form Header */
        .form-header {
            margin-bottom: 35px;
        }

        .form-header .brand-small {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .form-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
        }

        /* Alertas */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #10B981;
        }

        [data-theme="dark"] .alert-error {
            background: #2D1B1B;
            color: #FCA5A5;
        }

        [data-theme="dark"] .alert-success {
            background: #1B2D24;
            color: #6EE7B7;
        }

        /* Formulario */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--primary);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all var(--transition);
        }

        .input-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(10, 142, 140, 0.12);
        }

        .input-wrapper .input-icon {
            padding: 0 0 0 16px;
            color: var(--text-muted);
            font-size: 1rem;
            transition: color var(--transition);
        }

        .input-wrapper:focus-within .input-icon {
            color: var(--primary);
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 12px;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            color: var(--text-primary);
            outline: none;
            font-family: inherit;
        }

        .input-wrapper input::placeholder {
            color: var(--text-muted);
            font-weight: 300;
            font-size: 0.9rem;
        }

        /* Password toggle */
        .toggle-password {
            padding: 0 16px 0 8px;
            color: var(--text-muted);
            cursor: pointer;
            transition: color var(--transition);
            background: transparent;
            border: none;
            font-size: 1rem;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        /* Password hint */
        .password-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-hint i {
            color: var(--text-muted);
        }

        /* Opciones del formulario */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .form-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .form-options label input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .form-options a {
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all var(--transition);
        }

        .form-options a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Botón Register */
        .btn-register {
            width: 100%;
            padding: 15px;
            background: var(--primary-gradient);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.5px;
            font-family: inherit;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10, 142, 140, 0.35);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .btn-register i {
            font-size: 1.1rem;
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .login-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition);
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* ===== RESPONSIVE - TABLETS ===== */
        @media (max-width: 1024px) {
            .register-wrapper {
                max-width: 900px;
            }
            
            .register-image {
                padding: 40px 30px;
            }
            
            .register-form {
                padding: 40px 35px;
            }
            
            .image-logo h1 {
                font-size: 2.2rem;
            }
            
            .image-content h2 {
                font-size: 1.8rem;
            }
            
            .image-content .big-icon {
                font-size: 4rem;
            }
        }

        /* ===== RESPONSIVE - TABLETS PEQUEÑAS Y MÓVILES GRANDES ===== */
        @media (max-width: 820px) {
            .register-wrapper {
                grid-template-columns: 1fr;
                max-width: 550px;
                min-height: auto;
                border-radius: 16px;
            }

            .register-image {
                min-height: 200px;
                padding: 30px 30px 25px;
                border-radius: 16px 16px 0 0;
            }

            .image-content {
                display: none;
            }

            .image-logo h1 {
                font-size: 1.8rem;
            }

            .image-logo h1 span {
                font-size: 0.8rem;
                letter-spacing: 4px;
            }
            
            .image-logo .subtitle {
                font-size: 0.85rem;
            }

            .register-form {
                padding: 35px 30px;
                border-radius: 0 0 16px 16px;
            }

            .form-header h2 {
                font-size: 1.5rem;
            }
            
            .form-header p {
                font-size: 0.9rem;
            }
            
            .image-badge {
                font-size: 0.75rem;
            }
        }

        /* ===== RESPONSIVE - MÓVILES ===== */
        @media (max-width: 480px) {
            body {
                padding: 10px;
                align-items: flex-start;
                padding-top: 20px;
            }

            .register-wrapper {
                border-radius: 12px;
                max-width: 100%;
            }
            
            .register-image {
                min-height: 150px;
                padding: 20px 20px 18px;
                border-radius: 12px 12px 0 0;
            }

            .image-logo h1 {
                font-size: 1.4rem;
            }

            .image-logo h1 span {
                font-size: 0.7rem;
                letter-spacing: 3px;
                margin-top: 4px;
            }

            .image-logo .subtitle {
                font-size: 0.7rem;
                margin-top: 6px;
                letter-spacing: 1px;
            }

            .register-form {
                padding: 25px 18px;
                border-radius: 0 0 12px 12px;
            }
            
            .form-header {
                margin-bottom: 25px;
            }
            
            .form-header .brand-small {
                font-size: 0.75rem;
                gap: 6px;
            }

            .form-header h2 {
                font-size: 1.3rem;
            }

            .form-header p {
                font-size: 0.8rem;
            }

            .form-group {
                margin-bottom: 16px;
            }
            
            .form-group label {
                font-size: 0.75rem;
            }
            
            .input-wrapper input {
                padding: 12px 12px 12px 10px;
                font-size: 0.85rem;
            }
            
            .input-wrapper .input-icon {
                padding: 0 0 0 12px;
                font-size: 0.85rem;
            }

            .form-options {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
                margin-bottom: 20px;
            }
            
            .form-options label {
                font-size: 0.8rem;
            }
            
            .form-options a {
                font-size: 0.8rem;
            }

            .btn-register {
                padding: 13px;
                font-size: 0.9rem;
                border-radius: 10px;
            }

            .theme-toggle {
                font-size: 0.7rem;
                padding: 5px 10px;
                gap: 6px;
                margin-bottom: 20px;
                align-self: flex-start;
            }

            .theme-toggle .toggle-track {
                width: 30px;
                height: 18px;
            }

            .theme-toggle .toggle-thumb {
                width: 14px;
                height: 14px;
                top: 2px;
                left: 2px;
            }

            [data-theme="dark"] .theme-toggle .toggle-thumb {
                left: 14px;
            }
            
            .theme-toggle i {
                font-size: 0.9rem;
            }
            
            /* Alertas en móvil */
            .alert {
                padding: 10px 14px;
                font-size: 0.8rem;
                border-radius: 8px;
            }
            
            .password-hint {
                font-size: 0.7rem;
            }
            
            .login-link {
                font-size: 0.8rem;
                margin-top: 20px;
            }
        }

        /* ===== RESPONSIVE - MÓVILES MUY PEQUEÑOS ===== */
        @media (max-width: 360px) {
            body {
                padding: 6px;
                padding-top: 12px;
            }
            
            .register-image {
                min-height: 120px;
                padding: 14px 14px 14px;
            }
            
            .image-logo h1 {
                font-size: 1.1rem;
            }
            
            .image-logo h1 span {
                font-size: 0.6rem;
                letter-spacing: 2px;
            }
            
            .image-logo .subtitle {
                font-size: 0.6rem;
                margin-top: 4px;
            }
            
            .register-form {
                padding: 18px 14px;
            }
            
            .form-header h2 {
                font-size: 1.1rem;
            }
            
            .input-wrapper input {
                padding: 10px 10px 10px 8px;
                font-size: 0.8rem;
            }
            
            .btn-register {
                padding: 11px;
                font-size: 0.8rem;
            }
            
            .image-badge {
                font-size: 0.6rem;
                gap: 6px;
            }
            
            .image-badge .dot {
                width: 6px;
                height: 6px;
            }
        }

        /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL EN MÓVILES ===== */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding: 10px;
            }
            
            .register-wrapper {
                max-width: 700px;
                min-height: auto;
                grid-template-columns: 1fr 1.2fr;
            }
            
            .register-image {
                min-height: 100%;
                padding: 25px 20px;
            }
            
            .image-content {
                display: none;
            }
            
            .image-logo h1 {
                font-size: 1.4rem;
            }
            
            .image-logo h1 span {
                font-size: 0.7rem;
            }
            
            .image-logo .subtitle {
                font-size: 0.7rem;
            }
            
            .register-form {
                padding: 25px 20px;
            }
            
            .form-header {
                margin-bottom: 15px;
            }
            
            .form-header h2 {
                font-size: 1.3rem;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            .theme-toggle {
                margin-bottom: 15px;
            }
        }

        /* ===== UTILITY ===== */
        .hidden {
            display: none !important;
        }

        /* ===== MEJORAS DE ACCESIBILIDAD ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <!-- LEFT: IMAGE SIDE -->
        <div class="register-image">
            <div class="image-logo">
                <h1>
                    MEDACADEMY
                    <span>PREPÁRATE PARA TU FUTURO MÉDICO</span>
                </h1>
            </div>

            <div class="image-content">
                <div class="big-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Únete a MEDACADEMY</h2>
                <p>Crea tu cuenta como estudiante y comienza tu preparación para el examen de residencia médica.</p>
            </div>

            <div class="image-badge">
                <span class="dot"></span>
                <span>Comienza tu preparación hoy</span>
            </div>
        </div>

        <!-- RIGHT: FORM SIDE -->
        <div class="register-form">
            <!-- Theme Toggle -->
            <div class="theme-toggle" id="themeToggle" role="button" tabindex="0">
                <i class="fas fa-sun" id="iconSun"></i>
                <div class="toggle-track">
                    <div class="toggle-thumb"></div>
                </div>
                <i class="fas fa-moon" id="iconMoon"></i>
            </div>

            <!-- Form Header -->
            <div class="form-header">
                <div class="brand-small">
                    <i class="fas fa-graduation-cap"></i>
                    MEDACADEMY
                </div>
                <h2>Registro de Estudiante</h2>
                <p>Crea tu cuenta para acceder a todos los recursos</p>
            </div>

            <!-- Alertas -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form action="registrar.php" method="POST" id="registerForm" autocomplete="off">
                <div class="form-group">
                    <label for="nombre">
                        <i class="fas fa-user"></i> Nombre
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="nombre" name="nombre" required 
                               placeholder="Tu nombre" value="<?php echo htmlspecialchars($nombre); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="apellido">
                        <i class="fas fa-user"></i> Apellido
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="apellido" name="apellido" required 
                               placeholder="Tu apellido" value="<?php echo htmlspecialchars($apellido); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="correo">
                        <i class="fas fa-envelope"></i> Correo electrónico
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="correo" name="correo" required 
                               placeholder="ejemplo@email.com" value="<?php echo htmlspecialchars($correo); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" id="password" name="password" required 
                               placeholder="Mínimo 8 caracteres">
                        <button type="button" class="toggle-password" id="togglePassword1" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i>
                        La contraseña debe tener al menos 8 caracteres
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmar_password">
                        <i class="fas fa-check-circle"></i> Confirmar contraseña
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-check"></i></span>
                        <input type="password" id="confirmar_password" name="confirmar_password" required 
                               placeholder="Confirma tu contraseña">
                        <button type="button" class="toggle-password" id="togglePassword2" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label for="terminos">
                        <input type="checkbox" id="terminos" name="terminos" required>
                        Acepto los <a href="#" style="color: var(--primary);">términos y condiciones</a>
                    </label>
                </div>

                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i>
                    Registrarse como Estudiante
                </button>
            </form>

            <div class="login-link">
                ¿Ya tienes una cuenta? <a href="index.php">Inicia sesión aquí</a>
            </div>
        </div>
    </div>

    <!-- ===== JAVASCRIPT ===== -->
    <script>
        (function() {
            'use strict';

            // ---------- TEMA OSCURO/CLARO ----------
            const themeToggle = document.getElementById('themeToggle');
            const html = document.documentElement;
            const iconSun = document.getElementById('iconSun');
            const iconMoon = document.getElementById('iconMoon');

            // Cargar tema guardado
            const savedTheme = localStorage.getItem('medacademy-theme') || 'light';
            html.setAttribute('data-theme', savedTheme);
            updateIcons(savedTheme);

            themeToggle.addEventListener('click', function() {
                const current = html.getAttribute('data-theme');
                const next = current === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', next);
                localStorage.setItem('medacademy-theme', next);
                updateIcons(next);
            });

            // Soporte teclado
            themeToggle.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    themeToggle.click();
                }
            });

            function updateIcons(theme) {
                if (theme === 'dark') {
                    iconSun.style.opacity = '0.5';
                    iconMoon.style.opacity = '1';
                } else {
                    iconSun.style.opacity = '1';
                    iconMoon.style.opacity = '0.5';
                }
            }

            // ---------- MOSTRAR/OCULTAR CONTRASEÑA ----------
            const togglePassword1 = document.getElementById('togglePassword1');
            const togglePassword2 = document.getElementById('togglePassword2');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmar_password');

            togglePassword1.addEventListener('click', function() {
                toggleVisibility(passwordInput, this);
            });

            togglePassword2.addEventListener('click', function() {
                toggleVisibility(confirmPasswordInput, this);
            });

            function toggleVisibility(input, button) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                button.querySelector('i').classList.toggle('fa-eye');
                button.querySelector('i').classList.toggle('fa-eye-slash');
            }

            // ---------- VALIDACIÓN CLIENTE ----------
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                const nombre = document.getElementById('nombre').value.trim();
                const apellido = document.getElementById('apellido').value.trim();
                const correo = document.getElementById('correo').value.trim();
                const password = document.getElementById('password').value;
                const confirmar = document.getElementById('confirmar_password').value;
                const terminos = document.getElementById('terminos').checked;

                // Validar nombre y apellido
                if (nombre.length < 2) {
                    e.preventDefault();
                    alert('El nombre debe tener al menos 2 caracteres.');
                    document.getElementById('nombre').focus();
                    return;
                }

                if (apellido.length < 2) {
                    e.preventDefault();
                    alert('El apellido debe tener al menos 2 caracteres.');
                    document.getElementById('apellido').focus();
                    return;
                }

                // Validar correo
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(correo)) {
                    e.preventDefault();
                    alert('Por favor, ingresa un correo electrónico válido.');
                    document.getElementById('correo').focus();
                    return;
                }

                // Validar contraseña
                if (password.length < 8) {
                    e.preventDefault();
                    alert('La contraseña debe tener al menos 8 caracteres.');
                    document.getElementById('password').focus();
                    return;
                }

                if (password !== confirmar) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden.');
                    document.getElementById('confirmar_password').focus();
                    return;
                }

                if (!terminos) {
                    e.preventDefault();
                    alert('Debes aceptar los términos y condiciones.');
                    document.getElementById('terminos').focus();
                    return;
                }
            });

            // ---------- MOSTRAR CORREO SI HAY ERROR ----------
            const urlParams = new URLSearchParams(window.location.search);
            const errorParam = urlParams.get('error');
            if (errorParam) {
                const correoInput = document.getElementById('correo');
                const savedCorreo = localStorage.getItem('medacademy_last_correo');
                if (savedCorreo) {
                    correoInput.value = savedCorreo;
                }
            }

        })();
    </script>
</body>
</html>