<?php
require_once 'config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Procesar "Recordarme" - cookie de correo
if (isset($_COOKIE['remember_correo'])) {
    $correo_recordado = $_COOKIE['remember_correo'];
    $remember_checked = true;
} else {
    $correo_recordado = '';
    $remember_checked = false;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEDACADEMY - Iniciar Sesión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index_login.css">
    
    <style>
        /* Estilos para los botones de navegación */
        .navigation-buttons {
            margin-top: 25px;
            display: flex;
            gap: 12px;
            justify-content: center;
            border-top: 1px solid var(--border-color, #e5e7eb);
            padding-top: 20px;
            flex-wrap: wrap;
        }

        .btn-register {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: #207a8a;
            color: white !important;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            flex: 1;
            justify-content: center;
            min-width: 140px;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
            cursor: pointer;
        }

        .btn-register:hover {
            background: #207a8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
            color: white !important;
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .btn-back-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: #f3f4f6;
            color: #1f2937 !important;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            flex: 1;
            justify-content: center;
            min-width: 140px;
            cursor: pointer;
        }

        .btn-back-home:hover {
            background: #e5e7eb;
            border-color: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-back-home i,
        .btn-register i {
            font-size: 1rem;
            transition: transform 0.3s ease;
        }

        .btn-register:hover i {
            transform: translateX(3px);
        }

        .btn-back-home:hover i {
            transform: translateX(-3px);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .navigation-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-register,
            .btn-back-home {
                flex: none;
                width: 100%;
            }
        }

        /* Modo oscuro */
        [data-theme="dark"] .btn-back-home {
            background: #374151;
            color: #f9fafb !important;
            border-color: #4b5563;
        }

        [data-theme="dark"] .btn-back-home:hover {
            background: #4b5563;
            border-color: #9ca3af;
        }

        [data-theme="dark"] .btn-register {
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
        }

        /* Estilos adicionales para mejorar la apariencia */
        .btn-register, .btn-back-home {
            letter-spacing: 0.3px;
        }

        .btn-register i, .btn-back-home i {
            font-size: 1.1rem;
        }

        /* Animación sutil al cargar */
        .navigation-buttons {
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    
    <div class="login-wrapper">

        <!-- ===== LEFT: IMAGE SIDE ===== -->
        <div class="login-image">
            <div class="image-logo">
                <h1>
                    MEDACADEMY
                    <span>PREPÁRATE PARA TU FUTURO MÉDICO</span>
                </h1>
                <div class="subtitle">✦ Plataforma de estudio avanzada</div>
            </div>

            <div class="image-content">
                <div class="big-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h2>Bienvenido de vuelta</h2>
                <p>Accede a tu cuenta y continúa preparándote para el examen de residencia médica.</p>
            </div>

            <div class="image-badge">
                <span class="dot"></span>
                <span>Sistema disponible · 24/7</span>
            </div>
        </div>

        <!-- ===== RIGHT: FORM SIDE ===== -->
        <div class="login-form">
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
                <h2>Iniciar Sesión</h2>
                <p>Ingresa tus credenciales para acceder a tu cuenta</p>
            </div>

            <!-- Alertas -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form action="login.php" method="POST" id="loginForm" autocomplete="off">
                <div class="form-group">
                    <label for="correo">
                        <i class="fas fa-envelope"></i> Correo electrónico
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="email" id="correo" name="correo" required 
                               placeholder="ejemplo@email.com" autocomplete="email"
                               value="<?php echo htmlspecialchars($correo_recordado); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" id="password" name="password" required 
                               placeholder="Ingresa tu contraseña" autocomplete="current-password">
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember" id="remember" <?php echo $remember_checked ? 'checked' : ''; ?>>
                        Recordarme
                    </label>
                    <a href="#"><i class="fas fa-question-circle"></i> ¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                    Iniciar Sesión
                </button>
            </form>

            <!-- Botones de navegación -->
            <div class="navigation-buttons">
                <a href="registrar.php" class="btn-register">
                    <i class="fas fa-user-plus"></i>
                    <span>Crear cuenta</span>
                </a>
                <a href="index.html" class="btn-back-home">
                    <i class="fas fa-arrow-left"></i>
                    <span>Volver al inicio</span>
                </a>
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
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // ---------- VALIDACIÓN CLIENTE ----------
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                const correo = document.getElementById('correo').value.trim();
                const password = document.getElementById('password').value.trim();
                
                if (!correo) {
                    e.preventDefault();
                    alert('Por favor, ingresa tu correo electrónico.');
                    document.getElementById('correo').focus();
                    return;
                }
                
                if (!password) {
                    e.preventDefault();
                    alert('Por favor, ingresa tu contraseña.');
                    document.getElementById('password').focus();
                    return;
                }

                // Validación básica de email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(correo)) {
                    e.preventDefault();
                    alert('Por favor, ingresa un correo electrónico válido.');
                    document.getElementById('correo').focus();
                    return;
                }
            });

        })();
    </script>
</body>
</html>