<?php
// Verificar sesión y rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 3) {
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
    <title>Admin - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== SIDEBAR ADMIN - CON PALETA SUAVE ===== */

        /* ===== VARIABLES ===== */
        :root {
            --primary: #2D8F9E;
            --primary-dark: #1A6B7A;
            --primary-light: #E6F3F5;
            --primary-gradient: linear-gradient(145deg, #2D8F9E 0%, #1A6B7A 50%, #0F4F5E 100%);
            --primary-gradient-soft: linear-gradient(145deg, #4AA3B2 0%, #2D8F9E 50%, #1A6B7A 100%);
            
            --bg-main: #F5F7FA;
            --bg-card: #FFFFFF;
            --bg-input: #F0F4F8;
            
            --text-primary: #1A2A3A;
            --text-secondary: #4A5A6A;
            --text-muted: #8A9AAA;
            --text-light: #FFFFFF;
            
            --border-color: #E2E8F0;
            --shadow: 0 20px 50px -12px rgba(26, 107, 122, 0.15);
            --shadow-hover: 0 30px 60px -12px rgba(26, 107, 122, 0.25);
            --shadow-glow: 0 8px 32px rgba(45, 143, 158, 0.2);
            
            --radius: 20px;
            --radius-sm: 12px;
            --radius-lg: 30px;
            --transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===== SIDEBAR ===== */
        .admin-sidebar {
            width: 260px;
            background: var(--primary-gradient);
            color: var(--text-light);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding-top: 20px;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 4px 0 30px rgba(26, 107, 122, 0.2);
            overflow-y: auto;
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        .admin-sidebar .logo {
            padding: 20px 25px 25px;
            font-size: 22px;
            font-weight: 800;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }

        .admin-sidebar .logo i {
            color: #8FD9E0;
            font-size: 26px;
        }

        .admin-sidebar .logo span {
            background: rgba(255, 255, 255, 0.95);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-sidebar .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-weight: 500;
            font-size: 14px;
            position: relative;
        }

        .admin-sidebar .menu-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
            border-left-color: #8FD9E0;
        }

        .admin-sidebar .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: #FFFFFF;
            border-left-color: #8FD9E0;
        }

        .admin-sidebar .menu-item.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: #8FD9E0;
            border-radius: 4px 0 0 4px;
        }

        .admin-sidebar .menu-item i {
            width: 22px;
            text-align: center;
            font-size: 16px;
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.3s ease;
        }

        .admin-sidebar .menu-item:hover i,
        .admin-sidebar .menu-item.active i {
            color: #8FD9E0;
        }

        .admin-sidebar .menu-divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin: 10px 20px;
        }

        .admin-sidebar .menu-item.logout {
            color: #EF8A8A;
        }

        .admin-sidebar .menu-item.logout:hover {
            background: rgba(239, 138, 138, 0.15);
            color: #FF6B6B;
        }

        /* ===== CONTENIDO PRINCIPAL ===== */
        .admin-content {
            margin-left: 260px;
            padding: 20px 30px 30px;
            min-height: 100vh;
            background: var(--bg-main);
            transition: background var(--transition);
        }

        /* ===== TOPBAR ===== */
        .admin-topbar {
            background: var(--bg-card);
            padding: 16px 28px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            transition: all var(--transition);
        }

        .admin-topbar:hover {
            box-shadow: var(--shadow-hover);
        }

        .admin-topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-topbar h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            transition: color var(--transition);
        }

        .admin-topbar h2 i {
            color: var(--primary);
            font-size: 24px;
        }

        .admin-topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* ===== USUARIO ===== */
        .admin-user {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .admin-user .avatar {
            width: 42px;
            height: 42px;
            background: var(--primary-gradient-soft);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: var(--shadow-glow);
            flex-shrink: 0;
        }

        .admin-user .user-info {
            text-align: right;
        }

        .admin-user .user-info .name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            transition: color var(--transition);
        }

        .admin-user .user-info .role {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            transition: color var(--transition);
        }

        .admin-user .user-info .role i {
            color: var(--primary);
            font-size: 12px;
        }

        /* ===== MENÚ TOGGLE (Mobile) ===== */
        .menu-toggle {
            display: none;
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-glow);
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(45, 143, 158, 0.3);
        }

        .menu-toggle:active {
            transform: scale(0.95);
        }

        /* ===== BADGE ===== */
        .badge-admin {
            background: var(--primary-light);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .admin-sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-content {
                margin-left: 0;
                padding: 15px 20px;
            }
            
            .menu-toggle {
                display: block !important;
            }
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                max-width: 300px;
            }
            
            .admin-content {
                padding: 12px 15px;
            }
            
            .admin-topbar {
                flex-wrap: wrap;
                gap: 12px;
                padding: 14px 18px;
            }

            .admin-topbar-left {
                flex-wrap: wrap;
            }
            
            .admin-topbar h2 {
                font-size: 18px;
            }
            
            .admin-user .user-info .name {
                font-size: 13px;
            }

            .admin-topbar-right {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            .admin-sidebar {
                max-width: 100%;
                width: 100%;
            }
            
            .admin-content {
                padding: 10px 12px;
            }
            
            .admin-topbar {
                padding: 12px 14px;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .admin-topbar h2 {
                font-size: 16px;
            }
            
            .admin-user {
                justify-content: flex-end;
            }
            
            .admin-user .user-info .name {
                font-size: 12px;
            }
            
            .admin-user .user-info .role {
                font-size: 10px;
            }
            
            .admin-user .avatar {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }
            
            .menu-toggle {
                padding: 8px 14px;
                font-size: 18px;
            }
            
            .admin-sidebar .logo {
                font-size: 18px;
                padding: 15px 20px;
            }
            
            .admin-sidebar .menu-item {
                padding: 10px 20px;
                font-size: 13px;
            }

            .admin-topbar-right {
                flex-wrap: wrap;
                gap: 8px;
            }
        }

        /* ===== OVERLAY ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        @media (max-width: 992px) {
            .sidebar-overlay.show {
                display: block;
            }
        }

        @keyframes slideInSidebar {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .admin-sidebar.show {
            animation: slideInSidebar 0.3s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="admin-sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
            <span>Admin Panel</span>
        </div>
        
        <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="usuarios.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Usuarios
        </a>
        <a href="materias.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'materias.php' ? 'active' : ''; ?>">
            <i class="fas fa-book-open"></i> Materias
        </a>
        <a href="roles.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tag"></i> Roles
        </a>
        <a href="auditoria.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'auditoria.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i> Auditoría
        </a>
        <a href="estadisticas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'estadisticas.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Estadísticas
        </a>
        <a href="backup.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i> Backup
        </a>
        
        <hr class="menu-divider">
        
        <a href="perfil.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> Mi Perfil
        </a>
        <a href="../../logout.php" class="menu-item logout">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>

    <!-- Content -->
    <div class="admin-content">
        <div class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2><i class="fas fa-<?php echo $page_icon ?? 'home'; ?>"></i> <?php echo $page_title ?? 'Dashboard'; ?></h2>
            </div>
            <div class="admin-topbar-right">
                <div class="admin-user">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($nombre); ?></div>
                        <div class="role"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($rol); ?></div>
                    </div>
                    <div class="avatar"><?php echo strtoupper(substr($nombre, 0, 1)); ?></div>
                </div>
            </div>
        </div>
        <div id="content-area">