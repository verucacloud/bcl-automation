<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isset($_GET['error'])) {
    $errorReason = (string)($_GET['error_description'] ?? $_GET['error'] ?? 'Terjadi kesalahan saat otorisasi.');
    tiktokErrorPage('Otorisasi Ditolak', $errorReason, 400);
}

$state = (string)($_GET['state'] ?? '');
$savedState = (string)($_SESSION['tiktok_oauth_state'] ?? '');
unset($_SESSION['tiktok_oauth_state']);

if ($state === '' || $savedState === '' || !hash_equals($savedState, $state)) {
    tiktokErrorPage('State Tidak Valid', 'Permintaan otorisasi kedaluwarsa atau tidak valid. Silakan coba lagi.', 400);
}

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    tiktokErrorPage('Kode Tidak Ditemukan', 'Kode otorisasi dari TikTok tidak diterima.', 400);
}

$clientKey = tiktokConfigValue('tiktok_client_key');
$clientSecret = tiktokConfigValue('tiktok_client_secret');
$redirectUri = tiktokConfigValue('tiktok_redirect_uri');

// 1. Tukar authorization code dengan access token
try {
    $tokenResponse = tiktokHttpRequest(
        'https://open.tiktokapis.com/v2/oauth/token/',
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]
    );
} catch (Exception $e) {
    tiktokErrorPage('Gagal Mengambil Token', $e->getMessage(), 500);
}

$tokenData = $tokenResponse['json']['data'] ?? $tokenResponse['json'] ?? [];
$accessToken = (string)($tokenData['access_token'] ?? '');
$refreshToken = (string)($tokenData['refresh_token'] ?? '');
$openId = (string)($tokenData['open_id'] ?? '');
$expiresIn = (int)($tokenData['expires_in'] ?? 86400);

if ($accessToken === '' || $openId === '') {
    $errorMsg = $tokenResponse['json']['error_description'] ?? $tokenResponse['json']['message'] ?? 'Token atau Open ID TikTok tidak ditemukan.';
    tiktokErrorPage('Gagal Otorisasi TikTok', (string)$errorMsg, 400);
}

// 2. Ambil informasi profil pengguna
$username = 'tiktok_' . substr($openId, 0, 8);
$displayName = $username;
$avatarUrl = null;

try {
    $userInfoUrl = 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,display_name';
    $userInfoResponse = tiktokHttpRequest($userInfoUrl, 'GET', [
        'Authorization: Bearer ' . $accessToken,
    ]);
    $userData = $userInfoResponse['json']['data']['user'] ?? [];
    if (!empty($userData['display_name'])) {
        $displayName = (string)$userData['display_name'];
        $username = strtolower(preg_replace('/[^a-zA-Z0-9._]/', '', $displayName));
    }
    if (!empty($userData['avatar_url'])) {
        $avatarUrl = (string)$userData['avatar_url'];
    }
} catch (Exception $e) {
    error_log('Gagal mengambil profil TikTok: ' . $e->getMessage());
}

// 3. Simpan ke database social_accounts
$tokenExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
$scopes = 'user.info.basic,user.info.stats,video.list';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$stmt = $conn->prepare("
    INSERT INTO social_accounts (
        user_id, platform, platform_account_id, username, display_name,
        avatar_url, access_token, refresh_token, token_expires_at, scopes, status, last_sync_at, created_at, updated_at
    ) VALUES (
        ?, 'tiktok', ?, ?, ?,
        ?, ?, ?, ?, ?, 'connected', NOW(), NOW(), NOW()
    )
    ON DUPLICATE KEY UPDATE
        username = VALUES(username),
        display_name = VALUES(display_name),
        avatar_url = VALUES(avatar_url),
        access_token = VALUES(access_token),
        refresh_token = VALUES(refresh_token),
        token_expires_at = VALUES(token_expires_at),
        scopes = VALUES(scopes),
        status = 'connected',
        last_sync_at = NOW(),
        updated_at = NOW()
");

if ($stmt) {
    $stmt->bind_param(
        'issssssss',
        $userId,
        $openId,
        $username,
        $displayName,
        $avatarUrl,
        $accessToken,
        $refreshToken,
        $tokenExpiresAt,
        $scopes
    );
    $stmt->execute();
    $stmt->close();
} else {
    tiktokErrorPage('Database Error', 'Gagal menyimpan akun TikTok: ' . $conn->error, 500);
}

header('Location: ../../accounts/index.php?status=connected&platform=tiktok');
exit;
