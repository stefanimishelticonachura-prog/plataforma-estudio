<?php
$page_title = 'Mi Perfil';
$page_icon = 'user-circle';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

// Obtener datos del usuario
try {
    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    $usuario = null;
    $_SESSION['error'] = 'Error al cargar datos del perfil';
}
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
        color: #3498db;
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

    /* ===== PROFILE GRID ===== */
    .profile-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    /* ===== PROFILE CARD ===== */
    .profile-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s;
    }
    
    .profile-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .profile-avatar {
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
    
    .profile-card .profile-name {
        text-align: center;
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 22px;
        font-weight: 600;
    }
    
    .profile-field {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .profile-field:last-of-type {
        border-bottom: none;
    }
    
    .profile-field .label {
        color: #666;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .profile-field .label i {
        color: #3498db;
        width: 18px;
    }
    
    .profile-field .value {
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
    
    .badge-estudiante {
        background: #e3f2fd;
        color: #1976d2;
    }

    /* ===== FORMULARIO CAMBIAR CONTRASEÑA ===== */
    .profile-card .form-title {
        margin-bottom: 20px;
        color: #2c3e50;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .profile-card .form-title i {
        color: #3498db;
    }
    
    .profile-card .form-subtitle {
        color: #666;
        margin-bottom: 20px;
        font-size: 14px;
        line-height: 1.5;
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }
    
    .profile-card .form-subtitle i {
        color: #3498db;
        margin-top: 2px;
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
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #3498db;
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
        color: #3498db;
    }
    
    .btn-change-password {
        background: #3498db;
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
    
    .btn-change-password:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
    }
    
    .btn-change-password:active {
        transform: translateY(0);
    }
    
    .btn-change-password:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .password-error {
        color: #e74c3c;
        font-size: 13px;
        margin-bottom: 10px;
        display: none;
        padding: 8px 12px;
        background: #fde8e8;
        border-radius: 6px;
        border-left: 3px solid #e74c3c;
    }
    
    .password-error.show {
        display: block;
    }
    
    .password-success {
        color: #2ecc71;
        font-size: 13px;
        margin-bottom: 10px;
        display: none;
        padding: 8px 12px;
        background: #e8f8e8;
        border-radius: 6px;
        border-left: 3px solid #2ecc71;
    }
    
    .password-success.show {
        display: block;
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .perfil-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .profile-container {
            gap: 20px;
        }
        
        .profile-card {
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
        
        .profile-container {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .profile-card {
            padding: 20px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            font-size: 40px;
        }
        
        .profile-card .profile-name {
            font-size: 20px;
        }
        
        .profile-field {
            padding: 10px 0;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .profile-field .value {
            width: 100%;
        }
        
        .profile-card .form-title {
            font-size: 18px;
        }
        
        .form-group input {
            font-size: 13px;
            padding: 8px 12px;
        }
        
        .btn-change-password {
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
        
        .profile-card {
            padding: 16px;
            border-radius: 10px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            font-size: 32px;
        }
        
        .profile-card .profile-name {
            font-size: 17px;
            margin-bottom: 15px;
        }
        
        .profile-field {
            padding: 8px 0;
            font-size: 13px;
        }
        
        .profile-field .label {
            font-size: 13px;
        }
        
        .profile-field .value {
            font-size: 13px;
        }
        
        .profile-card .form-title {
            font-size: 16px;
        }
        
        .profile-card .form-subtitle {
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
            padding: 8px 10px;
            border-radius: 6px;
        }
        
        .btn-change-password {
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
        
        .profile-card {
            padding: 12px;
        }
        
        .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        
        .profile-card .profile-name {
            font-size: 15px;
        }
        
        .profile-field {
            font-size: 12px;
            padding: 6px 0;
        }
        
        .profile-field .label {
            font-size: 12px;
        }
        
        .profile-field .value {
            font-size: 12px;
        }
        
        .profile-card .form-title {
            font-size: 14px;
        }
        
        .form-group input {
            font-size: 12px;
            padding: 6px 8px;
        }
        
        .btn-change-password {
            font-size: 13px;
            padding: 6px 12px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .perfil-container {
            padding: 10px;
        }
        
        .profile-container {
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .profile-card {
            padding: 15px;
        }
        
        .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        
        .profile-card .profile-name {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .profile-field {
            padding: 5px 0;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 8px;
        }
        
        .form-group input {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .btn-change-password {
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
        background: #3498db;
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

    <div class="profile-container">
        <!-- Información Personal -->
        <div class="profile-card">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($usuario['nombre'] ?? 'E', 0, 1)); ?>
            </div>
            <h3 class="profile-name">
                <?php echo htmlspecialchars($usuario['nombre'] ?? '') . ' ' . htmlspecialchars($usuario['apellido'] ?? ''); ?>
            </h3>
            
            <div class="profile-field">
                <span class="label"><i class="fas fa-envelope"></i> Correo</span>
                <span class="value"><?php echo htmlspecialchars($usuario['correo'] ?? ''); ?></span>
            </div>
            <div class="profile-field">
                <span class="label"><i class="fas fa-user-tag"></i> Rol</span>
                <span class="value">
                    <span class="badge badge-estudiante">Estudiante</span>
                </span>
            </div>
            <div class="profile-field">
                <span class="label"><i class="fas fa-calendar"></i> Registrado</span>
                <span class="value"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'] ?? 'now')); ?></span>
            </div>
            <div class="profile-field">
                <span class="label"><i class="fas fa-clock"></i> Último acceso</span>
                <span class="value"><?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?></span>
            </div>
            <div class="profile-field">
                <span class="label"><i class="fas fa-circle"></i> Estado</span>
                <span class="value">
                    <span class="badge <?php echo ($usuario['activo'] ?? 0) ? 'badge-activo' : 'badge-inactivo'; ?>">
                        <?php echo ($usuario['activo'] ?? 0) ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </span>
            </div>
        </div>
        
        <!-- Cambiar Contraseña -->
        <div class="profile-card">
            <h3 class="form-title">
                <i class="fas fa-key"></i> Cambiar Contraseña
            </h3>
            <p class="form-subtitle">
                <i class="fas fa-info-circle"></i>
                Solo puedes cambiar tu contraseña. Para cambiar otros datos contacta al administrador.
            </p>
            
            <form method="POST" action="actions/cambiar_password.php" class="password-form" id="formCambiarPassword">
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
                <div class="password-error" id="passwordError"></div>
                <div class="password-success" id="passwordSuccess"></div>
                <button type="submit" class="btn-change-password" id="btnCambiarPassword">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// =============================================
// MOSTRAR/OCULTAR CONTRASEÑA
// =============================================
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

// =============================================
// VALIDACIÓN DEL FORMULARIO
// =============================================
document.getElementById('formCambiarPassword').addEventListener('submit', function(e) {
    var passwordNueva = document.getElementById('passwordNueva').value;
    var passwordConfirmar = document.getElementById('passwordConfirmar').value;
    var errorDiv = document.getElementById('passwordError');
    var successDiv = document.getElementById('passwordSuccess');
    
    // Limpiar mensajes anteriores
    errorDiv.classList.remove('show');
    errorDiv.textContent = '';
    successDiv.classList.remove('show');
    successDiv.textContent = '';
    
    // Validar que las contraseñas coincidan
    if (passwordNueva !== passwordConfirmar) {
        e.preventDefault();
        errorDiv.textContent = '❌ Las contraseñas no coinciden.';
        errorDiv.classList.add('show');
        document.getElementById('passwordConfirmar').focus();
        document.getElementById('passwordConfirmar').style.borderColor = '#e74c3c';
        return;
    }
    
    // Validar longitud mínima
    if (passwordNueva.length < 8) {
        e.preventDefault();
        errorDiv.textContent = '❌ La nueva contraseña debe tener al menos 8 caracteres.';
        errorDiv.classList.add('show');
        document.getElementById('passwordNueva').focus();
        document.getElementById('passwordNueva').style.borderColor = '#e74c3c';
        return;
    }
    
    // Validar que la contraseña actual no esté vacía
    var passwordActual = document.getElementById('passwordActual').value;
    if (passwordActual.length === 0) {
        e.preventDefault();
        errorDiv.textContent = '❌ Debes ingresar tu contraseña actual.';
        errorDiv.classList.add('show');
        document.getElementById('passwordActual').focus();
        document.getElementById('passwordActual').style.borderColor = '#e74c3c';
        return;
    }
    
    // Restaurar estilos si todo está bien
    document.getElementById('passwordActual').style.borderColor = '#e0e0e0';
    document.getElementById('passwordNueva').style.borderColor = '#e0e0e0';
    document.getElementById('passwordConfirmar').style.borderColor = '#e0e0e0';
});

// =============================================
// VALIDACIÓN EN TIEMPO REAL
// =============================================

// Limpiar error al escribir en los campos
document.querySelectorAll('#formCambiarPassword input').forEach(function(input) {
    input.addEventListener('input', function() {
        this.style.borderColor = '#e0e0e0';
        document.getElementById('passwordError').classList.remove('show');
        document.getElementById('passwordSuccess').classList.remove('show');
    });
});

// Mostrar validación en tiempo real de coincidencia
document.getElementById('passwordConfirmar').addEventListener('input', function() {
    var passwordNueva = document.getElementById('passwordNueva').value;
    var errorDiv = document.getElementById('passwordError');
    var successDiv = document.getElementById('passwordSuccess');
    
    errorDiv.classList.remove('show');
    successDiv.classList.remove('show');
    
    if (this.value.length > 0 && this.value !== passwordNueva) {
        this.style.borderColor = '#e74c3c';
        errorDiv.textContent = '⚠️ Las contraseñas no coinciden.';
        errorDiv.classList.add('show');
        errorDiv.style.color = '#e74c3c';
    } else if (this.value.length > 0 && this.value === passwordNueva) {
        this.style.borderColor = '#2ecc71';
        successDiv.textContent = '✅ Las contraseñas coinciden.';
        successDiv.classList.add('show');
        successDiv.style.color = '#2ecc71';
    } else {
        this.style.borderColor = '#e0e0e0';
    }
});

// Mostrar fortaleza de la contraseña
document.getElementById('passwordNueva').addEventListener('input', function() {
    var errorDiv = document.getElementById('passwordError');
    var successDiv = document.getElementById('passwordSuccess');
    var confirmar = document.getElementById('passwordConfirmar');
    
    errorDiv.classList.remove('show');
    successDiv.classList.remove('show');
    
    if (this.value.length > 0 && this.value.length < 8) {
        this.style.borderColor = '#f39c12';
        errorDiv.textContent = '⚠️ La contraseña debe tener al menos 8 caracteres.';
        errorDiv.classList.add('show');
        errorDiv.style.color = '#f39c12';
    } else if (this.value.length >= 8) {
        this.style.borderColor = '#2ecc71';
        successDiv.textContent = '✅ Longitud válida.';
        successDiv.classList.add('show');
        successDiv.style.color = '#2ecc71';
    } else {
        this.style.borderColor = '#e0e0e0';
    }
    
    // También verificar coincidencia si hay algo en confirmar
    if (confirmar.value.length > 0) {
        confirmar.dispatchEvent(new Event('input'));
    }
});

// =============================================
// PREVENIR ENVÍO DUPLICADO
// =============================================
document.getElementById('formCambiarPassword').addEventListener('submit', function(e) {
    var btn = document.getElementById('btnCambiarPassword');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
    // Re-habilitar después de 5 segundos si no se envía
    setTimeout(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Cambiar Contraseña';
    }, 5000);
});
</script>

<?php require_once '../../includes/footer.php'; ?>