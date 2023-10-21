<?php

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = $_ENV['TELEGRAM_TOKEN'];
$webhookUrl = $_ENV['BOTPRESS_URL'];

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message']['photo']) || isset($update['message']['document'])) {
    $chatId = $update['message']['chat']['id'];

    $responseText = 'Arquivo recebido e enviado para análise! Volte em breve para visualizar o status do seu atestado.';

    $file;

    if (isset($update['message']['photo'])) {
        $file = $update['message']['photo'][0];
    }
    if (isset($update['message']['document'])) {
        $file = $update['message']['document'];
    }

    saveReceivedFile($file, $chatId);

    sendTelegramMessage($chatId, $responseText);

    disableWebhook($token);

    activateWebhook($token, $webhookUrl);
} else {
    disableWebhook($token);

    activateWebhook($token, $webhookUrl);
}

function sendTelegramMessage($chatId, $text)
{
    global $token;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);
}

function saveReceivedFile($file, $chatId)
{
    global $token;
    $fileId = $file['file_id'];
    $fileUrl = "https://api.telegram.org/bot$token/getFile?file_id=$fileId";
    $fileInfo = json_decode(file_get_contents($fileUrl), true);

    if ($fileInfo['ok']) {
        $filePath = $fileInfo['result']['file_path'];
        $fileUrl = "https://api.telegram.org/file/bot$token/$filePath";
        $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $fileExtension;

        $logFile = 'image_urls.log';
        $logMessage = date('Y-m-d H:i:s') . ' - ' . $fileUrl . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        $apiUrl = "http://127.0.0.1:8000/api/chatbot/atestados/$chatId";
        $ch = curl_init($apiUrl);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

        $postData = [
            'url' => $fileUrl,
            'file_name' => $fileName,
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);

        curl_close($ch);
    }
}

function disableWebhook($token)
{
    $url = "https://api.telegram.org/bot$token/deleteWebhook";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);
}

function activateWebhook($token, $webhookUrl)
{
    $url = "https://api.telegram.org/bot$token/setWebhook?url=$webhookUrl";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);
}

http_response_code(200);
