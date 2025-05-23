<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Defina as constantes necessárias ANTES do require
if (!defined('OK_LOADME')) {
    define('OK_LOADME', true);
}
if (!defined('INSTALL_PATH')) {
    define('INSTALL_PATH', $_SERVER['DOCUMENT_ROOT']);
}

// Caminho correto para o mailer.do.php
$commonPath = $_SERVER['DOCUMENT_ROOT'] . '/common/mailer.do.php';
if (!file_exists($commonPath)) {
    die('Erro: Não foi possível localizar o arquivo mailer.do.php em ' . $commonPath);
}
require_once($commonPath);

$phone = isset($_GET['phone']) ? preg_replace('/\D/', '', $_GET['phone']) : '';
$msg = isset($_GET['msg']) ? $_GET['msg'] : 'Teste de envio via DWAPI - ' . date('d/m/Y H:i:s');

if (!$phone) {
    echo '<form method="get">'
        . 'Número WhatsApp (internacional): <input name="phone" placeholder="5511999999999" required><br>'
        . 'Mensagem: <input name="msg" value="Teste de envio via DWAPI"><br>'
        . '<button type="submit">Enviar</button>'
        . '</form>';
    exit;
}

try {
    $result = sendWhatsAppDWAPI($phone, $msg);
    echo '<pre>';
    var_dump($result);
    echo '</pre>';
} catch (Throwable $e) {
    echo '<b>Erro ao enviar mensagem:</b><br>';
    echo nl2br($e);
} 