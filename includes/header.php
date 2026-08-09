<?php
// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

$nombre = $_SESSION['usuario_nombre'];
$rol = $_SESSION['usuario_rol'];
$rol_id = $_SESSION['usuario_rol_id'];
$usuario_id = $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - Plataforma</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <h1><i class="fas fa-graduation-cap"></i> Plataforma de Estudio</h1>
            </div>
            <div class="header-right">
                <div class="user-badge">
                    <span class="user-avatar">
                        <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                    </span>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($nombre); ?></span>
                        <span class="user-role role-<?php echo strtolower($rol); ?>">
                            <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($rol); ?>
                        </span>
                    </div>
                </div>
                <a href="../../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </header>