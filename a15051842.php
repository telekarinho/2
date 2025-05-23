<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
define("OK_LOADME", true);
require_once("common/init.loader.php"); // Para getpasshash
require_once("common/init.db.php");
require_once("dwapi-send-v1.php");
require_once("log.php");
// Se quiser criar grupos, descomente a linha abaixo
// require_once("group-create-v1.php");

$numero = $_GET['n'] ?? '';
$codigo = $_GET['c'] ?? '';

// Corrigir número possivelmente incompleto (apenas para Brasil - DDI 55)
$numero_corrigido = $numero;
if (substr($numero, 0, 2) === '55') {
    if (strlen($numero) == 12) {
        $ddd = substr($numero, 2, 2);
        $inicio = substr($numero, 4, 1);
        // Insere o 9 apenas se o número parece ser de celular
        if (!in_array($inicio, ['2', '3', '4', '5'])) {
            $numero_corrigido = substr($numero, 0, 4) . '9' . substr($numero, 4);
        }
    }
} else {
    // Não faz nenhuma alteração em números internacionais
    $numero_corrigido = $numero;
}

if (empty($numero_corrigido) || empty($codigo)) {
    die("Parâmetros inválidos");
}

// VERIFICAÇÃO DE DUPLICIDADE NO BANCO
$existe = $db->getRecFrmQry("SELECT * FROM ".DB_TBLPREFIX."_mbrs WHERE username = '".$numero_corrigido."' LIMIT 1");
if (!empty($existe)) {
    // Usuário já existe, bloqueia cadastro
    $mensagem = "✅ Você já está cadastrado na 7com1!\n\n" .
        "🎯 Acesse seu painel:\nhttps://7c1.pro/member\n\n" .
        "👤 Usuário: {$numero_corrigido}\n🔐 Senha: [Use a opção 'Esqueci minha senha']\n\n" .
        "📣 Grupos de Rede:\n" .
        "➡️ Grupo do seu líder:\nhttps://7c1.pro/chat/grupo.php?n={$existe[0]['patrocinador']}\n\n" .
        "➡️ Seu grupo como líder:\nhttps://7c1.pro/chat/grupo.php?n={$numero_corrigido}\n\n" .
        "🚀 Link de indicação:\nhttps://7c1.pro/id/{$numero_corrigido}\n\n" .
        "⚠️ Salve nosso número no WhatsApp para não perder nenhuma atualização da sua rede!";
    sendWhatsAppMessage($numero_corrigido, $mensagem, [], '43999300593');
    exit;
}

// Se chegou aqui, é um novo cadastro
$verificacao_file = __DIR__ . '/verificacoes/' . $numero_corrigido . '.json';
if (!file_exists($verificacao_file)) {
    die("Registro não encontrado");
}
$dados = json_decode(file_get_contents($verificacao_file), true);

// Garante que a senha esteja no arquivo de verificação
if (!isset($dados['senha']) && isset($dados['password'])) {
    $dados['senha'] = $dados['password'];
    if (!is_array($dados)) {
        die("Erro ao processar dados do usuário.");
    }
    file_put_contents($verificacao_file, json_encode($dados, JSON_PRETTY_PRINT));
}

if (trim(strval($dados['codigo'])) !== trim(strval($codigo))) {
    die("Código inválido");
}
if (time() - $dados['timestamp'] > 3600) {
    unlink($verificacao_file);
    die("Código expirado. Solicite um novo link de ativação.");
}

// Dados do usuário
$senha_numerica = $dados['password'];
$hashedpassword = getpasshash($senha_numerica); // CORRETO!
$in_date = date('Y-m-d H:i:s');
$firstname = substr($numero_corrigido, 0, 4);
$lastname = substr($numero_corrigido, 4);
$username = $numero_corrigido; // número completo!
$email = $numero_corrigido . '@7com1.com';
$patrocinador_num = $dados['patrocinador'] ?? '';
$isconfirm = 1;
$log_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$country = 'BR';

// Buscar o ID do patrocinador pelo número
$idref = 0;
if ($patrocinador_num) {
    $sql = $db->getRecFrmQry("SELECT id FROM ".DB_TBLPREFIX."_mbrs WHERE username = '".$patrocinador_num."' LIMIT 1");
    if (count($sql) > 0) {
        $idref = $sql[0]['id'];
    } else {
        // Patrocinador não existe, bloqueia o cadastro
        $mensagem = "❌ O patrocinador informado ({$patrocinador_num}) não existe em nossa base.\n\nPor favor, peça ao seu patrocinador para te enviar o link correto ou entre em contato com o suporte.";
        sendWhatsAppMessage($numero_corrigido, $mensagem, [], '43999300593');
        die("Patrocinador inexistente. Cadastro bloqueado.");
    }
}

// Gerar token de login automático
$token = md5(uniqid($username . time(), true));
$mbrtokenval = "|refbyidmbr:{$idref}|,|logtoken:{$token}|";

// Monta o array de dados para inserção
$data = [
    'in_date'    => $in_date,
    'firstname'  => $firstname,
    'lastname'   => $lastname,
    'username'   => $username,
    'email'      => $email,
    'password'   => $hashedpassword,
    'isconfirm'  => $isconfirm,
    'log_ip'     => $log_ip,
    'country'    => $country,
    'mylang'     => '',
    'mbrtoken'   => $mbrtokenval
];
$ok = $db->insert(DB_TBLPREFIX . '_mbrs', $data);
$userid = $db->lastInsertId();

if ($ok && $userid) {
    // --- CRIAÇÃO AUTOMÁTICA DOS GRUPOS INTERNOS DO CHAT 7COM1 ---
    // Log para depuração
    logEvent('chat_group_creation', [
        'userid' => $userid,
        'patrocinador_num' => $patrocinador_num,
        'idref' => $idref
    ], 'info');

    // Cria o grupo do líder (inclui o novo usuário como membro)
    if (!empty($patrocinador_num) && !empty($idref)) {
        $url_lider = "https://7c1.pro/chat/auto-grupos.php?id={$idref}&add={$userid}";
        @file_get_contents($url_lider);
        logEvent('chat_group_lider', ['url' => $url_lider], 'info');
    }
    // Cria o grupo do próprio usuário (ele como líder, outros 7 virão)
    $url_novo = "https://7c1.pro/chat/auto-grupos.php?id={$userid}";
    @file_get_contents($url_novo);
    logEvent('chat_group_novo', ['url' => $url_novo], 'info');

    // Atualiza status do arquivo de verificação
    $dados['status'] = 'confirmado';
    $dados['senha'] = $senha_numerica;
    $dados['userid'] = $userid;
    // Links dos grupos internos do chat
    $link_grupo_lider_chat = $patrocinador_num ? "https://7c1.pro/chat/grupo.php?n={$patrocinador_num}" : '';
    $link_grupo_novo_chat = "https://7c1.pro/chat/grupo.php?n={$numero_corrigido}";
    $dados['grupo_lider_chat'] = $link_grupo_lider_chat;
    $dados['grupo_novo_chat'] = $link_grupo_novo_chat;
    file_put_contents($verificacao_file, json_encode($dados, JSON_PRETTY_PRINT));

    // Mensagem inicial com vídeo e PDF
    $mensagem_inicial = "👋 Olá! Aqui é o time 7com1.\n\nRecebemos seu pedido de cadastro!\n\nVocê está se cadastrando na rede de mini franqueados de:\n{$patrocinador_num}\n\n🔐 Ative seu cadastro clicando no link:\nhttps://7c1.pro/a.php?n={$numero_corrigido}&c={$codigo}\n\n📎 Abaixo segue Vídeo e PDF com a apresentação (APN).\n\n⚠️ Salve nosso número para ativar os links e receber atualizações da sua rede.";
    
    // Envia mensagem inicial com vídeo e PDF no WhatsApp
    $media_files = [
        'https://7c1.pro/apn7com1.mp4',
        'https://7c1.pro/apn7com1.pdf'
    ];
    sendWhatsAppMessage($numero_corrigido, $mensagem_inicial, $media_files, '43999300593');

    // Mensagem final (WhatsApp e Telegram)
    $mensagem_final = "✅ Cadastro concluído com sucesso!\n\nSeja bem-vindo à 7com1!\n\n🎯 Acesse seu painel:\nhttps://7c1.pro/member\n\n👤 Usuário: {$numero_corrigido}\n🔐 Senha: {$senha_numerica}\n\n📣 Seus grupos de rede:\n\n";
    if ($link_grupo_lider_chat) {
        $mensagem_final .= "➡️ Você faz parte do grupo do seu líder:\n{$link_grupo_lider_chat}\n\n";
    }
    $mensagem_final .= "➡️ Esse é o seu grupo como líder (aguarde seus indicados):\n{$link_grupo_novo_chat}\n\n";
    $mensagem_final .= "🚀 Link de indicação para convidar novos franqueados:\nhttps://7c1.pro/id/{$numero_corrigido}\n\n⚠️ Salve o nosso número no WhatsApp para atualizações importantes também.";
    
    // Envia mensagem final no WhatsApp
    sendWhatsAppMessage($numero_corrigido, $mensagem_final, [], '43999300593');
    
    // Envia no Telegram (se houver telegram_id)
    if (!empty($dados['telegram_id'])) {
        require_once __DIR__ . '/telegram-bot.php';
        sendTelegramMessage($dados['telegram_id'], $mensagem_final);
    }
    
    // Envia mensagem de marketing com vídeo e PDF
    require_once __DIR__ . '/send-marketing.php';
    sendMarketingMessage($numero_corrigido, $numero_corrigido);
    
    // Integração automática com matrix e grupos de chat
    require_once __DIR__ . '/common/chat.matrix.sync.php';
    syncChatMatrix($numero_corrigido, $patrocinador_num, '43999300593');

    // Envio do fluxo completo de onboarding 7com1
    require_once __DIR__ . '/common/onboarding7com1.php';
    enviarFluxoCompletoCadastro7com1(
        $numero_corrigido,
        $senha_numerica,
        "https://7c1.pro/a.php?n={$numero_corrigido}&c={$codigo}",
        $patrocinador_num,
        $link_grupo_lider_chat,
        $link_grupo_novo_chat
    );
    
    // Integração Stripe Connect: cria conta Stripe Connect Express para o novo afiliado
    $member = $db->getOne("SELECT * FROM ".DB_TBLPREFIX."_mbrs WHERE id = {$userid}");
    require_once __DIR__ . '/modules/payment/stripe_create_on_register.php';
    if (is_array($member)) {
        try {
            create_stripe_connect_account($member);
        } catch (Exception $e) {
            error_log('Erro Stripe Connect: ' . $e->getMessage());
        }
    }

    logEvent('registration_finalized', [
        'numero' => $numero_corrigido,
        'codigo' => $codigo,
        'userid' => $userid
    ], 'success');
    header("Location: confirmacao.php?status=success");
    exit;
} else {
    logEvent('registration_error', [
        'numero' => $numero_corrigido,
        'codigo' => $codigo,
        'error' => $db->errorInfo ?? 'Erro ao inserir'
    ], 'error');
    die("Erro ao cadastrar. Tente novamente.");
}

// Função para criar grupos no Telegram e retornar os links
function criarGruposTelegram($numero_lider, $numero_novo) {
    // Simulação: use a API real do Telegram para criar grupos e obter links
    $link_grupo_lider = "https://t.me/7com1_lider_{$numero_lider}";
    $link_grupo_novo = "https://t.me/7com1_lider_{$numero_novo}";
    return [$link_grupo_lider, $link_grupo_novo];
}

// BLOQUEIO DE CADASTRO DUPLICADO
if (isset($dados['status']) && $dados['status'] === 'confirmado') {
    // Busca a senha real no banco de dados de senhas
    $senha_query = $db->getRecFrmQry("SELECT senha FROM ".DB_TBLPREFIX."_senhas WHERE username = '".$numero_corrigido."' ORDER BY id DESC LIMIT 1");
    $senha_real = !empty($senha_query) ? $senha_query[0]['senha'] : '[Use a opção \"Esqueci minha senha\"]';

    $mensagem = "✅ Você já está cadastrado na 7com1!\n\n" .
        "🎯 Acesse seu painel:\nhttps://7c1.pro/member\n\n" .
        "👤 Usuário: {$numero_corrigido}\n🔐 Senha: {$senha_real}\n\n" .
        "📣 Grupos de Rede:\n" .
        "➡️ Grupo do seu líder:\nhttps://7c1.pro/chat/grupo.php?n={$dados['patrocinador']}\n\n" .
        "➡️ Seu grupo como líder:\nhttps://7c1.pro/chat/grupo.php?n={$numero_corrigido}\n\n" .
        "🚀 Link de indicação:\nhttps://7c1.pro/id/{$numero_corrigido}\n\n" .
        "⚠️ Salve nosso número no WhatsApp para não perder nenhuma atualização da sua rede!";
    sendWhatsAppMessage($numero_corrigido, $mensagem, [], '43999300593');
    die("Cadastro já confirmado. Mensagem reenviada.");
} 