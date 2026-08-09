<?php
// NO debe haber ningún echo o HTML antes de estas líneas

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 1) {
    header('Location: ../../index.php');
    exit();
}

$nombre = $_SESSION['usuario_nombre'];
$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiante - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .estudiante-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding-top: 20px;
            z-index: 1000;
            transition: all 0.3s;
        }
        .estudiante-sidebar .logo {
            padding: 20px 25px;
            font-size: 22px;
            font-weight: bold;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        .estudiante-sidebar .logo i {
            color: #3498db;
            margin-right: 10px;
        }
        .estudiante-sidebar .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .estudiante-sidebar .menu-item:hover {
            background: #34495e;
            color: white;
            border-left-color: #3498db;
        }
        .estudiante-sidebar .menu-item.active {
            background: #34495e;
            color: white;
            border-left-color: #3498db;
        }
        .estudiante-sidebar .menu-item i {
            width: 20px;
            text-align: center;
        }
        .estudiante-content {
            margin-left: 250px;
            padding: 20px 30px;
            min-height: 100vh;
        }
        .estudiante-topbar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .estudiante-topbar h2 {
            font-size: 22px;
            color: #2c3e50;
        }
        .estudiante-topbar h2 i {
            color: #3498db;
            margin-right: 10px;
        }
        .estudiante-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .estudiante-user .avatar {
            width: 40px;
            height: 40px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        .estudiante-user .user-info {
            text-align: right;
        }
        .estudiante-user .user-info .name {
            font-weight: 600;
            color: #2c3e50;
        }
        .estudiante-user .user-info .role {
            font-size: 12px;
            color: #7f8c8d;
        }
        .estudiante-user .user-info .role i {
            color: #3498db;
        }
        @media (max-width: 768px) {
            .estudiante-sidebar {
                transform: translateX(-100%);
            }
            .estudiante-sidebar.show {
                transform: translateX(0);
            }
            .estudiante-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block !important;
            }
        }
        .menu-toggle {
            display: none;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 20px;
        }
        .badge-estudiante {
            background: #e3f2fd;
            color: #1976d2;
        }
        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-view {
            background: #3498db;
            color: white;
        }
        .btn-view:hover {
            background: #2980b9;
        }
        .btn-materials {
            background: #9b59b6;
            color: white;
        }
        .btn-materials:hover {
            background: #8e44ad;
        }
        .btn-evaluations {
            background: #e67e22;
            color: white;
        }
        .btn-evaluations:hover {
            background: #d35400;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert i {
            font-size: 20px;
        }
        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-activo {
            background: #d4edda;
            color: #155724;
        }
        .badge-inactivo {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="estudiante-sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-user-graduate"></i> Estudiante
        </div>
        <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="mis-materias.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'mis-materias.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> Mis Materias
        </a>
        <a href="material-estudio.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'material-estudio.php' ? 'active' : ''; ?>">
            <i class="fas fa-video"></i> Material Estudio
        </a>
        <a href="evaluaciones.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'evaluaciones.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks"></i> Evaluaciones
        </a>
        <a href="foros.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'foros.php' ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i> Foros
        </a>
        <a href="progreso.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'progreso.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Mi Progreso
        </a>
        <a href="calificaciones.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'calificaciones.php' ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> Calificaciones
        </a>
        <hr style="border-color: #34495e; margin: 10px 20px;">
        <a href="perfil.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> Mi Perfil
        </a>
        <a href="../../logout.php" class="menu-item" style="color: #e74c3c;">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>

    <!-- Content -->
    <div class="estudiante-content">
        <div class="estudiante-topbar">
            <div>
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2><i class="fas fa-<?php echo $page_icon ?? 'user-graduate'; ?>"></i> <?php echo $page_title ?? 'Dashboard'; ?></h2>
            </div>
            <div class="estudiante-user">
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($nombre); ?></div>
                    <div class="role"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($rol); ?></div>
                </div>
                <div class="avatar"><?php echo strtoupper(substr($nombre, 0, 1)); ?></div>
            </div>
        </div>
        <div id="content-area">

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
</script>