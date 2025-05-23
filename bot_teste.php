<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Log mínimo para garantir execução
file_put_contents(__DIR__.'/logs/debug_top_'.date('Y-m-d').'.log', date('Y-m-d H:i:s')." - Entrou no topo do bot_teste.php\n", FILE_APPEND);

require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/telegram/register-via-telegram.php';
require_once __DIR__ . '/telegram/send-welcome.php';
require_once __DIR__ . '/telegram/log.php';

// Log inicial
global $telegram_log_file;
telegram_log(["msg" => "Iniciando processamento do bot_teste", "time" => date('Y-m-d H:i:s')]);

// Funções utilitárias para enviar mensagens
function sendTelegramMessage($chat_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
    telegram_log(["msg" => "DEBUG: Entrou em sendTelegramMessage", "chat_id" => $chat_id, "text" => $text]);
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    $ch = curl_init($url);
    telegram_log(["msg" => "DEBUG: Após curl_init"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    telegram_log(["msg" => "DEBUG: Antes do curl_exec", "data_enviada" => $data]);
    $result = curl_exec($ch);
    telegram_log(["msg" => "DEBUG: Depois do curl_exec", "result" => $result]);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    telegram_log([
        "msg" => "DEBUG: curl_exec result",
        "result" => $result,
        "http_code" => $http_code,
        "curl_error" => $curl_error,
        "curl_errno" => $curl_errno,
        "data_enviada" => $data
    ]);
    telegram_log(["msg" => "DEBUG: Fim da função sendTelegramMessage"]);
    return json_decode($result, true);
}

// Recebe update do Telegram
$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
telegram_log(["msg" => "Dados recebidos do webhook", "update" => $update]);

// TESTE: Envio imediato de mensagem simples ao receber qualquer update
if (isset($update['message']['chat']['id'])) {
    $test_chat_id = $update['message']['chat']['id'];
    sendTelegramMessage($test_chat_id, 'Mensagem de teste IMEDIATA do início do webhook! (bot_teste.php)');
    telegram_log(["msg" => "DEBUG: Mensagem de teste enviada imediatamente", "chat_id" => $test_chat_id]);
}

header('HTTP/1.1 200 OK');
echo "OK"; 