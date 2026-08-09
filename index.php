<?php
require_once 'config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<link rel="stylesheet" href="css/index_login.css">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEDACADEMY - Iniciar Sesión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                               placeholder="ejemplo@email.com" autocomplete="email">
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
                        <input type="checkbox" name="remember" id="remember">
                        Recordarme
                    </label>
                    <a href="#"><i class="fas fa-question-circle"></i> ¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                    Iniciar Sesión
                </button>
            </form>
            <!-- Credenciales de prueba -->
            <div class="test-credentials">
                <h4><i class="fas fa-flask"></i> Credenciales de prueba</h4>
                <div class="cred-grid">
                    <div class="cred-item">
                        <span class="role-badge">👨‍🎓 Estudiante</span>
                        <span>juan.perez@email.com</span>
                        <span class="cred-pass">· password</span>
                    </div>
                    <div class="cred-item">
                        <span class="role-badge">👨‍🏫 Profesor</span>
                        <span>carlos.mendoza@email.com</span>
                        <span class="cred-pass">· password</span>
                    </div>
                    <div class="cred-item" style="grid-column: span 2;">
                        <span class="role-badge">👑 Administrador</span>
                        <span>admin@plataforma.com</span>
                        <span class="cred-pass">· password</span>
                    </div>
                        <i><a href="index.html">volver al inicio</a></i>
                </div>
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

            // ---------- AUTOCOMPLETADO SEGURO ----------
            // Si hay error, mantener el correo escrito
            const urlParams = new URLSearchParams(window.location.search);
            const errorParam = urlParams.get('error');
            if (errorParam) {
                const correoInput = document.getElementById('correo');
                const savedCorreo = localStorage.getItem('medacademy_last_correo');
                if (savedCorreo) {
                    correoInput.value = savedCorreo;
                }
            }

            // Guardar correo al enviar (para recuperar en error)
            document.getElementById('loginForm').addEventListener('submit', function() {
                const correo = document.getElementById('correo').value.trim();
                if (correo) {
                    localStorage.setItem('medacademy_last_correo', correo);
                }
            });

        })();
    </script>
</body>
</html>