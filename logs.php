<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

// Verifica autenticação básica
$username = 'admin';
$password = '7c1@2024#logs'; // Senha segura para acesso aos logs

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] !== $username || $_SERVER['PHP_AUTH_PW'] !== $password) {
    header('WWW-Authenticate: Basic realm="Logs"');
    header('HTTP/1.0 401 Unauthorized');
    die('Acesso negado');
}

$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    die('Diretório de logs não encontrado');
}

$tipo = $_GET['tipo'] ?? 'events';
$data = $_GET['data'] ?? date('Y-m-d');

$log_file = $log_dir . '/' . $tipo . '_' . $data . '.log';
if (!file_exists($log_file)) {
    die('Arquivo de log não encontrado');
}

$logs = file($log_file);
$logs = array_reverse($logs); // Mostra os mais recentes primeiro

?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs do Sistema</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .log-entry { margin: 10px 0; padding: 10px; border: 1px solid #ddd; }
        .error { background: #ffe6e6; }
        .success { background: #e6ffe6; }
        .info { background: #e6f3ff; }
        .filters { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Logs do Sistema</h1>
    
    <div class="filters">
        <form method="get">
            <select name="tipo">
                <option value="events" <?= $tipo === 'events' ? 'selected' : '' ?>>Eventos</option>
                <option value="whatsapp" <?= $tipo === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            </select>
            <input type="date" name="data" value="<?= $data ?>">
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <?php foreach ($logs as $log): ?>
        <?php $entry = json_decode($log, true); ?>
        <div class="log-entry <?= $entry['tipo'] ?? 'info' ?>">
            <strong><?= htmlspecialchars($entry['timestamp']) ?></strong>
            <br>
            Evento: <?= htmlspecialchars($entry['evento'] ?? 'N/A') ?>
            <br>
            Tipo: <?= htmlspecialchars($entry['tipo'] ?? 'info') ?>
            <br>
            Dados: <pre><?= htmlspecialchars(json_encode($entry['dados'] ?? [], JSON_PRETTY_PRINT)) ?></pre>
            <br>
            IP: <?= htmlspecialchars($entry['ip'] ?? 'unknown') ?>
            <br>
            User Agent: <?= htmlspecialchars($entry['user_agent'] ?? 'unknown') ?>
        </div>
    <?php endforeach; ?>
</body>
</html> 