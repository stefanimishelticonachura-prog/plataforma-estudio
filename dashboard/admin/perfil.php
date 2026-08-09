<?php
$page_title = 'Mi Perfil';
$page_icon = 'user-circle';
require_once '../../config/database.php';
require_once 'includes/admin_header.php';

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
    .profile-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    .profile-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
    }
    .profile-field {
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
    }
    .profile-field .label {
        color: #666;
        font-weight: 500;
    }
    .profile-field .value {
        color: #2c3e50;
        font-weight: 600;
    }
    .password-form {
        margin-top: 20px;
    }
    .password-form .form-group {
        margin-bottom: 15px;
    }
    .password-form .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-weight: 500;
    }
    .password-form .form-group input {
        width: 100%;
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    .password-form .form-group input:focus {
        outline: none;
        border-color: #3498db;
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
        transition: background 0.3s;
    }
    .btn-change-password:hover {
        background: #2980b9;
    }
    @media (max-width: 768px) {
        .profile-container {
            grid-template-columns: 1fr;
        }
    }
</style>

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
            <?php echo strtoupper(substr($usuario['nombre'] ?? 'A', 0, 1)); ?>
        </div>
        <h3 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">
            <?php echo htmlspecialchars($usuario['nombre'] ?? '') . ' ' . htmlspecialchars($usuario['apellido'] ?? ''); ?>
        </h3>
        
        <div class="profile-field">
            <span class="label"><i class="fas fa-envelope"></i> Correo</span>
            <span class="value"><?php echo htmlspecialchars($usuario['correo'] ?? ''); ?></span>
        </div>
        <div class="profile-field">
            <span class="label"><i class="fas fa-user-tag"></i> Rol</span>
            <span class="value"><?php echo htmlspecialchars($_SESSION['usuario_rol'] ?? ''); ?></span>
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
        <h3 style="margin-bottom: 20px; color: #2c3e50;">
            <i class="fas fa-key"></i> Cambiar Contraseña
        </h3>
        <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
            Solo puedes cambiar tu contraseña. Para cambiar otros datos contacta al administrador.
        </p>
        
        <form method="POST" action="actions/cambiar_password.php" class="password-form">
            <div class="form-group">
                <label>Contraseña Actual *</label>
                <input type="password" name="password_actual" required placeholder="Ingresa tu contraseña actual">
            </div>
            <div class="form-group">
                <label>Nueva Contraseña *</label>
                <input type="password" name="password_nueva" required placeholder="Mínimo 8 caracteres" minlength="8">
            </div>
            <div class="form-group">
                <label>Confirmar Nueva Contraseña *</label>
                <input type="password" name="password_confirmar" required placeholder="Confirma tu nueva contraseña">
            </div>
            <button type="submit" class="btn-change-password">
                <i class="fas fa-save"></i> Cambiar Contraseña
            </button>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>