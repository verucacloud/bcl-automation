<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config.php';

$configFile = __DIR__ . '/../../config.local.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    exit('File config.local.php belum tersedia.');
}

$tiktokConfig = require $configFile;

if (!is_array($tiktokConfig)) {
    http_response_code(500);
    exit('Isi config.local.php tidak valid.');
}

function tiktokConfigValue($key)
{
    global $tiktokConfig;
    return isset($tiktokConfig[$key]) ? trim((string) $tiktokConfig[$key]) : '';
}

function tiktokEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tiktokHttpRequest($url, $method = 'GET', $headers = [], $body = null)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL belum aktif.');
    }

    $curl = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ];

    if ($body !== null) {
        $curlOptions[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
    }

    curl_setopt_array($curl, $curlOptions);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error !== '') {
        throw new RuntimeException('cURL error: ' . $error);
    }

    return [
        'status' => $httpCode,
        'body' => $response,
        'json' => json_decode((string) $response, true),
    ];
}

function tiktokErrorPage($title, $message, $httpCode = 400)
{
    http_response_code($httpCode);
    $escapedTitle = tiktokEscape($title);
    $escapedMessage = tiktokEscape($message);
    echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedTitle}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-cyan-500/30 rounded-2xl p-6 shadow-2xl text-center">
        <div class="w-16 h-16 bg-cyan-500/10 text-cyan-400 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">!</div>
        <h1 class="text-xl font-bold text-white mb-2">{$escapedTitle}</h1>
        <p class="text-slate-400 text-sm mb-6">{$escapedMessage}</p>
        <a href="../../accounts/index.php" class="inline-block px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-pink-500 hover:from-cyan-600 hover:to-pink-600 text-white rounded-xl text-sm font-semibold transition">Kembali ke Daftar Akun</a>
    </div>
</body>
</html>
HTML;
    exit;
}
