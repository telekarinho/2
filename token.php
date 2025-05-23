<?php
// token.php seguro e compatível com Unimatrix
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
session_start();
define("OK_LOADME", true);
require_once("common/init.loader.php");
require_once("common/init.db.php");

$uid = $_GET['uid'] ?? '';
$token = $_GET['token'] ?? '';

if (!$uid || !$token) {
    echo "Parâmetros inválidos.";
    exit;
}

$sql = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_mbrs WHERE id = '$uid' LIMIT 1");

if (count($sql) > 0) {
    $user = $sql[0];
    $senha_md5 = md5($user['id'] . $user['password']);
    
    if ($senha_md5 === $token) {
        $_SESSION['userlog'] = $user['username'];
        $_SESSION['password'] = $user['password'];
        $_SESSION['usercountry'] = $user['country'];
        $_SESSION['fullname'] = $user['firstname'] . " " . $user['lastname'];
        $_SESSION['ppidsess'] = $user['id'];
        $_SESSION['clpcsess'] = $user['mbrtoken'];

        header("Location: member/index.php");
        exit;
    }
}

echo "Token inválido ou usuário não encontrado.";
exit; 