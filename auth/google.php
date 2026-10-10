<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$sessionFile = __DIR__ . '/session.php';
$googleClientFile = __DIR__ . '/google_client.php';

if (!is_file($sessionFile)) {
    http_response_code(500);

    exit(
        'File session.php tidak ditemukan di: ' .
        htmlspecialchars($sessionFile, ENT_QUOTES, 'UTF-8')
    );
}

require_once $sessionFile;

if (!is_file($googleClientFile)) {
    http_response_code(500);

    exit(
        'File google_client.php tidak ditemukan di: ' .
        htmlspecialchars($googleClientFile, ENT_QUOTES, 'UTF-8')
    );
}

require_once $googleClientFile;

if (!function_exists('createGoogleClient')) {
    http_response_code(500);

    exit(
        'Function createGoogleClient() tidak ditemukan. ' .
        'Periksa isi file auth/google_client.php.'
    );
}

try {
    $client = createGoogleClient();

    $state = bin2hex(random_bytes(32));

    $_SESSION['oauth_state'] = $state;

    $client->setState($state);

    $authUrl = $client->createAuthUrl();

    if ($authUrl === '') {
        throw new RuntimeException(
            'Google authorization URL gagal dibuat.'
        );
    }

    header('Location: ' . $authUrl);
    exit;
} catch (Throwable $error) {
    error_log(
        'Google OAuth start error: ' .
        $error->getMessage() .
        ' in ' .
        $error->getFile() .
        ':' .
        $error->getLine()
    );

    http_response_code(500);

    echo '<h1>Google OAuth Error</h1>';
    echo '<p><strong>Pesan:</strong> ' .
        htmlspecialchars(
            $error->getMessage(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) .
        '</p>';

    echo '<p><strong>File:</strong> ' .
        htmlspecialchars(
            $error->getFile(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) .
        '</p>';

    echo '<p><strong>Baris:</strong> ' .
        (int) $error->getLine() .
        '</p>';

    exit;
}