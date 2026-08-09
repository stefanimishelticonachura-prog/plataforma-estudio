<?php
session_start();
require_once '../../../config/database.php';

$evaluacion_id = $_GET['id'] ?? 0;
$usuario_id = $_SESSION['usuario_id'];

if ($evaluacion_id == 0) {
    $_SESSION['error'] = 'Evaluación no válida';
    header('Location: ../evaluaciones.php');
    exit();
}

try {
    // Verificar que el estudiante tiene acceso a esta evaluación
    $stmt = $pdo->prepare("
        SELECT e.*, t.id_materia 
        FROM Evaluaciones e
        JOIN Temas t ON e.id_tema = t.id
        JOIN Inscripciones i ON i.id_materia = t.id_materia
        WHERE e.id = ? AND i.id_usuario = ?
    ");
    $stmt->execute([$evaluacion_id, $usuario_id]);
    $evaluacion = $stmt->fetch();
    
    if (!$evaluacion) {
        $_SESSION['error'] = 'No tienes acceso a esta evaluación';
        header('Location: ../evaluaciones.php');
        exit();
    }
    
    // Verificar intentos disponibles
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM ResultadosEvaluacion 
        WHERE id_evaluacion = ? AND id_usuario = ?
    ");
    $stmt->execute([$evaluacion_id, $usuario_id]);
    $intentos = $stmt->fetch()['total'];
    
    if ($intentos >= $evaluacion['intentos_permitidos']) {
        $_SESSION['error'] = 'Has alcanzado el límite de intentos permitidos';
        header('Location: ../evaluaciones.php');
        exit();
    }
    
    // Aquí iría la lógica para mostrar la evaluación
    // Por ahora, simulamos una calificación aleatoria
    $puntaje = rand(50, 100);
    $nuevo_intento = $intentos + 1;
    
    // Guardar resultado
    $stmt = $pdo->prepare("
        INSERT INTO ResultadosEvaluacion (id_usuario, id_evaluacion, intento, puntaje_obtenido) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$usuario_id, $evaluacion_id, $nuevo_intento, $puntaje]);
    
    // Actualizar progreso
    $stmt = $pdo->prepare("
        UPDATE Progreso 
        SET evaluacion_completada = 1, fecha_actualizacion = NOW() 
        WHERE id_usuario = ? AND id_tema = ?
    ");
    $stmt->execute([$usuario_id, $evaluacion['id_tema']]);
    
    $aprobado = $puntaje >= $evaluacion['puntaje_aprobacion'];
    $_SESSION['success'] = 'Evaluación completada. Puntaje: ' . $puntaje . 
                          ' - ' . ($aprobado ? '✅ Aprobada' : '❌ Reprobada');
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error al realizar evaluación: ' . $e->getMessage();
}

header('Location: ../evaluaciones.php');
exit();
?>