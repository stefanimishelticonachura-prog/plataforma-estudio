<?php
// NO debe haber ningún echo o HTML antes de estas líneas

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
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
    <title>Profesor - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profesor-sidebar {
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
        .profesor-sidebar .logo {
            padding: 20px 25px;
            font-size: 22px;
            font-weight: bold;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        .profesor-sidebar .logo i {
            color: #9b59b6;
            margin-right: 10px;
        }
        .profesor-sidebar .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .profesor-sidebar .menu-item:hover {
            background: #34495e;
            color: white;
            border-left-color: #9b59b6;
        }
        .profesor-sidebar .menu-item.active {
            background: #34495e;
            color: white;
            border-left-color: #9b59b6;
        }
        .profesor-sidebar .menu-item i {
            width: 20px;
            text-align: center;
        }
        .profesor-content {
            margin-left: 250px;
            padding: 20px 30px;
            min-height: 100vh;
        }
        .profesor-topbar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .profesor-topbar h2 {
            font-size: 22px;
            color: #2c3e50;
        }
        .profesor-topbar h2 i {
            color: #9b59b6;
            margin-right: 10px;
        }
        .profesor-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .profesor-user .avatar {
            width: 40px;
            height: 40px;
            background: #9b59b6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        .profesor-user .user-info {
            text-align: right;
        }
        .profesor-user .user-info .name {
            font-weight: 600;
            color: #2c3e50;
        }
        .profesor-user .user-info .role {
            font-size: 12px;
            color: #7f8c8d;
        }
        .profesor-user .user-info .role i {
            color: #9b59b6;
        }
        @media (max-width: 768px) {
            .profesor-sidebar {
                transform: translateX(-100%);
            }
            .profesor-sidebar.show {
                transform: translateX(0);
            }
            .profesor-content {
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
        .badge-profesor {
            background: #f3e5f5;
            color: #7b1fa2;
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
        .btn-edit {
            background: #3498db;
            color: white;
        }
        .btn-edit:hover {
            background: #2980b9;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        .btn-delete:hover {
            background: #c0392b;
        }
        .btn-toggle {
            background: #f39c12;
            color: white;
        }
        .btn-toggle:hover {
            background: #e67e22;
        }
        .btn-view {
            background: #9b59b6;
            color: white;
        }
        .btn-view:hover {
            background: #8e44ad;
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
    <div class="profesor-sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-chalkboard-teacher"></i> Profesor
        </div>
        <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="mis-materias.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'mis-materias.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> Mis Materias
        </a>
        <a href="crear-materia.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'crear-materia.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i> Crear Materia
        </a>
        <a href="subir-material.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'subir-material.php' ? 'active' : ''; ?>">
            <i class="fas fa-upload"></i> Gestión de Materiales
        </a>
        <a href="crear-evaluacion.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'crear-evaluacion.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-signature"></i> Crear Evaluación
        </a>
        <a href="corregir-evaluaciones.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'corregir-evaluaciones.php' ? 'active' : ''; ?>">
            <i class="fas fa-check-double"></i> Corregir Evaluaciones
        </a>
        <a href="ver-estudiantes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'ver-estudiantes.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Ver Estudiantes
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
    <div class="profesor-content">
        <div class="profesor-topbar">
            <div>
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2><i class="fas fa-<?php echo $page_icon ?? 'chalkboard-teacher'; ?>"></i> <?php echo $page_title ?? 'Dashboard'; ?></h2>
            </div>
            <div class="profesor-user">
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