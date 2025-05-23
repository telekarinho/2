<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
require_once("dwapi-send-v1.php");

$mensagem = '';
$status = '';
$result_api = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $_POST['numero'] ?? '';
    $texto = $_POST['texto'] ?? '';
    $anexo = $_POST['anexo'] ?? '';
    
    if (empty($numero) || empty($texto)) {
        $status = 'error';
        $mensagem = 'Preencha todos os campos';
    } else {
        $anexos = [];
        if (!empty($anexo)) {
            $anexos[] = $anexo;
        }
        // Envia a mensagem
        $success = sendWhatsAppMessage($numero, $texto, $anexos);
        $status = $success ? 'success' : 'error';
        $mensagem = $success ? 'Mensagem enviada com sucesso!' : 'Erro ao enviar mensagem';
        // Log do resultado da API
        if (file_exists(__DIR__.'/logs/last_dwapi_result.txt')) {
            $result_api = file_get_contents(__DIR__.'/logs/last_dwapi_result.txt');
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teste de Envio WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], textarea { width: 100%; padding: 8px; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #dff0d8; color: #3c763d; }
        .error { background: #f2dede; color: #a94442; }
        .api-result { background: #f9f9f9; border: 1px solid #ccc; padding: 10px; margin-top: 10px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Teste de Envio WhatsApp</h1>
        
        <?php if ($mensagem): ?>
            <div class="message <?= $status ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="numero">Número WhatsApp (apenas dígitos):</label>
                <input type="text" id="numero" name="numero" placeholder="Ex: 554399300953" required>
            </div>

            <div class="form-group">
                <label for="texto">Mensagem:</label>
                <textarea id="texto" name="texto" rows="4" required></textarea>
            </div>

            <div class="form-group">
                <label for="anexo">URL de Anexo (opcional):</label>
                <input type="text" id="anexo" name="anexo" placeholder="https://exemplo.com/arquivo.pdf">
            </div>

            <button type="submit">Enviar Mensagem</button>
        </form>

        <?php if ($result_api): ?>
            <div class="api-result">
                <strong>Resposta da API DWAPI:</strong><br>
                <pre><?= htmlspecialchars($result_api) ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 