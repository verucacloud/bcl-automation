<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$clientId = instagramConfigValue('instagram_client_id');
$clientSecret = instagramConfigValue('instagram_client_secret');
$redirectUri = instagramConfigValue('instagram_redirect_uri');

if ($clientId === '') {
    instagramErrorPage(
        'Konfigurasi Belum Lengkap',
        'Instagram Client ID belum diisi pada config.local.php.',
        500
    );
}

if ($clientSecret === '') {
    instagramErrorPage(
        'Konfigurasi Belum Lengkap',
        'Instagram Client Secret belum diisi pada config.local.php.',
        500
    );
}

if ($redirectUri === '') {
    instagramErrorPage(
        'Konfigurasi Belum Lengkap',
        'Instagram Redirect URI belum diisi pada config.local.php.',
        500
    );
}

// Generate secure random state token
$state = bin2hex(random_bytes(16));
$_SESSION['instagram_oauth_state'] = $state;

$scopes = [
    'instagram_business_basic',
    'instagram_business_manage_messages',
    'instagram_business_manage_comments',
];

$authUrl = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
    'enable_fb_login' => '0',
    'force_authentication' => '1',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => implode(',', $scopes),
    'state' => $state,
]);

header('Location: ' . $authUrl);
exit;
