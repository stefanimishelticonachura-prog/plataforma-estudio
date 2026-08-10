<?php
// ============================================================
// RECUPERAR CONTRASEÑA - MEDACADEMY
// ============================================================

// ========== CONFIGURACIÓN ==========
$email_origen = "medacademy2000@gmail.com";
$clave_app = "eepv tcoz pydf llcd";
$url_login = "https://plataforma-estudio-9eot.onrender.com/";

// ========== CARGAR PHPMailer ==========
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
require_once '../../config/database.php';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("❌ Error de conexión: " . $e->getMessage());
}

// ========== PROCESAR EL FORMULARIO ==========
$mensaje = '';
$tipo_mensaje = '';
$correo_ingresado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo_usuario = trim($_POST['email'] ?? '');
    $correo_ingresado = $correo_usuario;
    
    if (empty($correo_usuario)) {
        $mensaje = 'Por favor, ingresa tu correo electrónico';
        $tipo_mensaje = 'error';
    } 
    elseif (!filter_var($correo_usuario, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Formato de correo inválido';
        $tipo_mensaje = 'error';
    } 
    else {
        try {
            // Buscar usuario
            $sql = "SELECT id, nombre, apellido, correo FROM Usuarios WHERE correo = ? AND activo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$correo_usuario]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                $mensaje = 'No existe una cuenta con este correo';
                $tipo_mensaje = 'error';
            } 
            else {
                // Generar nueva contraseña
                $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $nueva_password = substr(str_shuffle($caracteres), 0, 10);
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                
                // Actualizar en BD
                $sql = "UPDATE Usuarios SET password = ? WHERE correo = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$password_hash, $correo_usuario]);
                
                // Enviar correo con PHPMailer
                $nombre_completo = $usuario['nombre'] . ' ' . $usuario['apellido'];
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    // Configuración SMTP
                    $mail->SMTPDebug = 0;
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $email_origen;
                    $mail->Password = str_replace(' ', '', $clave_app);
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    $mail->setFrom($email_origen, 'MEDACADEMY');
                    $mail->addAddress($correo_usuario, $nombre_completo);
                    $mail->isHTML(true);
                    $mail->Subject = '🔑 Recuperación de Contraseña - MEDACADEMY';
                    
                    $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Nueva Contraseña</title>
                    </head>
                    <body style='font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background-color: #F0F4F8; margin: 0; padding: 40px 20px;'>
                        <div style='max-width: 560px; margin: 0 auto; background: #FFFFFF; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);'>
                            <!-- Header -->
                            <div style='background: linear-gradient(135deg, #0A8E8C 0%, #06706E 100%); padding: 35px 40px 30px; text-align: center;'>
                                <h1 style='color: #FFFFFF; font-size: 28px; font-weight: 800; margin: 0; letter-spacing: -0.5px;'>
                                    MEDACADEMY
                                    <span style='display: block; font-weight: 300; font-size: 14px; letter-spacing: 6px; opacity: 0.85; margin-top: 6px;'>PREPÁRATE PARA TU FUTURO MÉDICO</span>
                                </h1>
                            </div>
                            
                            <!-- Content -->
                            <div style='padding: 35px 40px 30px;'>
                                <h2 style='color: #1A202C; font-size: 22px; margin: 0 0 8px 0;'>¡Hola " . htmlspecialchars($nombre_completo) . "!</h2>
                                <p style='color: #4A5568; font-size: 15px; margin: 0 0 25px 0; line-height: 1.6;'>Has solicitado recuperar tu contraseña en <strong>MEDACADEMY</strong>. Tu nueva contraseña temporal es:</p>
                                
                                <!-- Password Box -->
                                <div style='background: #F7FAFC; border: 2px solid #E2E8F0; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 25px;'>
                                    <p style='margin: 0 0 8px 0; color: #A0AEC0; font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;'>🔑 Nueva Contraseña</p>
                                    <div style='font-size: 32px; font-weight: 700; color: #0A8E8C; letter-spacing: 4px; font-family: 'Courier New', monospace; background: #E8F5F4; padding: 12px; border-radius: 8px; display: inline-block; min-width: 200px;'>
                                        " . htmlspecialchars($nueva_password) . "
                                    </div>
                                </div>
                                
                                <!-- Warning Box -->
                                <div style='background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 15px 18px; border-radius: 8px; margin-bottom: 28px;'>
                                    <p style='margin: 0; font-size: 13px; color: #92400E; line-height: 1.5;'>
                                        <strong>⚠️ Importante:</strong> Esta contraseña es temporal. Te recomendamos cambiarla después de iniciar sesión por seguridad.
                                    </p>
                                </div>
                                
                                <!-- Button -->
                                <div style='text-align: center; margin: 30px 0 25px;'>
                                    <a href='" . htmlspecialchars($url_login) . "' 
                                       style='display: inline-block; background: linear-gradient(135deg, #0A8E8C 0%, #06706E 100%); color: #FFFFFF; padding: 14px 45px; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 16px; transition: all 0.3s; box-shadow: 0 8px 25px rgba(10, 142, 140, 0.3);'>
                                        🔐 Iniciar Sesión
                                    </a>
                                </div>
                                
                                <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 25px 0 15px;'>
                                
                                <p style='color: #A0AEC0; font-size: 12px; text-align: center; margin: 0; line-height: 1.6;'>
                                    Este es un correo automático. No respondas a este mensaje.<br>
                                    © 2026 MEDACADEMY - Todos los derechos reservados.
                                </p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mail->send();
                    $mensaje = '¡Listo! Se ha enviado una nueva contraseña a tu correo.';
                    $tipo_mensaje = 'exito';
                    $correo_ingresado = '';
                    
                } catch (Exception $e) {
                    $mensaje = 'Error al enviar el correo: ' . $mail->ErrorInfo;
                    $tipo_mensaje = 'error';
                }
            }
        } catch (PDOException $e) {
            $mensaje = 'Error en la base de datos: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - MEDACADEMY</title>
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
            
            --success-bg: #D1FAE5;
            --success-text: #065F46;
            --success-border: #10B981;
            --error-bg: #FEE2E2;
            --error-text: #991B1B;
            --error-border: #EF4444;
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
            
            --success-bg: #1B2D24;
            --success-text: #6EE7B7;
            --error-bg: #2D1B1B;
            --error-text: #FCA5A5;
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
        .recover-wrapper {
            width: 100%;
            max-width: 1000px;
            min-height: 550px;
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            overflow: hidden;
            transition: all var(--transition);
            position: relative;
        }

        .recover-wrapper:hover {
            box-shadow: var(--shadow-hover);
        }

        /* ===== LEFT SIDE - IMAGE ===== */
        .recover-image {
            background: var(--primary-gradient);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            min-height: 400px;
        }

        .recover-image::before {
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

        .recover-image::after {
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
            font-size: 1rem;
            letter-spacing: 6px;
            opacity: 0.8;
            margin-top: 8px;
        }

        .image-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .image-content .big-icon {
            font-size: 5rem;
            color: rgba(255, 255, 255, 0.15);
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
        .recover-form {
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--bg-card);
            transition: background var(--transition);
        }

        /* Theme Toggle */
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
            user-select: none;
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
            margin-bottom: 30px;
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
            display: <?php echo $mensaje ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
            border-left: 4px solid var(--error-border);
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
            border-left: 4px solid var(--success-border);
        }

        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
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

        .input-wrapper input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Email list hint */
        .email-hint {
            margin-top: 15px;
            padding: 14px 16px;
            background: var(--bg-input);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .email-hint p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0 0 6px 0;
            font-weight: 600;
        }

        .email-hint .emails {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .email-hint .emails span {
            font-size: 0.8rem;
            color: var(--text-muted);
            background: var(--bg-card);
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            font-family: 'Courier New', monospace;
            transition: all var(--transition);
        }

        .email-hint .emails span:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Botón */
        .btn-recover {
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
            margin-top: 5px;
        }

        .btn-recover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10, 142, 140, 0.35);
        }

        .btn-recover:active {
            transform: translateY(0);
        }

        .btn-recover:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-recover i {
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 820px) {
            .recover-wrapper {
                grid-template-columns: 1fr;
                max-width: 520px;
                min-height: auto;
                border-radius: 16px;
            }

            .recover-image {
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

            .recover-form {
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

        @media (max-width: 480px) {
            body {
                padding: 10px;
                align-items: flex-start;
                padding-top: 20px;
            }

            .recover-wrapper {
                border-radius: 12px;
                max-width: 100%;
            }
            
            .recover-image {
                min-height: 140px;
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

            .recover-form {
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

            .btn-recover {
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
            
            .alert {
                padding: 10px 14px;
                font-size: 0.8rem;
                border-radius: 8px;
            }
            
            .login-link {
                font-size: 0.8rem;
                margin-top: 20px;
            }
            
            .email-hint {
                padding: 10px 12px;
            }
            
            .email-hint p {
                font-size: 0.7rem;
            }
            
            .email-hint .emails span {
                font-size: 0.7rem;
                padding: 3px 10px;
            }
        }

        @media (max-width: 360px) {
            body {
                padding: 6px;
                padding-top: 12px;
            }
            
            .recover-image {
                min-height: 110px;
                padding: 14px 14px 14px;
            }
            
            .image-logo h1 {
                font-size: 1.1rem;
            }
            
            .image-logo h1 span {
                font-size: 0.6rem;
                letter-spacing: 2px;
            }
            
            .recover-form {
                padding: 18px 14px;
            }
            
            .form-header h2 {
                font-size: 1.1rem;
            }
            
            .input-wrapper input {
                padding: 10px 10px 10px 8px;
                font-size: 0.8rem;
            }
            
            .btn-recover {
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

        @media (max-height: 600px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding: 10px;
            }
            
            .recover-wrapper {
                max-width: 700px;
                min-height: auto;
                grid-template-columns: 1fr 1.2fr;
            }
            
            .recover-image {
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
            
            .recover-form {
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
    <div class="recover-wrapper">
        <!-- LEFT: IMAGE SIDE -->
        <div class="recover-image">
            <div class="image-logo">
                <h1>
                    MEDACADEMY
                    <span>PREPÁRATE PARA TU FUTURO MÉDICO</span>
                </h1>
            </div>

            <div class="image-content">
                <div class="big-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Recuperar Contraseña</h2>
                <p>Te enviaremos una nueva contraseña temporal a tu correo electrónico.</p>
            </div>

            <div class="image-badge">
                <span class="dot"></span>
                <span>Seguridad y confianza</span>
            </div>
        </div>

        <!-- RIGHT: FORM SIDE -->
        <div class="recover-form">
            <!-- Theme Toggle -->
            <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" aria-label="Cambiar tema">
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
                <h2>🔑 Recuperar Contraseña</h2>
                <p>Ingresa tu correo y te enviaremos una nueva contraseña</p>
            </div>

            <!-- Alertas -->
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <i class="fas fa-<?php echo $tipo_mensaje === 'exito' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" action="" id="recoverForm" autocomplete="off">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Correo electrónico
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="ejemplo@email.com" 
                            value="<?php echo htmlspecialchars($correo_ingresado); ?>"
                            required
                            autocomplete="email"
                            <?php echo $tipo_mensaje === 'exito' ? 'disabled' : ''; ?>
                        >
                    </div>
                </div>

                <button type="submit" class="btn-recover" id="submitBtn" <?php echo $tipo_mensaje === 'exito' ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i>
                    <?php echo $tipo_mensaje === 'exito' ? '¡Enviado con éxito!' : 'Enviar nueva contraseña'; ?>
                </button>
            </form>



            <div class="login-link">
                ¿Recordaste tu contraseña? <a href="<?php echo htmlspecialchars($url_login); ?>">Inicia sesión aquí</a>
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

            // ---------- OCULTAR ALERTA AL ESCRIBIR ----------
            const emailInput = document.getElementById('email');
            const alertElement = document.querySelector('.alert');

            if (emailInput && alertElement) {
                emailInput.addEventListener('input', function() {
                    alertElement.style.display = 'none';
                });
            }

            // ---------- AUTO-LLENAR CORREOS DE PRUEBA (CLICK) ----------
            document.querySelectorAll('.emails span').forEach(function(el) {
                el.style.cursor = 'pointer';
                el.addEventListener('click', function() {
                    if (!emailInput.disabled) {
                        emailInput.value = this.textContent.trim();
                        emailInput.focus();
                        
                        // Disparar evento input para ocultar alerta
                        const event = new Event('input', { bubbles: true });
                        emailInput.dispatchEvent(event);
                    }
                });
            });

            // ---------- VALIDACIÓN CLIENTE ----------
            document.getElementById('recoverForm').addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email) {
                    e.preventDefault();
                    alert('Por favor, ingresa tu correo electrónico.');
                    document.getElementById('email').focus();
                    return;
                }
                
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Por favor, ingresa un correo electrónico válido.');
                    document.getElementById('email').focus();
                    return;
                }
            });

            // ---------- PREVENIR DOBLE ENVÍO ----------
            const form = document.getElementById('recoverForm');
            const submitBtn = document.getElementById('submitBtn');
            let isSubmitting = false;

            form.addEventListener('submit', function() {
                if (isSubmitting) {
                    e.preventDefault();
                    return;
                }
                isSubmitting = true;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            });

            // Restaurar si hay error
            <?php if ($tipo_mensaje === 'error'): ?>
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar nueva contraseña';
            <?php endif; ?>

        })();
    </script>
</body>
</html>
