<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<b>Teste de curl_init:</b><br>";
$ch = curl_init("https://api.telegram.org");
if ($ch) {
    echo "curl_init OK<br>";
    curl_close($ch);
} else {
    echo "curl_init FALHOU<br>";
}

echo "<b>Versão do PHP:</b> " . phpversion() . "<br>";

echo "<b>Funções desabilitadas:</b> ";
echo ini_get('disable_functions'); 