<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação - 7com1</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="icon success">✅</div>
            <h1 class="success">Mensagem Enviada!</h1>
            <p class="message">
                Enviamos uma mensagem para seu WhatsApp com as instruções para ativar seu cadastro.
                Por favor, verifique seu WhatsApp e siga as instruções.
            </p>
        <?php else: ?>
            <div class="icon error">❌</div>
            <h1 class="error">Ops! Algo deu errado</h1>
            <p class="message">
                <?php
                if (isset($_GET['message'])) {
                    echo htmlspecialchars($_GET['message']);
                } else {
                    echo 'Não foi possível enviar a mensagem. Por favor, tente novamente.';
                }
                ?>
            </p>
        <?php endif; ?>
        
        <a href="/" class="button">Voltar ao Início</a>
    </div>
</body>
</html> 