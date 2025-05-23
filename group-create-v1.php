<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
define("OK_LOADME", true);
require_once("dwapi-send-v1.php");
require_once("log.php");

// Recebe dados via POST ou GET
$numero = $_POST['numero'] ?? $_GET['numero'] ?? '';
$patrocinador = $_POST['patrocinador'] ?? $_GET['patrocinador'] ?? '';
$admin = '43999300593';

if (empty($numero) || empty($patrocinador)) {
    die(json_encode(['status' => 'error', 'message' => 'Dados insuficientes']));
}

// Exemplo de chamada à API DW para criar grupo (ajuste conforme sua API DW)
function criarGrupo($nome, $membros, $admin) {
    // Aqui você deve implementar a chamada real à API DW para criar grupo
    // Exemplo fictício:
    return [
        'status' => 'success',
        'link' => 'https://chat.whatsapp.com/' . md5($nome . time())
    ];
}

// Grupo do líder
$nome_lider = "7com1 - $patrocinador";
$membros_lider = [$admin, $patrocinador, $numero];
$grupo_lider = criarGrupo($nome_lider, $membros_lider, $admin);

// Grupo do novo franqueado
$nome_novo = "7com1 - $numero";
$membros_novo = [$admin, $numero];
$grupo_novo = criarGrupo($nome_novo, $membros_novo, $admin);

logEvent('group_created', [
    'grupo_lider' => $grupo_lider,
    'grupo_novo' => $grupo_novo
], 'success');

die(json_encode([
    'status' => 'success',
    'grupo_lider' => $grupo_lider['link'],
    'grupo_novo' => $grupo_novo['link']
])); 