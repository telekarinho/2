<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
define("OK_LOADME", true);
require_once("log.php");

$numero = $_GET['numero'] ?? '';
$codigo = $_GET['codigo'] ?? '';

if (empty($numero) || !preg_match('/^[0-9]{10,15}$/', $numero)) {
    die(json_encode(['status' => 'error', 'message' => 'Número inválido']));
}

$verificacao_file = __DIR__ . '/verificacoes/' . $numero . '.json';
if (!file_exists($verificacao_file)) {
    die(json_encode(['status' => 'error', 'message' => 'Registro não encontrado']));
}
$dados = json_decode(file_get_contents($verificacao_file), true);

if (!empty($codigo)) {
    if ($dados['codigo'] !== $codigo) {
        die(json_encode(['status' => 'error', 'message' => 'Código inválido']));
    }
    if ($dados['status'] === 'confirmado') {
        die(json_encode(['status' => 'error', 'message' => 'Registro já confirmado']));
    }
    if (time() - $dados['timestamp'] > 3600) {
        unlink($verificacao_file);
        die(json_encode(['status' => 'error', 'message' => 'Código expirado']));
    }
    $dados['status'] = 'confirmado';
    file_put_contents($verificacao_file, json_encode($dados, JSON_PRETTY_PRINT));
    logEvent('registration_confirmed', [
        'numero' => $numero,
        'codigo' => $codigo
    ], 'success');
    die(json_encode([
        'status' => 'confirmed',
        'dados' => $dados
    ]));
}

if ($dados['status'] === 'confirmado') {
    die(json_encode([
        'status' => 'confirmed',
        'dados' => $dados
    ]));
}
if (time() - $dados['timestamp'] > 3600) {
    unlink($verificacao_file);
    die(json_encode(['status' => 'error', 'message' => 'Código expirado']));
}
die(json_encode(['status' => 'pending'])); 