<?php
function logEvent($evento, $dados = [], $tipo = 'info') {
    $log_dir = __DIR__ . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'evento' => $evento,
        'tipo' => $tipo,
        'dados' => $dados,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    $log_file = $log_dir . '/events_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, json_encode($log_data) . "\n", FILE_APPEND);
} 