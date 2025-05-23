<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Token e chat_id do bot
$token = '8144994385:AAGCDElfpvAFlUcLjhBTg8kvrbwQKbaU6nY';
$chat_id = '7635999916'; // Altere para o seu chat_id se necessário

// Mensagem de teste
$text = 'Teste de envio isolado via cURL direto do servidor!';

// Monta a URL e os dados
$url = "https://api.telegram.org/bot$token/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => $text
];

// Inicializa o cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

// Executa o envio
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Exibe o resultado
header('Content-Type: text/html; charset=utf-8');
echo "<b>URL:</b> $url<br>";
echo "<b>Dados enviados:</b> <pre>" . htmlspecialchars(print_r($data, true)) . "</pre><br>";
echo "<b>Resposta:</b> <pre>" . htmlspecialchars($response) . "</pre><br>";
echo "<b>HTTP Code:</b> $http_code<br>";
echo "<b>cURL Error:</b> $curl_error<br>"; 