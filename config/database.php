<?php
// ============================================================
// CONEXIÓN A LA BASE DE DATOS
// Archivo: config/database.php
// ============================================================

// Configuración LOCAL (XAMPP/WAMP)
$host = 'localhost';
$dbname = 'plataforma_estudio';
$username = 'root';
$password = '';
$port = '3306';

// Si estamos en PRODUCCIÓN (Render/Aiven), usar variables de entorno
if (getenv('ENVIRONMENT') === 'production') {
    $host = getenv('DB_HOST') ?: 'mysql-1a3a2fae-stefanimishelticonachura-ebb8.a.aivencloud.com';
    $dbname = getenv('DB_NAME') ?: 'plataforma_estudio';
    $username = getenv('DB_USER') ?: 'avnadmin';
    $password = getenv('DB_PASSWORD') ?: 'AVNS_SM4u1fVUK9uu_xhnKUn';
    $port = getenv('DB_PORT') ?: '18631';
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
    
    // En producción (Aiven), habilitar SSL
    if (getenv('ENVIRONMENT') === 'production') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch(PDOException $e) {
    // En producción, mostrar error genérico (seguridad)
    if (getenv('ENVIRONMENT') === 'production') {
        die("Error de conexión a la base de datos. Por favor, intente más tarde.");
    } else {
        // En desarrollo, mostrar error detallado
        die("Error de conexión: " . $e->getMessage());
    }
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>