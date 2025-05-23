<?php
// Webhook do bot Telegram 7com1 – integração 7x7
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

$TELEGRAM_BOT_TOKEN = '8144994385:AAGCDElfpvAFlUcLjhBTg8kvrbwQKbaU6nY';
$API_URL = "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/";

// Função para enviar mensagem
function sendTelegramMessage($chat_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
    global $API_URL;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    file_get_contents($API_URL . 'sendMessage?' . http_build_query($data));
}

// Função para enviar mídia
function sendTelegramMedia($chat_id, $media_url, $caption = '') {
    global $API_URL;
    $data = [
        'chat_id' => $chat_id,
        'video' => $media_url,
        'caption' => $caption
    ];
    file_get_contents($API_URL . 'sendVideo?' . http_build_query($data));
}
function sendTelegramDocument($chat_id, $doc_url, $caption = '') {
    global $API_URL;
    $data = [
        'chat_id' => $chat_id,
        'document' => $doc_url,
        'caption' => $caption
    ];
    file_get_contents($API_URL . 'sendDocument?' . http_build_query($data));
}

// Função para criar grupo privado
function createTelegramGroup($title, $user_ids = []) {
    // Telegram não permite criar grupos via API diretamente, mas permite criar supergrupos via bots adicionando membros
    // Aqui, simulamos a criação e retornamos um link fictício para testes
    // Na produção, use bots avançados ou integração manual para criar e obter o link real
    $slug = preg_replace('/\D/', '', $title);
    return "https://t.me/7com1_lider_$slug";
}

// Recebe update do Telegram
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || !isset($data['message'])) exit;

$message = $data['message'];
$text = $message['text'] ?? '';
$chat_id = $message['chat']['id'];
$from_id = $message['from']['id'];

// Reconhece frase de cadastro
if (preg_match('/ID de indica(?:ção|cao) de (\d{10,15})/i', $text, $matches)) {
    $patrocinador = $matches[1];
    // Extrai número do Telegram (user_id) e converte para string internacional (exemplo: 55 + DDD + número, se aplicável)
    $numero = $from_id;
    $numero_corrigido = $numero;
    // Se o usuário informar o número no texto, use esse número como base para o cadastro (opcional)
    // Aqui, mantemos o user_id do Telegram como identificador único
    $codigo = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $password = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $verificacoes_dir = __DIR__ . '/verificacoes';
    if (!file_exists($verificacoes_dir)) mkdir($verificacoes_dir, 0777, true);
    $verificacao_file = $verificacoes_dir . '/' . $numero_corrigido . '.json';
    file_put_contents($verificacao_file, json_encode([
        'numero' => $numero_corrigido,
        'patrocinador' => $patrocinador,
        'codigo' => $codigo,
        'password' => $password,
        'username' => $numero_corrigido,
        'firstname' => substr($numero_corrigido, 0, 4),
        'lastname' => substr($numero_corrigido, 4),
        'email' => $numero_corrigido . '@7com1.com',
        'status' => 'pendente',
        'telegram_id' => $from_id,
        'timestamp' => time()
    ], JSON_PRETTY_PRINT));
    // Mensagem de boas-vindas
    $msg = "👋 Olá! Aqui é o time 7com1.\n\nRecebemos seu pedido de cadastro!\n\nVocê está se cadastrando na rede de mini franqueados de:\n$patrocinador\n\n🔐 Ative seu cadastro clicando no link:\nhttps://7c1.pro/a.php?n=$numero_corrigido&c=$codigo\n\n📎 Abaixo segue Vídeo e PDF com A APN.\n\n⚠️ Salve nosso número para ativar os links e receber atualizações da sua rede.";
    sendTelegramMessage($chat_id, $msg);
    sendTelegramMedia($chat_id, 'https://7c1.pro/boasvindas.mp4');
    sendTelegramDocument($chat_id, 'https://7c1.pro/apresentacao7com1.pdf');
    exit;
}

// Outras lógicas podem ser adicionadas aqui (ex: ativação, suporte, etc) 