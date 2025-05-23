<?php
function sendWhatsAppMessage($numero, $mensagem, $anexos = [], $from = '43999300593') {
    $api_url = 'https://api.dw-api.com/send';
    $api_token = 'LABCLORYFML093'; // Seu token DWAPI

    // Envia mensagem de texto
    $data = [
        'receiver' => $numero,
        'msgtext'  => $mensagem,
        'token'    => $api_token
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code !== 200 || strpos($result, 'success') === false) {
        return false;
    }

    // Envia cada anexo separadamente
    if (!empty($anexos)) {
        foreach ($anexos as $anexo) {
            $data_anexo = [
                'receiver' => $numero,
                'msgtext'  => '',
                'token'    => $api_token,
                'mediaurl' => $anexo
            ];
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_anexo));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            $result_anexo = curl_exec($ch);
            $http_code_anexo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code_anexo !== 200 || strpos($result_anexo, 'success') === false) {
                return false;
            }
        }
    }
    return true;
} 