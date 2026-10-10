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

$youtubeConfig = require $configFile;

if (!is_array($youtubeConfig)) {
    http_response_code(500);
    exit('Isi config.local.php tidak valid.');
}

function youtubeConfigValue($key)
{
    global $youtubeConfig;

    return isset($youtubeConfig[$key])
        ? trim((string) $youtubeConfig[$key])
        : '';
}

function youtubeEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function youtubeHttpRequest($url, $method, $headers, $body = null)
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'Ekstensi PHP cURL belum aktif.'
        );
    }

    $curl = curl_init($url);

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]
    );

    if ($body !== null) {
        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            $body
        );
    }

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $error = curl_error($curl);
        curl_close($curl);

        throw new RuntimeException(
            'Google API request gagal: ' . $error
        );
    }

    $statusCode = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    $decoded = json_decode(
        $responseBody,
        true
    );

    if (!is_array($decoded)) {
        $decoded = [
            'raw_response' => $responseBody
        ];
    }

    return [
        'status' => $statusCode,
        'body' => $decoded
    ];
}

function youtubeCreateState()
{
    $state = bin2hex(random_bytes(32));

    $_SESSION['youtube_oauth_state'] = $state;
    $_SESSION['youtube_oauth_started_at'] = time();

    return $state;
}

function youtubeValidateState($receivedState)
{
    $storedState = isset(
        $_SESSION['youtube_oauth_state']
    )
        ? $_SESSION['youtube_oauth_state']
        : '';

    $startedAt = isset(
        $_SESSION['youtube_oauth_started_at']
    )
        ? (int) $_SESSION['youtube_oauth_started_at']
        : 0;

    unset(
        $_SESSION['youtube_oauth_state'],
        $_SESSION['youtube_oauth_started_at']
    );

    if ($storedState === '' || $receivedState === '') {
        return false;
    }

    if (!hash_equals($storedState, $receivedState)) {
        return false;
    }

    if ($startedAt <= 0 || time() - $startedAt > 600) {
        return false;
    }

    return true;
}

function youtubeErrorPage($title, $message, $statusCode = 500)
{
    http_response_code((int) $statusCode);

    $safeTitle = youtubeEscape($title);
    $safeMessage = youtubeEscape($message);

    echo '
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">

        <title>' . $safeTitle . '</title>

        <style>
            body {
                min-height: 100vh;
                display: grid;
                place-items: center;
                margin: 0;
                padding: 24px;
                color: #ffffff;
                background: #020617;
                font-family: Arial, sans-serif;
            }

            .card {
                width: 100%;
                max-width: 620px;
                padding: 32px;
                border: 1px solid rgba(255,255,255,.12);
                border-radius: 24px;
                background: rgba(15,23,42,.95);
            }

            p {
                color: #94a3b8;
                line-height: 1.7;
            }

            a {
                display: inline-block;
                margin-top: 18px;
                padding: 12px 18px;
                color: #ffffff;
                border-radius: 12px;
                background: linear-gradient(
                    135deg,
                    #7c3aed,
                    #0ea5e9
                );
                text-decoration: none;
                font-weight: bold;
            }
        </style>
    </head>

    <body>
        <div class="card">
            <h1>' . $safeTitle . '</h1>
            <p>' . $safeMessage . '</p>

            <a href="../../api_configuration.php">
                Kembali ke API Configuration
            </a>
        </div>
    </body>
    </html>
    ';

    exit;
}