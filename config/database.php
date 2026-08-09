<?php
// ============================================================
// CONEXIÓN A LA BASE DE DATOS (VERSIÓN ROBUSTA PARA RENDER + AIVEN)
// Archivo: config/database.php
// ============================================================

// Configuración LOCAL (XAMPP/WAMP) - Por defecto
$host = 'localhost';
$dbname = 'plataforma_estudio';
$username = 'root';
$password = '';
$port = '3306';

// Si estamos en PRODUCCIÓN (Render), usar variables de entorno
if (getenv('ENVIRONMENT') === 'production') {
    $host     = getenv('DB_HOST')     ?: 'mysql-1a3a2fae-stefanimishelticonachura-ebb8.a.aivencloud.com';
    $dbname   = getenv('DB_NAME')     ?: 'plataforma_estudio';
    $username = getenv('DB_USER')     ?: 'avnadmin';
    $password = getenv('DB_PASSWORD') ?: 'AVNS_SM4ulfvUK9uu_xhnKUn';
    $port     = getenv('DB_PORT')     ?: '18631';
}

try {
    // Construir la conexión DSN
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Configurar opciones PDO
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    // En producción (Aiven), activar SSL con el certificado que ya trae Render instalado
    if (getenv('ENVIRONMENT') === 'production') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    // Intentar la conexión
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Si llegamos hasta aquí, la conexión fue EXITOSA (Para pruebas)
    if (getenv('ENVIRONMENT') === 'production') {
        echo "<h1 style='color:green; text-align:center; margin-top:50px;'>✅ CONEXIÓN A LA BASE DE DATOS EXITOSA</h1>";
        echo "<p style='text-align:center;'>La página debería cargar ahora. Si ves esto, el problema de conexión está solucionado.</p>";
        exit(); // Detenemos la ejecución para que veas el mensaje de éxito.
    }
    
} catch(PDOException $e) {
    // FORZAR A VER EL ERROR EN LA PÁGINA WEB
    if (getenv('ENVIRONMENT') === 'production') {
        die("<h2 style='color:red;'>ERROR REAL DE CONEXIÓN:</h2><pre style='background:#f4f4f4; padding:15px; border:1px solid #ddd;'>" . $e->getMessage() . "</pre>");
    } else {
        die("Error de conexión local: " . $e->getMessage());
    }
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
