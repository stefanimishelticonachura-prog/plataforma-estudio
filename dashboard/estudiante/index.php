<?php
$page_title = 'Dashboard';
$page_icon = 'home';
require_once '../../config/database.php';
require_once 'includes/estudiante_header.php';

try {
    // Estadísticas del estudiante
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM Inscripciones i
        JOIN Materias m ON i.id_materia = m.id
        WHERE i.id_usuario = ? AND m.estado = 'activo'
    ");
    $stmt->execute([$usuario_id]);
    $materias_inscritas = $stmt->fetch()['total'];
    
    // Progreso promedio
    $stmt = $pdo->prepare("
        SELECT AVG(porcentaje) as promedio 
        FROM Progreso 
        WHERE id_usuario = ?
    ");
    $stmt->execute([$usuario_id]);
    $progreso_promedio = round($stmt->fetch()['promedio'] ?? 0);
    
    // Calificaciones promedio
    $stmt = $pdo->prepare("
        SELECT AVG(puntaje_obtenido) as promedio 
        FROM ResultadosEvaluacion 
        WHERE id_usuario = ?
    ");
    $stmt->execute([$usuario_id]);
    $calificacion_promedio = round($stmt->fetch()['promedio'] ?? 0);
    
    // Evaluaciones pendientes
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.id) as total
        FROM Evaluaciones e
        JOIN Temas t ON e.id_tema = t.id
        JOIN Materias m ON t.id_materia = m.id
        JOIN Inscripciones i ON i.id_materia = m.id
        WHERE i.id_usuario = ? 
        AND e.id NOT IN (
            SELECT id_evaluacion FROM ResultadosEvaluacion 
            WHERE id_usuario = ? AND aprobado = 1
        )
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $evaluaciones_pendientes = $stmt->fetch()['total'];
    
    // Últimas materias vistas
    $stmt = $pdo->prepare("
        SELECT m.*, 
               (SELECT COUNT(*) FROM Temas WHERE id_materia = m.id) as total_temas,
               (SELECT COUNT(*) FROM Progreso WHERE id_usuario = ? AND id_tema IN (SELECT id FROM Temas WHERE id_materia = m.id) AND video_visto = 1) as temas_vistos
        FROM Inscripciones i
        JOIN Materias m ON i.id_materia = m.id
        WHERE i.id_usuario = ? AND m.estado = 'activo'
        ORDER BY m.nombre
        LIMIT 5
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $ultimas_materias = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $materias_inscritas = 0;
    $progreso_promedio = 0;
    $calificacion_promedio = 0;
    $evaluaciones_pendientes = 0;
    $ultimas_materias = [];
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
    .dashboard-container {
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

    .alert-success {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    /* ===== STATS CARDS ===== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
        border-left: 4px solid #10b981;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .stat-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .stat-card .stat-info h3 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: #1a1a2e;
        line-height: 1.2;
    }

    .stat-card .stat-info p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
    }

    .stat-blue .stat-icon { background: #e3f2fd; color: #1976d2; }
    .stat-blue { border-left-color: #1976d2; }
    .stat-green .stat-icon { background: #e8f5e9; color: #2e7d32; }
    .stat-green { border-left-color: #2e7d32; }
    .stat-orange .stat-icon { background: #fff3e0; color: #e65100; }
    .stat-orange { border-left-color: #e65100; }
    .stat-purple .stat-icon { background: #f3e5f5; color: #7b1fa2; }
    .stat-purple { border-left-color: #7b1fa2; }
    .stat-ia .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .stat-ia { border-left-color: #764ba2; }

    /* ===== PANEL IA ===== */
    .ia-panel {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .ia-panel-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .ia-panel-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ia-status {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        opacity: 0.9;
    }

    .ia-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #4ade80;
        display: inline-block;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.8); }
        100% { opacity: 1; transform: scale(1); }
    }

    .ia-body {
        padding: 20px;
    }

    /* ===== CHAT IA ===== */
    .chat-container {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 450px;
        background: #fafafa;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .chat-message {
        max-width: 85%;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.5;
        animation: slideIn 0.3s ease;
        word-wrap: break-word;
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .chat-message.user {
        background: #667eea;
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
    }

    .chat-message.assistant {
        background: white;
        color: #1a1a2e;
        align-self: flex-start;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    .chat-message.assistant strong {
        color: #667eea;
    }

    .chat-input-area {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        background: white;
        border-top: 1px solid #e5e7eb;
        align-items: center;
        flex-wrap: wrap;
    }

    .chat-input-area input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
        min-width: 120px;
    }

    .chat-input-area input:focus {
        border-color: #667eea;
    }

    .chat-input-area button {
        padding: 10px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .chat-input-area button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .chat-input-area button:active {
        transform: translateY(0);
    }

    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 8px 16px;
        align-self: flex-start;
        background: white;
        border-radius: 12px;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: #667eea;
        border-radius: 50%;
        animation: typingBounce 1.4s infinite;
    }

    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typingBounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-8px); opacity: 1; }
    }

    /* ===== INFO CARD ===== */
    .info-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 30px;
        overflow-x: auto;
    }

    .info-card h4 {
        margin: 0 0 16px 0;
        color: #1a1a2e;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-card table {
        width: 100%;
        border-collapse: collapse;
        min-width: 300px;
    }

    .info-card td {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-card tr:last-child td {
        border-bottom: none;
    }

    .btn-sm {
        padding: 4px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }

    .btn-view {
        background: #e3f2fd;
        color: #1976d2;
    }

    .btn-view:hover {
        background: #1976d2;
        color: white;
        transform: translateY(-2px);
    }

    /* ===== ACCIONES RÁPIDAS ===== */
    .actions-section {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .actions-section h3 {
        margin: 0 0 16px 0;
        color: #1a1a2e;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .action-card {
        background: #fafafa;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .action-card:hover {
        background: white;
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }

    .action-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-size: 20px;
    }

    .action-card h4 {
        margin: 0 0 4px 0;
        font-size: 15px;
        color: #1a1a2e;
    }

    .action-card p {
        margin: 0;
        font-size: 13px;
        color: #6b7280;
    }

    /* ===== BOTÓN DE AYUDA IA ===== */
    .ia-helper {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
    }

    .ia-helper-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        font-size: 28px;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ia-helper-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 30px rgba(102, 126, 234, 0.7);
    }

    .ia-helper-btn:active {
        transform: scale(0.95);
    }

    /* ===== RESPONSIVE - TABLETS ===== */
    @media (max-width: 1024px) {
        .dashboard-container {
            padding: 15px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            padding: 16px;
        }
        
        .stat-card .stat-info h3 {
            font-size: 24px;
        }
        
        .actions-grid {
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }
    }

    /* ===== RESPONSIVE - MÓVILES Y TABLETS PEQUEÑAS ===== */
    @media (max-width: 820px) {
        .dashboard-container {
            padding: 12px;
        }
        
        .page-title {
            font-size: 20px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-card {
            padding: 14px;
            gap: 12px;
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            font-size: 20px;
        }
        
        .stat-card .stat-info h3 {
            font-size: 22px;
        }
        
        .stat-card .stat-info p {
            font-size: 12px;
        }
        
        .ia-panel-header {
            padding: 14px 18px;
        }
        
        .ia-panel-header h3 {
            font-size: 16px;
        }
        
        .ia-body {
            padding: 14px;
        }
        
        .chat-container {
            height: 380px;
        }
        
        .chat-message {
            max-width: 90%;
            font-size: 13px;
            padding: 10px 14px;
        }
        
        .chat-input-area {
            padding: 10px 14px;
        }
        
        .chat-input-area input {
            font-size: 13px;
            padding: 8px 12px;
        }
        
        .chat-input-area button {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .actions-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .action-card {
            padding: 16px;
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            font-size: 17px;
        }
        
        .action-card h4 {
            font-size: 14px;
        }
        
        .action-card p {
            font-size: 12px;
        }
        
        .info-card {
            padding: 16px;
        }
        
        .info-card table {
            min-width: 250px;
        }
        
        .info-card td {
            padding: 6px 0;
            font-size: 13px;
        }
        
        .ia-helper {
            bottom: 20px;
            right: 20px;
        }
        
        .ia-helper-btn {
            width: 50px;
            height: 50px;
            font-size: 22px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES PEQUEÑOS ===== */
    @media (max-width: 480px) {
        .dashboard-container {
            padding: 8px;
        }
        
        .page-title {
            font-size: 17px;
        }
        
        .page-title i {
            font-size: 16px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .stat-card {
            padding: 12px;
            gap: 10px;
            border-radius: 12px;
        }
        
        .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
            border-radius: 10px;
        }
        
        .stat-card .stat-info h3 {
            font-size: 18px;
        }
        
        .stat-card .stat-info p {
            font-size: 10px;
        }
        
        .ia-panel {
            border-radius: 12px;
        }
        
        .ia-panel-header {
            padding: 12px 14px;
        }
        
        .ia-panel-header h3 {
            font-size: 14px;
        }
        
        .ia-status {
            font-size: 11px;
        }
        
        .ia-body {
            padding: 10px;
        }
        
        .chat-container {
            height: 320px;
            border-radius: 8px;
        }
        
        .chat-messages {
            padding: 10px;
            gap: 8px;
        }
        
        .chat-message {
            max-width: 92%;
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 10px;
        }
        
        .chat-input-area {
            padding: 8px 10px;
            gap: 6px;
        }
        
        .chat-input-area input {
            font-size: 12px;
            padding: 8px 10px;
            border-radius: 6px;
            min-width: 80px;
        }
        
        .chat-input-area button {
            padding: 8px 14px;
            font-size: 12px;
            border-radius: 6px;
        }
        
        .actions-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        
        .action-card {
            padding: 14px;
            border-radius: 10px;
        }
        
        .action-icon {
            width: 36px;
            height: 36px;
            font-size: 15px;
        }
        
        .action-card h4 {
            font-size: 13px;
        }
        
        .action-card p {
            font-size: 11px;
        }
        
        .info-card {
            padding: 14px;
            border-radius: 12px;
        }
        
        .info-card h4 {
            font-size: 14px;
        }
        
        .info-card table {
            min-width: 200px;
            font-size: 12px;
        }
        
        .info-card td {
            padding: 5px 0;
            font-size: 12px;
        }
        
        .btn-sm {
            font-size: 11px;
            padding: 3px 8px;
        }
        
        .ia-helper {
            bottom: 15px;
            right: 15px;
        }
        
        .ia-helper-btn {
            width: 44px;
            height: 44px;
            font-size: 18px;
        }
        
        .typing-indicator span {
            width: 6px;
            height: 6px;
        }
    }

    /* ===== RESPONSIVE - MÓVILES MUY PEQUEÑOS ===== */
    @media (max-width: 360px) {
        .dashboard-container {
            padding: 4px;
        }
        
        .page-title {
            font-size: 15px;
        }
        
        .stats-grid {
            gap: 6px;
        }
        
        .stat-card {
            padding: 8px 10px;
            gap: 8px;
        }
        
        .stat-icon {
            width: 30px;
            height: 30px;
            font-size: 14px;
            border-radius: 8px;
        }
        
        .stat-card .stat-info h3 {
            font-size: 16px;
        }
        
        .stat-card .stat-info p {
            font-size: 9px;
        }
        
        .chat-container {
            height: 280px;
        }
        
        .chat-message {
            font-size: 11px;
            padding: 6px 10px;
        }
        
        .chat-input-area input {
            font-size: 11px;
            padding: 6px 8px;
        }
        
        .chat-input-area button {
            padding: 6px 10px;
            font-size: 11px;
        }
        
        .action-card {
            padding: 12px;
        }
        
        .action-icon {
            width: 32px;
            height: 32px;
            font-size: 13px;
        }
    }

    /* ===== SOPORTE PARA ORIENTACIÓN HORIZONTAL ===== */
    @media (max-height: 600px) and (orientation: landscape) {
        .dashboard-container {
            padding: 10px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }
        
        .stat-card {
            padding: 10px 14px;
        }
        
        .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }
        
        .stat-card .stat-info h3 {
            font-size: 20px;
        }
        
        .chat-container {
            height: 250px;
        }
        
        .ia-panel-header {
            padding: 10px 16px;
        }
        
        .ia-body {
            padding: 10px;
        }
        
        .actions-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<div class="dashboard-container">
    <h3 class="page-title"><i class="fas fa-home"></i> Dashboard</h3>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon"><i class="fas fa-book"></i></div>
            <div class="stat-info">
                <h3><?php echo $materias_inscritas; ?></h3>
                <p>Materias Inscritas</p>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon"><i class="fas fa-tasks"></i></div>
            <div class="stat-info">
                <h3><?php echo $progreso_promedio; ?>%</h3>
                <p>Progreso Promedio</p>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-info">
                <h3><?php echo $calificacion_promedio; ?></h3>
                <p>Calificación Promedio</p>
            </div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3><?php echo $evaluaciones_pendientes; ?></h3>
                <p>Evaluaciones Pendientes</p>
            </div>
        </div>
        <div class="stat-card stat-ia">
            <div class="stat-icon"><i class="fas fa-robot"></i></div>
            <div class="stat-info">
                <h3>IA</h3>
                <p>Asistente SYSGOV</p>
            </div>
        </div>
    </div>

    <!-- Panel IA - Solo Chat -->
    <div class="ia-panel">
        <div class="ia-panel-header">
            <h3><i class="fas fa-robot"></i> Asistente IA SYSGOV</h3>
            <div class="ia-status">
                <span class="ia-status-dot"></span>
                <span>Conectado a Groq</span>
            </div>
        </div>
        <div class="ia-body">
            <div class="chat-container">
                <div class="chat-messages" id="chatMessagesEstudiante">
                    <div class="chat-message assistant">
                        <strong>🤖 ¡Hola estudiante! Soy el asistente SYSGOV</strong><br><br>
                        Puedo ayudarte con tus estudios, resolver dudas sobre materias, recomendarte cómo mejorar tu progreso, y guiarte en tu aprendizaje. ¿En qué puedo ayudarte hoy?
                    </div>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="chatInputEstudiante" placeholder="Pregunta sobre tus materias, estudios, dudas...">
                    <button id="chatSendEstudiante">Enviar ✨</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimas Materias -->
    <div class="info-card">
        <h4><i class="fas fa-clock"></i> Mis Materias</h4>
        <?php if (empty($ultimas_materias)): ?>
            <p style="color: #999; text-align: center; padding: 20px;">No estás inscrito en ninguna materia</p>
        <?php else: ?>
            <table>
                <?php foreach ($ultimas_materias as $materia): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($materia['nombre']); ?></strong>
                            <br>
                            <small style="color: #999;">
                                Progreso: <?php 
                                $total = $materia['total_temas'];
                                $vistos = $materia['temas_vistos'];
                                $porcentaje = $total > 0 ? round(($vistos / $total) * 100) : 0;
                                echo $porcentaje . '%';
                                ?>
                            </small>
                        </td>
                        <td style="text-align: right;">
                            <a href="mis-materias.php" class="btn-sm btn-view">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Acciones Rápidas -->
    <div class="actions-section">
        <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
        <div class="actions-grid">
            <div class="action-card" onclick="location.href='material-estudio.php'">
                <div class="action-icon" style="background: #e3f2fd; color: #1976d2;">
                    <i class="fas fa-play"></i>
                </div>
                <h4>Continuar Estudiando</h4>
                <p>Continúa con tu material de estudio</p>
            </div>
            <div class="action-card" onclick="location.href='evaluaciones.php'">
                <div class="action-icon" style="background: #fff3e0; color: #e65100;">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <h4>Realizar Evaluaciones</h4>
                <p>Tienes <?php echo $evaluaciones_pendientes; ?> evaluaciones pendientes</p>
            </div>
            <div class="action-card" onclick="location.href='progreso.php'">
                <div class="action-icon" style="background: #fce4ec; color: #c62828;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h4>Ver Mi Progreso</h4>
                <p>Revisa tu avance en cada materia</p>
            </div>
            <div class="action-card" onclick="location.href='calificaciones.php'">
                <div class="action-icon" style="background: #e0f7fa; color: #00838f;">
                    <i class="fas fa-star"></i>
                </div>
                <h4>Ver Calificaciones</h4>
                <p>Consulta tus notas y rendimiento</p>
            </div>
            <div class="action-card" onclick="openIA()">
                <div class="action-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-robot"></i>
                </div>
                <h4>Preguntar a la IA</h4>
                <p>Consulta al asistente inteligente</p>
            </div>
        </div>
    </div>
</div>

<!-- Botón flotante para abrir la IA -->
<div class="ia-helper">
    <button class="ia-helper-btn" onclick="openIA()" title="Preguntar a la IA">
        <i class="fas fa-robot"></i>
    </button>
</div>

<script>
    // ========== CONFIGURACIÓN ==========
    const PROXY_URL = 'https://sysgov-proxy.onrender.com/api/groq';
    
    let chatHistoryEstudiante = [];
    const chatMessagesDiv = document.getElementById('chatMessagesEstudiante');
    const chatInput = document.getElementById('chatInputEstudiante');
    const chatSendBtn = document.getElementById('chatSendEstudiante');

    // ========== FUNCIONES IA ==========
    async function askGroq(question, contextHistory = []) {
        const messages = [
            { role: 'system', content: 'Eres un asistente experto llamado SYSGOV, especializado en educación y aprendizaje. Ayudas a estudiantes con sus estudios, resuelves dudas académicas, recomiendas técnicas de estudio, y explicas conceptos de manera clara y didáctica. Respondes en español, eres paciente, motivador y siempre buscas que el estudiante aprenda mejor.' }
        ];
        
        for(let msg of contextHistory.slice(-6)) {
            messages.push({ role: msg.role, content: msg.content });
        }
        messages.push({ role: 'user', content: question });
        
        try {
            const response = await fetch(PROXY_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    messages, 
                    model: 'llama-3.3-70b-versatile', 
                    temperature: 0.7, 
                    max_tokens: 1500 
                })
            });
            
            if(!response.ok) throw new Error(`Error ${response.status}`);
            const data = await response.json();
            if(data.error) throw new Error(data.error.message);
            return data.choices[0].message.content;
        } catch(err) {
            console.error('Error en IA:', err);
            throw new Error('No se pudo conectar con el asistente. Verifica tu conexión.');
        }
    }

    function addChatMessage(text, sender) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-message ${sender}`;
        if(sender === 'assistant') {
            let formatted = text.replace(/\n/g, '<br>');
            msgDiv.innerHTML = formatted;
        } else {
            msgDiv.textContent = text;
        }
        chatMessagesDiv.appendChild(msgDiv);
        chatMessagesDiv.scrollTop = chatMessagesDiv.scrollHeight;
        
        if(sender !== 'system') {
            chatHistoryEstudiante.push({ 
                role: sender === 'user' ? 'user' : 'assistant', 
                content: text 
            });
            if(chatHistoryEstudiante.length > 30) chatHistoryEstudiante = chatHistoryEstudiante.slice(-30);
        }
    }

    function showTyping() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.id = 'typingIndicatorEstudiante';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';
        chatMessagesDiv.appendChild(typingDiv);
        chatMessagesDiv.scrollTop = chatMessagesDiv.scrollHeight;
    }

    function hideTyping() {
        const el = document.getElementById('typingIndicatorEstudiante');
        if(el) el.remove();
    }

    async function handleChat() {
        const userMsg = chatInput.value.trim();
        if(!userMsg) return;
        
        addChatMessage(userMsg, 'user');
        chatInput.value = '';
        showTyping();
        
        try {
            const recentHistory = chatHistoryEstudiante.slice(-6);
            const reply = await askGroq(userMsg, recentHistory);
            hideTyping();
            addChatMessage(reply, 'assistant');
        } catch(err) {
            hideTyping();
            console.error(err);
            addChatMessage(`❌ Error: ${err.message}`, 'assistant');
        }
    }

    function openIA() {
        document.querySelector('.ia-panel').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        setTimeout(() => {
            chatInput.focus();
        }, 500);
    }

    // ========== EVENT LISTENERS ==========
    chatSendBtn.addEventListener('click', handleChat);
    chatInput.addEventListener('keypress', (e) => { 
        if(e.key === 'Enter') {
            e.preventDefault();
            handleChat();
        }
    });

    // ========== INICIALIZACIÓN ==========
    console.log('✅ SYSGOV IA integrada para estudiantes');
</script>

<?php require_once '../../includes/footer.php'; ?>