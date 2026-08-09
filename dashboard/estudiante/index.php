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

<!-- Estilos para el dashboard del estudiante con IA -->
<style>
    /* ===== ESTILOS BASE ===== */
    .dashboard-ia-container {
        padding: 20px;
        max-width: 100%;
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
    }

    .chat-input-area input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
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

    /* ===== ALERTAS ===== */
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-success {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
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

    @media (max-width: 768px) {
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
</style>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<div class="dashboard-ia-container">
    
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

<!-- ===== JAVASCRIPT IA ===== -->
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