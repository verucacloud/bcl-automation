<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$clientKey = tiktokConfigValue('tiktok_client_key');
$clientSecret = tiktokConfigValue('tiktok_client_secret');
$redirectUri = tiktokConfigValue('tiktok_redirect_uri');

if ($clientKey === '') {
    tiktokErrorPage(
        'Konfigurasi Belum Lengkap',
        'TikTok Client Key belum diisi pada config.local.php.',
        500
    );
}

if ($clientSecret === '') {
    tiktokErrorPage(
        'Konfigurasi Belum Lengkap',
        'TikTok Client Secret belum diisi pada config.local.php.',
        500
    );
}

if ($redirectUri === '') {
    tiktokErrorPage(
        'Konfigurasi Belum Lengkap',
        'TikTok Redirect URI belum diisi pada config.local.php.',
        500
    );
}

$state = bin2hex(random_bytes(16));
$_SESSION['tiktok_oauth_state'] = $state;

$scopes = [
    'user.info.basic',
    'user.info.stats',
    'video.list',
];

$authUrl = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
    'client_key' => $clientKey,
    'scope' => implode(',', $scopes),
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;
