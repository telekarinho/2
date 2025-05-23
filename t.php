<?php
define('OK_LOADME', true);
require_once('common/init.loader.php');

// Função para enviar mensagem pelo Telegram
function sendTelegramMessage($chat_id, $text, $parse_mode = 'HTML') {
    global $botToken;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Verifica parâmetros
$numero = $_GET['n'] ?? '';
$codigo = $_GET['c'] ?? '';

if (empty($numero) || empty($codigo)) {
    die("Parâmetros inválidos");
}

// Verifica arquivo JSON
$json_file = 'verificacoes/' . $numero . '.json';
if (!file_exists($json_file)) {
    die("Verificação não encontrada");
}

// Carrega dados do JSON
$user_data = json_decode(file_get_contents($json_file), true);

// Valida código
if ($user_data['codigo'] !== $codigo) {
    die("Código inválido");
}

// Verifica se já foi ativado
if (isset($user_data['activated']) && $user_data['activated']) {
    die("Este cadastro já foi ativado");
}

try {
    // Inicia transação
    $db->beginTransaction();

    // Cria o usuário no sistema
    $mbrdata = array(
        'username' => $user_data['numero'],
        'password' => getpasshash($user_data['senha']),
        'email' => $user_data['email'],
        'firstname' => $user_data['numero'],
        'lastname' => 'Telegram',
        'mbrsite' => '',
        'mpstatus' => 1,
        'emailaddr' => $user_data['email'],
        'phver' => 1,
        'phonenum' => $user_data['numero'],
        'telegram_id' => $user_data['numero'],
        'mpid' => $user_data['sponsor_id'],
        'mpdepth' => 0,
        'sprlist' => '',
        'getcycle' => 0,
        'reg_date' => date('Y-m-d H:i:s'),
        'reg_ip' => get_client_ip()
    );

    // Insere usuário
    $db->insert(DB_TBLPREFIX . '_mbrs', $mbrdata);
    $newmbrid = $db->lastInsertId();

    // Atualiza sprlist (lista de patrocinadores)
    $sprlist = getsprlistid($user_data['sponsor_id']);
    $data = array(
        'sprlist' => $sprlist,
    );
    $update = $db->update(DB_TBLPREFIX . '_mbrs', $data, array('id' => $newmbrid));

    // Cria grupos internos
    $groupdata = array(
        'owner_id' => $newmbrid,
        'group_name' => 'Grupo ' . $user_data['numero'],
        'group_description' => 'Grupo de rede do usuário ' . $user_data['numero'],
        'created_date' => date('Y-m-d H:i:s')
    );
    $db->insert(DB_TBLPREFIX . '_groups', $groupdata);

    // Marca como ativado no JSON
    $user_data['activated'] = true;
    $user_data['activation_date'] = date('Y-m-d H:i:s');
    file_put_contents($json_file, json_encode($user_data));

    // Commit transação
    $db->commit();

    // Envia mensagem de sucesso via Telegram
    $success_message = "✅ Cadastro concluído com sucesso!\n\n";
    $success_message .= "🎯 Acesse seu painel:\n";
    $success_message .= "https://7c1.pro/member\n\n";
    $success_message .= "👤 Usuário: {$user_data['numero']}\n";
    $success_message .= "🔐 Senha: {$user_data['senha']}\n\n";
    $success_message .= "📣 Seus grupos de rede:\n\n";
    $success_message .= "➡️ Você faz parte do grupo do seu líder:\n";
    $success_message .= "https://7c1.pro/chat/grupo/{$user_data['sponsor_id']}\n\n";
    $success_message .= "➡️ Esse é o seu grupo como líder (aguarde seus indicados):\n";
    $success_message .= "https://7c1.pro/chat/grupo/{$user_data['numero']}\n\n";
    $success_message .= "🚀 Link de indicação:\n";
    $success_message .= "https://7c1.pro/id/{$user_data['numero']}\n\n";
    $success_message .= "⚠️ Fique ligado nas notificações por aqui para novidades da sua rede!";

    sendTelegramMessage($user_data['numero'], $success_message);

    // Exibe página de sucesso
    echo "✅ Cadastro ativado com sucesso! Verifique as instruções enviadas no Telegram.";

} catch (Exception $e) {
    $db->rollBack();
    error_log("Erro na ativação Telegram: " . $e->getMessage());
    die("Erro ao ativar cadastro. Por favor, tente novamente.");
}
?> 