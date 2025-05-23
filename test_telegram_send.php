<?php
// Configuração de logs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para log detalhado
function logMessage($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= "\nData: " . print_r($data, true);
    }
    error_log($log . "\n", 3, "telegram_test.log");
}

// Função para enviar mensagem
function sendTelegramMessage($chat_id, $text, $parse_mode = null, $reply_markup = null) {
    $token = '8144994385:AAGCDElfpvAFlUcLjhBTg8kvrbwQKbaU6nY'; // Token do bot 7com1
    $url = "https://api.telegram.org/bot$token/sendMessage";
    
    // Monta o array de dados
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    
    // Adiciona campos opcionais se fornecidos
    if ($parse_mode) {
        $data['parse_mode'] = $parse_mode;
    }
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    
    // Log dos dados que serão enviados
    logMessage("Enviando mensagem para Telegram", [
        'url' => $url,
        'data' => $data
    ]);
    
    // Configuração do cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Executa o cURL
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log da resposta
    logMessage("Resposta do Telegram", [
        'http_code' => $http_code,
        'response' => $response
    ]);
    
    // Log de erros se houver
    if (curl_errno($ch)) {
        logMessage("Erro cURL", [
            'error' => curl_error($ch),
            'errno' => curl_errno($ch)
        ]);
    }
    
    curl_close($ch);
    return $response;
}

// Teste 1: Mensagem simples
echo "Teste 1: Enviando mensagem simples...\n";
$chat_id = '7635999916'; // Chat ID de teste do bot 7com1
$response = sendTelegramMessage($chat_id, "Teste de mensagem simples");
echo "Resposta: " . $response . "\n\n";

// Teste 2: Mensagem com HTML
echo "Teste 2: Enviando mensagem com HTML...\n";
$response = sendTelegramMessage(
    $chat_id,
    "<b>Teste</b> com <i>HTML</i>",
    'HTML'
);
echo "Resposta: " . $response . "\n\n";

// Teste 3: Mensagem com botão
echo "Teste 3: Enviando mensagem com botão...\n";
$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => 'Botão de Teste', 'callback_data' => 'test_button']
        ]
    ]
];
$response = sendTelegramMessage(
    $chat_id,
    "Teste com botão",
    null,
    json_encode($keyboard)
);
echo "Resposta: " . $response . "\n\n";

echo "Testes concluídos. Verifique o arquivo telegram_test.log para detalhes.\n"; 