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

<h3><i class="fas fa-user-circle"></i> Mi Perfil</h3>

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

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <!-- Información Personal -->
    <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 48px; margin: 0 auto 20px;">
            <?php echo strtoupper(substr($usuario['nombre'] ?? 'P', 0, 1)); ?>
        </div>
        <h3 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">
            <?php echo htmlspecialchars($usuario['nombre'] ?? '') . ' ' . htmlspecialchars($usuario['apellido'] ?? ''); ?>
        </h3>
        
        <div style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between;">
            <span style="color: #666; font-weight: 500;"><i class="fas fa-envelope"></i> Correo</span>
            <span style="color: #2c3e50; font-weight: 600;"><?php echo htmlspecialchars($usuario['correo'] ?? ''); ?></span>
        </div>
        <div style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between;">
            <span style="color: #666; font-weight: 500;"><i class="fas fa-user-tag"></i> Rol</span>
            <span style="color: #2c3e50; font-weight: 600;">
                <span style="background: #f3e5f5; color: #7b1fa2; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;">Profesor</span>
            </span>
        </div>
        <div style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between;">
            <span style="color: #666; font-weight: 500;"><i class="fas fa-calendar"></i> Registrado</span>
            <span style="color: #2c3e50; font-weight: 600;"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'] ?? 'now')); ?></span>
        </div>
        <div style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between;">
            <span style="color: #666; font-weight: 500;"><i class="fas fa-clock"></i> Último acceso</span>
            <span style="color: #2c3e50; font-weight: 600;"><?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?></span>
        </div>
        <div style="padding: 12px 0; display: flex; justify-content: space-between;">
            <span style="color: #666; font-weight: 500;"><i class="fas fa-circle"></i> Estado</span>
            <span style="color: #2c3e50; font-weight: 600;">
                <span class="badge <?php echo ($usuario['activo'] ?? 0) ? 'badge-activo' : 'badge-inactivo'; ?>">
                    <?php echo ($usuario['activo'] ?? 0) ? 'Activo' : 'Inactivo'; ?>
                </span>
            </span>
        </div>
    </div>
    
    <!-- Cambiar Contraseña -->
    <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h3 style="margin-bottom: 20px; color: #2c3e50;">
            <i class="fas fa-key"></i> Cambiar Contraseña
        </h3>
        <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
            Solo puedes cambiar tu contraseña. Para cambiar otros datos contacta al administrador.
        </p>
        
        <form method="POST" action="actions/cambiar_password.php">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #555; font-weight: 500;">Contraseña Actual *</label>
                <input type="password" name="password_actual" required placeholder="Ingresa tu contraseña actual" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #555; font-weight: 500;">Nueva Contraseña *</label>
                <input type="password" name="password_nueva" required placeholder="Mínimo 8 caracteres" minlength="8" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #555; font-weight: 500;">Confirmar Nueva Contraseña *</label>
                <input type="password" name="password_confirmar" required placeholder="Confirma tu nueva contraseña" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            <button type="submit" style="background: #9b59b6; color: white; padding: 10px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%;">
                <i class="fas fa-save"></i> Cambiar Contraseña
            </button>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>