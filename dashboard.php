<?php
require_once 'config/database.php';

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Redirigir según el rol
$rol_id = $_SESSION['usuario_rol_id'];

switch ($rol_id) {
    case 1:
        header('Location: dashboard/estudiante/index.php');
        break;
    case 2:
        header('Location: dashboard/profesor/index.php');
        break;
    case 3:
        header('Location: dashboard/admin/index.php');
        break;
    default:
        header('Location: index.php');
        break;
}
exit();
?>