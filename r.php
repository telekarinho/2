<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
define("OK_LOADME", true);
require_once("common/init.db.php");
require_once("dwapi-send-v1.php");
require_once("log.php");

// Recebe número do usuário e do patrocinador
$numero = $_GET['numero'] ?? '';
$patrocinador = $_GET['patrocinador'] ?? '';

// Validação básica
if (empty($numero) || !preg_match('/^[0-9]{10,15}$/', $numero)) {
    die(json_encode(['status' => 'error', 'message' => 'Número inválido']));
}
if (empty($patrocinador) || !preg_match('/^[0-9]{10,15}$/', $patrocinador)) {
    die(json_encode(['status' => 'error', 'message' => 'Patrocinador inválido']));
}

// Gera código de ativação (4 dígitos)
$codigo = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Gera senha aleatória de 4 dígitos
$password = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Gera dados do usuário
$username = $numero;
$firstname = substr($numero, 4, 4);
$lastname = substr($numero, 8);
$email = $numero . '@7com1.com';
$ppid = 1;
$idref = substr($patrocinador, 0, 2); // Exemplo: pode ser ajustado conforme sua lógica

// Salva status/código para validação posterior
$verificacoes_dir = __DIR__ . '/verificacoes';
if (!file_exists($verificacoes_dir)) mkdir($verificacoes_dir, 0777, true);
$verificacao_file = $verificacoes_dir . '/' . $numero . '.json';
file_put_contents($verificacao_file, json_encode([
    'numero' => $numero,
    'patrocinador' => $patrocinador,
    'codigo' => $codigo,
    'password' => $password,
    'username' => $username,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'email' => $email,
    'ppid' => $ppid,
    'idref' => $idref,
    'status' => 'pendente',
    'timestamp' => time()
], JSON_PRETTY_PRINT));

// Monta mensagem 1
$link_ativacao = "https://7c1.pro/a?n={$numero}&c={$codigo}";
$mensagem = "👋 Olá! Aqui é o time 7com1.\n\n"
    . "Recebemos seu pedido de cadastro!\n\n"
    . "Você está se cadastrando na rede de mini franqueados de:\n\n"
    . "📲 7c1.pro/{$patrocinador}\n\n"
    . "🔐 Seu código de ativação: {$codigo}\n\n"
    . "🚀 Ou clique aqui para ativar:\n{$link_ativacao}\n\n"
    . "📎 Vídeo e PDF com mais detalhes estão anexados!\n\n"
    . "⚠️ Salve nosso número para ativar os links e receber atualizações da sua rede.";

// Envia mensagem via DW API com anexos
$response = sendWhatsAppMessage(
    $numero,
    $mensagem,
    [
        __DIR__ . '/video.mp4',
        __DIR__ . '/guia.pdf'
    ],
    '43999300593' // sender oficial
);

// Log
logEvent('registration_started', [
    'numero' => $numero,
    'patrocinador' => $patrocinador,
    'codigo' => $codigo,
    'response' => $response
], $response['status'] === 'success' ? 'success' : 'error');

die(json_encode([
    'status' => $response['status'],
    'message' => $response['message']
])); 