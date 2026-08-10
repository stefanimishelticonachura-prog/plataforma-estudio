<?php
// PRIMERO: Procesar acciones
$page_title = 'Mi Perfil';
$page_icon = 'user-circle';

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol_id'] != 2) {
    header('Location: ../../index.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del usuario
try {
    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $usuario = null;
    $_SESSION['error'] = 'Error al cargar datos del perfil';
}

// AHORA incluir el header
require_once 'includes/profesor_header.php';
?>

<style>
    /* ===== RESET & BASE ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* ===== CONTENEDOR PRINCIPAL ===== */
    .perfil-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        width: 100%;
    }
    
    .page-title {
        font-size: 24px;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .page-title i {
        color: #9b59b6;
    }

    /* ===== ALERTAS ===== */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
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
        background-color: #fee;
        color: #c33;
        border: 1px solid #fcc;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    /* ===== PERFIL GRID ===== */
    .perfil-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    /* ===== PERFIL CARD ===== */
    .perfil-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s;
    }
    
    .perfil-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .perfil-card .avatar-container {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        margin: 0 auto 20px;
        font-weight: 700;
        text-transform: uppercase;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }
    
    .perfil-card .perfil-nombre {
        text-align: center;
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 22px;
        font-weight: 600;
    }
    
    .perfil-card .perfil-info {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .perfil-card .perfil-info:last-of-type {
        border-bottom: none;
    }
    
    .perfil-card .perfil-info .label {
        color: #666;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .perfil-card .perfil-info .label i {
        color: #9b59b6;
        width: 18px;
    }
    
    .perfil-card .perfil-info .value {
        color: #2c3e50;
        font-weight: 600;
        word-break: break-all;
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
        background: #d4edda;
        color: #155724;
    }
    
    .badge-inactivo {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-profesor {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    /* ===== FORMULARIO CAMBIAR CONTRASEÑA ===== */
    .perfil-card .form-title {
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .perfil-card .form-title i {
        color: #9b59b6;
    }
    
    .perfil-card .form-subtitle {
        color: #666;
        margin-bottom: 20px;
        font-size: 14px;
        line-height: 1.5;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-weight: 500;
        font-size: 14px;
    }
    
    .form-group label .required {
        color: #e74c3c;
    }
    
    .form-group input {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #9b59b6;
    }
    
    .form-group .input-wrapper {
        position: relative;
    }
    
    .form-group .input-wrapper .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        font-size: 16px;
        padding: 5px;
        transition: color 0.3s;
    }
    
    .form-group .input-wrapper .toggle-password:hover {
        color: #9b59b6;
    }
    
    .btn-submit {
        background: #9b59b6;
        color: white;
        padding: 10px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        transition: all 0.3s;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-submit:hover {
        background: #8e44ad;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(155, 89, 182, 0.4);
    }
    
    .btn-submit:active {
        transform: translateY(0);
    }
    
    .btn-submit:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .perfil-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .perfil-grid {
            gap: 20px;
        }
        
        .perfil-card {
            padding: 25px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .perfil-container {
            padding: 12px;
        }
        
        .page-title {
            font-size: 20px;
        }
        
        .perfil-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .perfil-card {
            padding: 20px;
        }
        
        .perfil-card .avatar-container {
            width: 100px;
            height: 100px;
            font-size: 40px;
        }
        
        .perfil-card .perfil-nombre {
            font-size: 20px;
        }
        
        .perfil-card .perfil-info {
            padding: 10px 0;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .perfil-card .perfil-info .value {
            width: 100%;
        }
        
        .perfil-card .form-title {
            font-size: 18px;
        }
        
        .form-group input {
            font-size: 13px;
            padding: 8px;
        }
        
        .btn-submit {
            font-size: 15px;
            padding: 10px 20px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .perfil-container {
            padding: 8px;
        }
        
        .page-title {
            font-size: 17px;
        }
        
        .page-title i {
            font-size: 16px;
        }
        
        .perfil-card {
            padding: 16px;
            border-radius: 10px;
        }
        
        .perfil-card .avatar-container {
            width: 80px;
            height: 80px;
            font-size: 32px;
        }
        
        .perfil-card .perfil-nombre {
            font-size: 17px;
            margin-bottom: 15px;
        }
        
        .perfil-card .perfil-info {
            padding: 8px 0;
            font-size: 13px;
        }
        
        .perfil-card .perfil-info .label {
            font-size: 13px;
        }
        
        .perfil-card .perfil-info .value {
            font-size: 13px;
        }
        
        .perfil-card .form-title {
            font-size: 16px;
        }
        
        .perfil-card .form-subtitle {
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            font-size: 13px;
        }
        
        .form-group input {
            font-size: 13px;
            padding: 8px;
            border-radius: 6px;
        }
        
        .btn-submit {
            font-size: 14px;
            padding: 8px 16px;
        }
        
        .badge {
            font-size: 11px;
            padding: 2px 10px;
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
        .perfil-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .perfil-card {
            padding: 12px;
        }
        
        .perfil-card .avatar-container {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        
        .perfil-card .perfil-nombre {
            font-size: 15px;
        }
        
        .perfil-card .perfil-info {
            font-size: 12px;
            padding: 6px 0;
        }
        
        .perfil-card .perfil-info .label {
            font-size: 12px;
        }
        
        .perfil-card .perfil-info .value {
            font-size: 12px;
        }
        
        .perfil-card .form-title {
            font-size: 14px;
        }
        
        .form-group input {
            font-size: 12px;
            padding: 6px;
        }
        
        .btn-submit {
            font-size: 13px;
            padding: 6px 12px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .perfil-container {
            padding: 10px;
        }
        
        .perfil-grid {
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .perfil-card {
            padding: 15px;
        }
        
        .perfil-card .avatar-container {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        
        .perfil-card .perfil-nombre {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .perfil-card .perfil-info {
            padding: 5px 0;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 8px;
        }
        
        .form-group input {
            padding: 6px;
            font-size: 12px;
        }
        
        .btn-submit {
            padding: 8px 16px;
            font-size: 14px;
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
        background: #9b59b6;
        color: white;
    }

    /* ===== UTILITY ===== */
    .hidden {
        display: none !important;
    }
</style>

<div class="perfil-container">
    <h3 class="page-title"><i class="fas fa-user-circle"></i> Mi Perfil</h3>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="perfil-grid">
        <!-- Información Personal -->
        <div class="perfil-card">
            <div class="avatar-container">
                <?php echo strtoupper(substr($usuario['nombre'] ?? 'P', 0, 1)); ?>
            </div>
            <h3 class="perfil-nombre">
                <?php echo htmlspecialchars($usuario['nombre'] ?? '') . ' ' . htmlspecialchars($usuario['apellido'] ?? ''); ?>
            </h3>
            
            <div class="perfil-info">
                <span class="label"><i class="fas fa-envelope"></i> Correo</span>
                <span class="value"><?php echo htmlspecialchars($usuario['correo'] ?? ''); ?></span>
            </div>
            <div class="perfil-info">
                <span class="label"><i class="fas fa-user-tag"></i> Rol</span>
                <span class="value">
                    <span class="badge badge-profesor">Profesor</span>
                </span>
            </div>
            <div class="perfil-info">
                <span class="label"><i class="fas fa-calendar"></i> Registrado</span>
                <span class="value"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'] ?? 'now')); ?></span>
            </div>
            <div class="perfil-info">
                <span class="label"><i class="fas fa-clock"></i> Último acceso</span>
                <span class="value"><?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?></span>
            </div>
            <div class="perfil-info">
                <span class="label"><i class="fas fa-circle"></i> Estado</span>
                <span class="value">
                    <span class="badge <?php echo ($usuario['activo'] ?? 0) ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <?php echo ($usuario['activo'] ?? 0) ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </span>
            </div>
        </div>
        
        <!-- Cambiar Contraseña -->
        <div class="perfil-card">
            <h3 class="form-title">
                <i class="fas fa-key"></i> Cambiar Contraseña
            </h3>
            <p class="form-subtitle">
                <i class="fas fa-info-circle" style="color: #9b59b6;"></i>
                Solo puedes cambiar tu contraseña. Para cambiar otros datos contacta al administrador.
            </p>
            
            <form method="POST" action="actions/cambiar_password.php" id="formCambiarPassword">
                <div class="form-group">
                    <label>Contraseña Actual <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="password_actual" id="passwordActual" required placeholder="Ingresa tu contraseña actual">
                        <button type="button" class="toggle-password" onclick="togglePassword('passwordActual')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="password_nueva" id="passwordNueva" required placeholder="Mínimo 8 caracteres" minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('passwordNueva')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="password" name="password_confirmar" id="passwordConfirmar" required placeholder="Confirma tu nueva contraseña">
                        <button type="button" class="toggle-password" onclick="togglePassword('passwordConfirmar')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div id="passwordError" style="color: #e74c3c; font-size: 13px; margin-bottom: 10px; display: none;"></div>
                <button type="submit" class="btn-submit" id="btnCambiarPassword">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Función para mostrar/ocultar contraseña
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    var icon = input.parentElement.querySelector('.toggle-password i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Validación del formulario de cambio de contraseña
document.getElementById('formCambiarPassword').addEventListener('submit', function(e) {
    var passwordNueva = document.getElementById('passwordNueva').value;
    var passwordConfirmar = document.getElementById('passwordConfirmar').value;
    var errorDiv = document.getElementById('passwordError');
    
    // Limpiar error anterior
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    
    // Validar que las contraseñas coincidan
    if (passwordNueva !== passwordConfirmar) {
        e.preventDefault();
        errorDiv.textContent = '❌ Las contraseñas no coinciden.';
        errorDiv.style.display = 'block';
        document.getElementById('passwordConfirmar').focus();
        document.getElementById('passwordConfirmar').style.borderColor = '#e74c3c';
        return;
    }
    
    // Validar longitud mínima
    if (passwordNueva.length < 8) {
        e.preventDefault();
        errorDiv.textContent = '❌ La nueva contraseña debe tener al menos 8 caracteres.';
        errorDiv.style.display = 'block';
        document.getElementById('passwordNueva').focus();
        document.getElementById('passwordNueva').style.borderColor = '#e74c3c';
        return;
    }
    
    // Validar que la contraseña actual no esté vacía
    var passwordActual = document.getElementById('passwordActual').value;
    if (passwordActual.length === 0) {
        e.preventDefault();
        errorDiv.textContent = '❌ Debes ingresar tu contraseña actual.';
        errorDiv.style.display = 'block';
        document.getElementById('passwordActual').focus();
        document.getElementById('passwordActual').style.borderColor = '#e74c3c';
        return;
    }
    
    // Restaurar estilos si todo está bien
    document.getElementById('passwordActual').style.borderColor = '#e0e0e0';
    document.getElementById('passwordNueva').style.borderColor = '#e0e0e0';
    document.getElementById('passwordConfirmar').style.borderColor = '#e0e0e0';
});

// Limpiar error al escribir en los campos
document.querySelectorAll('#formCambiarPassword input').forEach(function(input) {
    input.addEventListener('input', function() {
        this.style.borderColor = '#e0e0e0';
        document.getElementById('passwordError').style.display = 'none';
    });
});

// Mostrar validación en tiempo real de coincidencia
document.getElementById('passwordConfirmar').addEventListener('input', function() {
    var passwordNueva = document.getElementById('passwordNueva').value;
    var errorDiv = document.getElementById('passwordError');
    
    if (this.value.length > 0 && this.value !== passwordNueva) {
        this.style.borderColor = '#e74c3c';
        errorDiv.textContent = '⚠️ Las contraseñas no coinciden.';
        errorDiv.style.display = 'block';
        errorDiv.style.color = '#e74c3c';
    } else if (this.value.length > 0 && this.value === passwordNueva) {
        this.style.borderColor = '#2ecc71';
        errorDiv.textContent = '✅ Las contraseñas coinciden.';
        errorDiv.style.display = 'block';
        errorDiv.style.color = '#2ecc71';
    } else {
        this.style.borderColor = '#e0e0e0';
        errorDiv.style.display = 'none';
    }
});

// Mostrar fortaleza de la contraseña
document.getElementById('passwordNueva').addEventListener('input', function() {
    var errorDiv = document.getElementById('passwordError');
    
    if (this.value.length > 0 && this.value.length < 8) {
        this.style.borderColor = '#f39c12';
        errorDiv.textContent = '⚠️ La contraseña debe tener al menos 8 caracteres.';
        errorDiv.style.display = 'block';
        errorDiv.style.color = '#f39c12';
    } else if (this.value.length >= 8) {
        this.style.borderColor = '#2ecc71';
        errorDiv.textContent = '✅ Longitud válida.';
        errorDiv.style.display = 'block';
        errorDiv.style.color = '#2ecc71';
    } else {
        this.style.borderColor = '#e0e0e0';
        errorDiv.style.display = 'none';
    }
    
    // También verificar coincidencia si hay algo en confirmar
    var confirmar = document.getElementById('passwordConfirmar');
    if (confirmar.value.length > 0) {
        confirmar.dispatchEvent(new Event('input'));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>