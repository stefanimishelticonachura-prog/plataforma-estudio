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
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profesor - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="../../css/style.css">
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
            --radius-sm: 12px;
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
            transition: background var(--transition), color var(--transition);
        }

        /* ===== SIDEBAR ===== */
        .profesor-sidebar {
            width: 260px;
            background: var(--bg-card);
            color: var(--text-primary);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding-top: 0;
            z-index: 1000;
            transition: all var(--transition);
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: var(--shadow);
            border-right: 1px solid var(--border-color);
        }

        .profesor-sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .profesor-sidebar::-webkit-scrollbar-track {
            background: var(--bg-card);
        }

        .profesor-sidebar::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .profesor-sidebar .logo {
            padding: 25px 25px 20px;
            font-size: 22px;
            font-weight: bold;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(0,0,0,0.02);
        }

        .profesor-sidebar .logo i {
            color: var(--primary);
            font-size: 28px;
        }

        .profesor-sidebar .logo span {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .profesor-sidebar .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all var(--transition);
            border-left: 3px solid transparent;
            font-size: 14px;
            position: relative;
        }

        .profesor-sidebar .menu-item:hover {
            background: var(--bg-input);
            color: var(--text-primary);
            border-left-color: var(--primary);
        }

        .profesor-sidebar .menu-item.active {
            background: var(--bg-input);
            color: var(--text-primary);
            border-left-color: var(--primary);
        }

        .profesor-sidebar .menu-item.active::after {
            content: '';
            position: absolute;
            right: 15px;
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
        }

        .profesor-sidebar .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
            color: var(--text-muted);
        }

        .profesor-sidebar .menu-item:hover i,
        .profesor-sidebar .menu-item.active i {
            color: var(--primary);
        }

        .profesor-sidebar .menu-item .badge-side {
            margin-left: auto;
            background: var(--primary-gradient);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .profesor-sidebar hr {
            border-color: var(--border-color);
            margin: 10px 20px;
            opacity: 0.5;
        }

        .profesor-sidebar .menu-item.logout {
            color: #e74c3c;
        }

        .profesor-sidebar .menu-item.logout:hover {
            background: #FEE2E2;
            border-left-color: #EF4444;
        }

        .profesor-sidebar .menu-item.logout i {
            color: #e74c3c;
        }

        [data-theme="dark"] .profesor-sidebar .menu-item.logout:hover {
            background: #2D1B1B;
            border-left-color: #EF4444;
        }

        /* ===== BOTÓN HAMBURGUESA ===== */
        .menu-toggle {
            display: none;
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 20px;
            transition: all var(--transition);
            margin-right: 15px;
            box-shadow: var(--shadow);
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-hover);
        }

        .menu-toggle:active {
            transform: scale(0.95);
        }

        /* ===== OVERLAY PARA MÓVIL ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(4px);
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* ===== CONTENT ===== */
        .profesor-content {
            margin-left: 260px;
            padding: 20px 30px;
            min-height: 100vh;
            width: calc(100% - 260px);
            transition: all var(--transition);
            background: var(--bg-main);
        }

        /* ===== TOPBAR ===== */
        .profesor-topbar {
            background: var(--bg-card);
            padding: 15px 25px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            transition: all var(--transition);
        }

        .profesor-topbar:hover {
            box-shadow: var(--shadow-hover);
        }

        .profesor-topbar .left-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profesor-topbar h2 {
            font-size: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profesor-topbar h2 i {
            color: var(--primary);
        }

        .profesor-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profesor-user .avatar {
            width: 42px;
            height: 42px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 2px 10px rgba(10, 142, 140, 0.3);
        }

        .profesor-user .user-info {
            text-align: right;
        }

        .profesor-user .user-info .name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .profesor-user .user-info .role {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .profesor-user .user-info .role i {
            color: var(--primary);
        }

        /* ===== BOTONES ===== */
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all var(--transition);
            font-weight: 500;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-sm:active {
            transform: translateY(0);
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
            background: var(--primary-gradient);
            color: white;
        }
        .btn-view:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .btn-primary-sm {
            background: var(--primary-gradient);
            color: white;
        }
        .btn-primary-sm:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        /* ===== ALERTAS ===== */
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert i {
            font-size: 20px;
        }

        .alert-error {
            background-color: #FEE2E2;
            color: #991B1B;
            border-left: 4px solid #EF4444;
        }

        .alert-success {
            background-color: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #10B981;
        }

        .alert-info {
            background-color: #DBEAFE;
            color: #1E40AF;
            border-left: 4px solid #3B82F6;
        }

        [data-theme="dark"] .alert-error {
            background-color: #2D1B1B;
            color: #FCA5A5;
        }

        [data-theme="dark"] .alert-success {
            background-color: #1B2D24;
            color: #6EE7B7;
        }

        [data-theme="dark"] .alert-info {
            background-color: #1A2744;
            color: #93C5FD;
        }

        /* ===== BADGES ===== */
        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-activo {
            background: #D1FAE5;
            color: #065F46;
        }

        .badge-inactivo {
            background: #FEE2E2;
            color: #991B1B;
        }

        .badge-profesor {
            background: var(--primary-light);
            color: var(--primary);
        }

        .badge-pendiente {
            background: #FEF3C7;
            color: #92400E;
        }

        .badge-completado {
            background: #D1FAE5;
            color: #065F46;
        }

        [data-theme="dark"] .badge-activo {
            background: #1B2D24;
            color: #6EE7B7;
        }

        [data-theme="dark"] .badge-inactivo {
            background: #2D1B1B;
            color: #FCA5A5;
        }

        [data-theme="dark"] .badge-profesor {
            background: var(--primary-light);
            color: var(--primary);
        }

        [data-theme="dark"] .badge-pendiente {
            background: #2D251B;
            color: #FCD34D;
        }

        [data-theme="dark"] .badge-completado {
            background: #1B2D24;
            color: #6EE7B7;
        }

        /* ===== RESPONSIVE - TABLETS ===== */
        @media (max-width: 1024px) {
            .profesor-content {
                padding: 15px 20px;
            }
            
            .profesor-topbar {
                padding: 12px 20px;
            }
            
            .profesor-topbar h2 {
                font-size: 18px;
            }
        }

        /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
        @media (max-width: 820px) {
            .menu-toggle {
                display: block;
            }

            .profesor-sidebar {
                transform: translateX(-100%);
                width: 280px;
                background: var(--bg-card);
            }

            .profesor-sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.active {
                display: block;
            }

            .profesor-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .profesor-topbar {
                flex-direction: row;
                padding: 12px 15px;
            }

            .profesor-topbar .left-section {
                flex: 1;
            }

            .profesor-topbar h2 {
                font-size: 16px;
            }

            .profesor-topbar h2 i {
                font-size: 14px;
            }

            .profesor-user .user-info .name {
                font-size: 13px;
            }

            .profesor-user .avatar {
                width: 36px;
                height: 36px;
                font-size: 15px;
            }
        }

        /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
        @media (max-width: 480px) {
            .profesor-content {
                padding: 10px;
            }

            .profesor-topbar {
                padding: 10px 12px;
                border-radius: 8px;
                flex-wrap: wrap;
            }

            .profesor-topbar .left-section {
                width: 100%;
                justify-content: space-between;
            }

            .profesor-topbar h2 {
                font-size: 14px;
                gap: 6px;
            }

            .profesor-topbar h2 i {
                font-size: 12px;
            }

            .menu-toggle {
                padding: 8px 10px;
                font-size: 16px;
                margin-right: 10px;
            }

            .profesor-user {
                width: 100%;
                justify-content: flex-end;
                padding-top: 8px;
                border-top: 1px solid var(--border-color);
            }

            .profesor-user .user-info .name {
                font-size: 12px;
            }

            .profesor-user .user-info .role {
                font-size: 10px;
            }

            .profesor-user .avatar {
                width: 32px;
                height: 32px;
                font-size: 13px;
            }

            .profesor-sidebar {
                width: 260px;
            }

            .profesor-sidebar .logo {
                padding: 18px 20px 15px;
                font-size: 18px;
            }

            .profesor-sidebar .logo i {
                font-size: 22px;
            }

            .profesor-sidebar .menu-item {
                padding: 10px 18px;
                font-size: 13px;
                gap: 10px;
            }

            .profesor-sidebar .menu-item i {
                font-size: 14px;
            }

            .alert {
                padding: 10px 14px;
                font-size: 13px;
                border-radius: 8px;
            }

            .alert i {
                font-size: 16px;
            }
        }

        /* ===== RESPONSIVE - MÓVILES MUY PEQUEÑOS ===== */
        @media (max-width: 360px) {
            .profesor-content {
                padding: 6px;
            }

            .profesor-topbar {
                padding: 8px 10px;
            }

            .profesor-topbar h2 {
                font-size: 12px;
            }

            .menu-toggle {
                padding: 6px 8px;
                font-size: 14px;
                margin-right: 6px;
            }

            .profesor-user .user-info .name {
                font-size: 11px;
            }

            .profesor-user .avatar {
                width: 28px;
                height: 28px;
                font-size: 11px;
            }

            .profesor-sidebar {
                width: 220px;
            }

            .profesor-sidebar .logo {
                padding: 14px 16px 12px;
                font-size: 16px;
            }

            .profesor-sidebar .logo i {
                font-size: 18px;
            }

            .profesor-sidebar .menu-item {
                padding: 8px 14px;
                font-size: 12px;
                gap: 8px;
            }

            .profesor-sidebar .menu-item i {
                font-size: 12px;
                width: 16px;
            }
        }

        /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
        @media (max-height: 600px) and (orientation: landscape) {
            .profesor-sidebar {
                padding-top: 0;
                overflow-y: auto;
            }

            .profesor-sidebar .logo {
                padding: 12px 20px 10px;
                font-size: 16px;
                margin-bottom: 8px;
            }

            .profesor-sidebar .logo i {
                font-size: 20px;
            }

            .profesor-sidebar .menu-item {
                padding: 8px 20px;
                font-size: 12px;
                gap: 10px;
            }

            .profesor-sidebar .menu-item i {
                font-size: 13px;
                width: 18px;
            }

            .profesor-sidebar hr {
                margin: 5px 15px;
            }

            .profesor-topbar {
                padding: 8px 15px;
                margin-bottom: 15px;
            }

            .profesor-topbar h2 {
                font-size: 16px;
            }

            .profesor-content {
                padding: 10px 15px;
            }
        }

        /* ===== MEJORAS DE ACCESIBILIDAD ===== */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ===== SCROLL SUAVE ===== */
        html {
            scroll-behavior: smooth;
        }

        /* ===== SELECTION ===== */
        ::selection {
            background: var(--primary);
            color: white;
        }

        /* ===== ENLACES ===== */
        a {
            text-decoration: none;
        }

        /* ===== TOGGLE TEMA (opcional, para el header) ===== */
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            transition: all var(--transition);
            font-size: 14px;
            color: var(--text-secondary);
        }

        .theme-toggle:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 142, 140, 0.15);
        }

        .theme-toggle i {
            font-size: 16px;
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

        /* ===== UTILITY ===== */
        .hidden {
            display: none !important;
        }

        .text-center {
            text-align: center;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Overlay para cerrar sidebar en móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <div class="profesor-sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-chalkboard-teacher"></i>
            <span>Profesor</span>
        </div>
        <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
            <span class="badge-side">Home</span>
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
        <hr>
        <a href="perfil.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> Mi Perfil
        </a>
        <a href="../../logout.php" class="menu-item logout">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>

    <!-- Content -->
    <div class="profesor-content">
        <div class="profesor-topbar">
            <div class="left-section">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <h2><i class="fas fa-<?php echo $page_icon ?? 'chalkboard-teacher'; ?>"></i> <?php echo $page_title ?? 'Dashboard'; ?></h2>
            </div>
            <div class="profesor-user">
                <!-- Toggle Theme (opcional, se puede agregar aquí) -->
                <div class="theme-toggle" id="themeToggle" role="button" tabindex="0" style="margin-right: 10px;">
                    <i class="fas fa-sun" id="iconSun"></i>
                    <div class="toggle-track">
                        <div class="toggle-thumb"></div>
                    </div>
                    <i class="fas fa-moon" id="iconMoon"></i>
                </div>
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
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('active');
    
    // Prevenir scroll cuando el sidebar está abierto en móvil
    if (sidebar.classList.contains('show')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('show');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Cerrar sidebar con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

// Cerrar sidebar al redimensionar a desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 820) {
        closeSidebar();
    }
});

// Detectar clic fuera del sidebar en móvil
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth <= 820) {
        if (sidebar.classList.contains('show')) {
            const isClickInsideSidebar = sidebar.contains(e.target);
            const isClickOnToggle = menuToggle.contains(e.target);
            
            if (!isClickInsideSidebar && !isClickOnToggle) {
                closeSidebar();
            }
        }
    }
});

// =============================================
// TOGGLE TEMA OSCURO/CLARO
// =============================================
(function() {
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    const iconSun = document.getElementById('iconSun');
    const iconMoon = document.getElementById('iconMoon');

    // Cargar tema guardado
    const savedTheme = localStorage.getItem('medacademy-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateIcons(savedTheme);

    if (themeToggle) {
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
    }

    function updateIcons(theme) {
        if (theme === 'dark') {
            if (iconSun) iconSun.style.opacity = '0.5';
            if (iconMoon) iconMoon.style.opacity = '1';
        } else {
            if (iconSun) iconSun.style.opacity = '1';
            if (iconMoon) iconMoon.style.opacity = '0.5';
        }
    }
})();
</script>