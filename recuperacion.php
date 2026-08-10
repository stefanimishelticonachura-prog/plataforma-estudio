<?php
// ============================================================
// RECUPERAR CONTRASEÑA - VERSIÓN SIMPLIFICADA PARA RENDER
// ============================================================

// ========== CONFIGURACIÓN ==========
$email_origen = "medacademy2000@gmail.com";
$clave_app = "eepv tcoz pydf llcd";
$url_login = "https://plataforma-estudio-9eot.onrender.com/";

// ========== VERIFICAR QUE PHPMailer EXISTE ==========
$phpmailer_file = __DIR__ . '/PHPMailer/src/PHPMailer.php';

if (!file_exists($phpmailer_file)) {
    die('❌ PHPMailer no encontrado en: ' . $phpmailer_file);
}

require_once $phpmailer_file;
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ========== CONEXIÓN A LA BASE DE DATOS ==========
$host = getenv('DB_HOST') ?: 'mysql-1a3a2fae-stefanimishelticonachura-ebb8.aivencloud.com';
$dbname = getenv('DB_NAME') ?: 'plataforma_estudio';
$username = getenv('DB_USER') ?: 'avnadmin';
$password = getenv('DB_PASSWORD') ?: 'AVNS_SM4ulfvUK9uu_xhnKUn';
$port = getenv('DB_PORT') ?: '18631';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("❌ Error de conexión a BD: " . $e->getMessage());
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
                
                $mail = new PHPMailer(true);
                
                try {
                    // Configuración SMTP
                    $mail->SMTPDebug = 0;
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $email_origen;
                    $mail->Password = str_replace(' ', '', $clave_app);
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                    
                    $mail->setFrom($email_origen, 'MEDACADEMY');
                    $mail->addAddress($correo_usuario, $nombre_completo);
                    $mail->isHTML(true);
                    $mail->Subject = '🔑 Recuperación de Contraseña - MEDACADEMY';
                    
                    $mail->Body = "
                    <html>
                    <body>
                        <h2>¡Hola " . htmlspecialchars($nombre_completo) . "!</h2>
                        <p>Tu nueva contraseña es:</p>
                        <h1 style='background:#f0f0f0;padding:10px;'>" . htmlspecialchars($nueva_password) . "</h1>
                        <a href='" . htmlspecialchars($url_login) . "'>Iniciar sesión</a>
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
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - MEDACADEMY</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
        }
        h2 {
            text-align: center;
            color: #0A8E8C;
        }
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 600;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        input[type="email"]:focus {
            border-color: #0A8E8C;
            outline: none;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #0A8E8C;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn:hover {
            background: #06706E;
        }
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        .emails {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0 20px;
        }
        .emails span {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid #ddd;
        }
        .emails span:hover {
            border-color: #0A8E8C;
            color: #0A8E8C;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #0A8E8C;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔑 Recuperar Contraseña</h2>
        <p class="subtitle">Te enviaremos una nueva contraseña a tu correo</p>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>📧 Correo electrónico</label>
                <input type="email" name="email" placeholder="ejemplo@gmail.com" value="<?php echo htmlspecialchars($correo_ingresado); ?>" required>
            </div>

            <div class="emails">
                <span onclick="document.querySelector('input[name=email]').value='medel@gmail.com'">medel@gmail.com</span>
                <span onclick="document.querySelector('input[name=email]').value='mishel@gmail.com'">mishel@gmail.com</span>
                <span onclick="document.querySelector('input[name=email]').value='laura@gmail.com'">laura@gmail.com</span>
                <span onclick="document.querySelector('input[name=email]').value='cristal@gmail.com'">cristal@gmail.com</span>
                <span onclick="document.querySelector('input[name=email]').value='yo@gmail.com'">yo@gmail.com</span>
            </div>

            <button type="submit" class="btn">📩 Enviar nueva contraseña</button>
        </form>

        <div class="login-link">
            <a href="<?php echo htmlspecialchars($url_login); ?>">← Volver al inicio de sesión</a>
        </div>
    </div>
</body>
</html>
