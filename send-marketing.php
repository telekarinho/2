<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
define("OK_LOADME", true);

require_once("common/init.loader.php");
require_once("common/init.db.php");
require_once("dwapi-send-v1.php");
require_once("log.php");

try {
    // Função para enviar mensagem de marketing
    function sendMarketingMessage($numero, $id_indicacao) {
        try {
            // Log do início do envio
            logEvent('marketing_start', [
                'numero' => $numero,
                'id_indicacao' => $id_indicacao
            ], 'info');

            // Gerar link WhatsApp corretamente codificado
            $id_indicacao = $id_indicacao;
            $mensagem = urlencode("Quero me cadastrar no ID de indicação de $id_indicacao");
            $link_wa = "https://wa.me/5543999300953?text=$mensagem";

            // Mensagem principal
            $mensagem1 = "🚀 Já pensou começar com apenas 1 dólar e construir uma renda recorrente em dólar? 💸🌍\n\n"
                . "👉 Plataforma simples, acessível e cheia de benefícios:\n"
                . "✅ Cadastro instantâneo via WhatsApp\n"
                . "✅ Cursos 100% online e certificados\n"
                . "✅ Sorteios mensais de até R$ 10.000\n"
                . "✅ Ganhos reais todos os meses\n"
                . "✅ E em breve: consultas médicas 24h com mais de 20 especialidades\n\n"
                . "📲 Link exclusivo para você indicar e ganhar:\n"
                . "https://7c1.pro/id/{$id_indicacao}\n\n"
                . "Ou envie direto no WhatsApp:\n"
                . "$link_wa";

            // Mensagem complementar
            $mensagem2 = "💡 Dica de ouro:\n\nComece agora com seu link de indicação e mostre o vídeo para pelo menos 3 pessoas ainda hoje!\n\nQuanto mais rápido você compartilha, mais rápido sua rede cresce! 🌱🚀";

            // Enviar vídeo
            $video_url = 'https://7c1.pro/apn7com1.mp4';
            $video_result = sendWhatsAppMessage($numero, "📺 Assista ao vídeo de apresentação:", [$video_url], '43999300593');
            logEvent('marketing_video', [
                'numero' => $numero,
                'result' => $video_result
            ], $video_result ? 'success' : 'error');

            sleep(2);

            // Enviar mensagem principal
            $msg1_result = sendWhatsAppMessage($numero, $mensagem1, [], '43999300593');
            logEvent('marketing_message1', [
                'numero' => $numero,
                'result' => $msg1_result
            ], $msg1_result ? 'success' : 'error');

            sleep(2);

            // Enviar PDF
            $pdf_url = 'https://7c1.pro/apn7com1.pdf';
            $pdf_result = sendWhatsAppMessage($numero, "📄 Baixe o PDF com todas as informações:", [$pdf_url], '43999300593');
            logEvent('marketing_pdf', [
                'numero' => $numero,
                'result' => $pdf_result
            ], $pdf_result ? 'success' : 'error');

            sleep(2);

            // Enviar mensagem final
            $msg2_result = sendWhatsAppMessage($numero, $mensagem2, [], '43999300593');
            logEvent('marketing_message2', [
                'numero' => $numero,
                'result' => $msg2_result
            ], $msg2_result ? 'success' : 'error');

            // Log do sucesso
            logEvent('marketing_complete', [
                'numero' => $numero,
                'video' => $video_result,
                'message1' => $msg1_result,
                'pdf' => $pdf_result,
                'message2' => $msg2_result
            ], 'success');

            return true;
        } catch (Exception $e) {
            logEvent('marketing_error', [
                'numero' => $numero,
                'error' => $e->getMessage()
            ], 'error');
            return false;
        }
    }

    // Roda via GET
    if (isset($_GET['numero']) && isset($_GET['id'])) {
        $numero = preg_replace('/[^0-9]/', '', $_GET['numero']);
        $id = trim($_GET['id']);

        if (strlen($numero) >= 10) {
            $result = sendMarketingMessage($numero, $id);
            echo json_encode([
                'success' => $result,
                'numero' => $numero,
                'id' => $id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Número inválido',
                'numero' => $numero
            ]);
        }
        exit;
    }
} catch (Throwable $e) {
    echo '<pre style="color:red;background:#fff;padding:16px;">Erro fatal: ' . $e->getMessage() . "\nArquivo: " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . '</pre>';
    if (function_exists('logEvent')) {
        logEvent('marketing_fatal_error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 'error');
    }
    exit;
} 