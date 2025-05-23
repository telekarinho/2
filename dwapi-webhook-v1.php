<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
define("OK_LOADME", true);
require_once("dwapi-send-v1.php");
require_once("log.php");

// Recebe JSON da DW API
$input = file_get_contents('php://input');
file_put_contents(__DIR__.'/logs/last_webhook_rawinput.log', $input . "\nPOST=" . var_export($_POST, true) . "\n", FILE_APPEND); // Loga tudo recebido

$data = json_decode($input, true);
if (!$data || !is_array($data)) {
    // Se não for JSON, tenta pegar do POST
    $data = $_POST;
    file_put_contents(__DIR__.'/logs/last_webhook_debug.log', "[FALLBACK POST] " . var_export($data, true) . "\n", FILE_APPEND);
} else {
    file_put_contents(__DIR__.'/logs/last_webhook_debug.log', "[JSON] " . var_export($data, true) . "\n", FILE_APPEND);
}

// Compatibilidade máxima de campos
$from = $data['sender'] ?? $data['from'] ?? $data['receiver'] ?? '';
$text = $data['text'] ?? $data['body'] ?? $data['msgtext'] ?? '';

if (empty($from) || empty($text)) {
    http_response_code(400);
    die('Dados insuficientes');
}

// Extrai patrocinador do texto (exemplo: "Quero me cadastrar no ID de indicação de 5543999300953")
preg_match('/ID(?: de indica(?:ção|cao))? de ([0-9]{10,15})/i', $text, $matches);
$patrocinador = $matches[1] ?? '';

if (empty($patrocinador)) {
    logEvent('webhook_error', ['from' => $from, 'text' => $text], 'error');
    http_response_code(400);
    die('Patrocinador não encontrado');
}

// Gera código de ativação
$codigo = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Gera senha de 4 dígitos
$password = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Gera dados do usuário
$username = $from;
$firstname = substr($from, 4, 4);
$lastname = substr($from, 8);
$email = $from . '@7com1.com';
$ppid = 1;
$idref = substr($patrocinador, 0, 2);

// Corrige o número para uso como identificador único
$numero_corrigido = preg_replace('/[^0-9]/', '', $data['recnumber'] ?? $data['receiver'] ?? $data['sender'] ?? '');
if (substr($numero_corrigido, 0, 2) === '55') {
    if (strlen($numero_corrigido) == 12) {
        $ddd = substr($numero_corrigido, 2, 2);
        $inicio = substr($numero_corrigido, 4, 1);
        if (!in_array($inicio, ['2', '3', '4', '5'])) {
            $numero_corrigido = substr($numero_corrigido, 0, 4) . '9' . substr($numero_corrigido, 4);
        }
    }
}
// Gera nome e email com base no número corrigido
$firstname = substr($numero_corrigido, 4, 4);
$lastname = substr($numero_corrigido, 8);
$username = $numero_corrigido;
$email = $numero_corrigido . '@7com1.com';
$ppid = 1;
$idref = substr($patrocinador, 0, 2);

// Salva status/código para validação posterior
$verificacoes_dir = __DIR__ . '/verificacoes';
if (!file_exists($verificacoes_dir)) mkdir($verificacoes_dir, 0777, true);
$verificacao_file = $verificacoes_dir . '/' . $numero_corrigido . '.json';
file_put_contents($verificacao_file, json_encode([
    'numero' => $numero_corrigido,
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

// Monta mensagem modelo
$link_ativacao = "https://7c1.pro/a.php?n={$numero_corrigido}&c={$codigo}";
$mensagem = "👋 Olá! Aqui é o time 7com1.\n\n"
    . "Recebemos seu pedido de cadastro!\n\n"
    . "Você está se cadastrando na rede de mini franqueados de:\n"
    . "$patrocinador\n\n"
    . "🔐 Ative seu cadastro clicando no link:  \nhttps://7c1.pro/a.php?n={$numero_corrigido}&c={$codigo}\n\n"
    . "📎 Abaixo segue Vídeo e PDF com A APN.\n\n"
    . "⚠️ Salve nosso número para ativar os links e receber atualizações da sua rede.";

// Envia mensagem via DW API com anexos (URLs públicas)
$anexos = [
    'https://7c1.pro/boasvindas.mp4',
    'https://7c1.pro/apresentacao7com1.pdf'
];
$response = sendWhatsAppMessage(
    $numero_corrigido,
    $mensagem,
    $anexos,
    '43999300593'
);

// Log detalhado do retorno da API DW
file_put_contents(__DIR__.'/logs/last_dwapi_response.log', date('Y-m-d H:i:s') . "\nTO: $numero_corrigido\n" . var_export($response, true) . "\n\n", FILE_APPEND);

if (!$response) {
    logEvent('webhook_sent', [
        'to' => $numero_corrigido,
        'mensagem' => $mensagem,
        'response' => $response,
        'erro_dwapi' => is_array($response) ? json_encode($response) : $response // log detalhado
    ], 'error');
} else {
    logEvent('webhook_sent', [
        'to' => $numero_corrigido,
        'mensagem' => $mensagem,
        'response' => $response
    ], 'success');
}

// Ativação via código de 4 dígitos
if (preg_match('/^\d{4}$/', trim($text))) {
    $codigo = trim($text);
    $verificacao_file = __DIR__ . '/verificacoes/' . $numero_corrigido . '.json';
    if (file_exists($verificacao_file)) {
        $dados = json_decode(file_get_contents($verificacao_file), true);
        if ($dados['codigo'] === $codigo && $dados['status'] !== 'confirmado' && (time() - $dados['timestamp'] <= 3600)) {
            // Dados do usuário
            $senha_numerica = $dados['password'];
            $senha_md5 = md5($senha_numerica);
            $in_date = date('Y-m-d H:i:s');
            $firstname = substr($numero_corrigido, 0, 4);
            $lastname = substr($numero_corrigido, 4);
            $username = $numero_corrigido;
            $email = $numero_corrigido . '@7com1.com';
            $ppid = $dados['ppid'] ?? 1;
            $patrocinador_num = $dados['patrocinador'] ?? '';
            $isconfirm = 1;
            $log_ip = $data['ip'] ?? '';
            $country = 'BR';
            // Buscar o ID do patrocinador pelo número
            $idref = 0;
            if ($patrocinador_num) {
                $sql = $db->getRecFrmQry("SELECT id FROM ".DB_TBLPREFIX."_mbrs WHERE username = '".$patrocinador_num."' LIMIT 1");
                if (count($sql) > 0) {
                    $idref = $sql[0]['id'];
                }
            }
            $mbrtokenval = "|refbyidmbr:{$idref}|";
            $data_insert = [
                'in_date'    => $in_date,
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'username'   => $username,
                'email'      => $email,
                'password'   => $senha_md5,
                'isconfirm'  => $isconfirm,
                'log_ip'     => $log_ip,
                'country'    => $country,
                'mylang'     => '',
                'mbrtoken'   => $mbrtokenval
            ];
            $ok = $db->insert(DB_TBLPREFIX . '_mbrs', $data_insert);
            $userid = $db->lastInsertId();
            if ($ok) {
                $dados['status'] = 'confirmado';
                $dados['senha'] = $senha_numerica;
                $dados['userid'] = $userid;
                file_put_contents($verificacao_file, json_encode($dados, JSON_PRETTY_PRINT));
                // Mensagem de confirmação
                $login_url = "https://7c1.pro/member";
                $login_auto = "https://7c1.pro/token.php?uid={$userid}&token=" . md5($userid . $senha_numerica);
                $link_indicacao = "https://7c1.pro/{$username}";
                $link_grupo_lider = $link_grupo_lider ?? '[link_grupo_lider]';
                $link_grupo_meulider = $link_grupo_frq ?? '[link_grupo_meulider]';
                $mensagem_final = "✅ Cadastro concluído com sucesso!  \nSeja bem-vindo à 7com1\n\n"
                    . "🎯 Acesse seu painel:  \nhttps://7c1.pro/member\n\n"
                    . "�� Usuário: {$numero_corrigido}  \n🔐 Senha: {$password}  \n\n"
                    . "👥 Grupo do seu líder:  \n{$link_grupo_lider}\n\n"
                    . "👑 Você é líder deste grupo (com seus 7 indicados):  \n{$link_grupo_meulider}\n\n"
                    . "📣 Após a ativação, seu link de indicação será:  \nhttps://7c1.pro/id/{$numero_corrigido}\n\n"
                    . "🚀 Agora você faz parte da nossa rede de Mini Franqueados!";
                sendWhatsAppMessage($numero_corrigido, $mensagem_final, [], '43999300593');
                logEvent('registration_finalized', [
                    'numero' => $numero_corrigido,
                    'codigo' => $codigo,
                    'userid' => $userid
                ], 'success');
                echo 'OK';
                exit;
            }
        }
    }
    // Se não ativou, pode responder com erro ou ignorar
}

echo 'OK'; 