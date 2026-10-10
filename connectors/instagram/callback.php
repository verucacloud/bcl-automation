<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Tangani verifikasi Webhook jika Meta memanggil endpoint ini
$hubChallenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? null;
if ($hubChallenge !== null) {
    header('Content-Type: text/plain');
    http_response_code(200);
    echo $hubChallenge;
    exit;
}

if (isset($_GET['error'])) {
    $errorReason = (string)($_GET['error_description'] ?? $_GET['error_reason'] ?? $_GET['error'] ?? 'Terjadi kesalahan saat otorisasi.');
    instagramErrorPage('Otorisasi Ditolak', $errorReason, 400);
}

$state = (string)($_GET['state'] ?? '');
$savedState = (string)($_SESSION['instagram_oauth_state'] ?? '');
unset($_SESSION['instagram_oauth_state']);

if ($state === '' || $savedState === '' || !hash_equals($savedState, $state)) {
    instagramErrorPage('State Tidak Valid', 'Permintaan otorisasi kedaluwarsa atau tidak valid. Silakan coba lagi.', 400);
}

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    instagramErrorPage('Kode Tidak Ditemukan', 'Kode otorisasi dari Instagram tidak diterima.', 400);
}

// Hapus trailing #_ jika ditambahkan oleh Instagram
$code = preg_replace('/#_$/', '', $code);

$clientId = instagramConfigValue('instagram_client_id');
$clientSecret = instagramConfigValue('instagram_client_secret');
$redirectUri = instagramConfigValue('instagram_redirect_uri');

// 1. Tukar authorization code dengan short-lived token
try {
    $tokenResponse = instagramHttpRequest(
        'https://api.instagram.com/oauth/access_token',
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]
    );
} catch (Exception $e) {
    instagramErrorPage('Gagal Mengambil Token', $e->getMessage(), 500);
}

$tokenData = $tokenResponse['json'] ?? [];
$shortLivedToken = (string)($tokenData['access_token'] ?? '');
$instagramUserId = (string)($tokenData['user_id'] ?? '');

if ($shortLivedToken === '' || $instagramUserId === '') {
    $err = $tokenData['error_message'] ?? 'Token atau User ID Instagram tidak ditemukan.';
    instagramErrorPage('Gagal Otorisasi', (string)$err, 400);
}

// 2. Tukar dengan Long-Lived Access Token (berlaku 60 hari)
$accessToken = $shortLivedToken;
$expiresIn = 5184000; // default 60 hari

try {
    $longLivedUrl = 'https://graph.instagram.com/access_token?' . http_build_query([
        'grant_type' => 'ig_exchange_token',
        'client_secret' => $clientSecret,
        'access_token' => $shortLivedToken,
    ]);
    $longLivedResponse = instagramHttpRequest($longLivedUrl, 'GET');
    if (!empty($longLivedResponse['json']['access_token'])) {
        $accessToken = (string)$longLivedResponse['json']['access_token'];
        $expiresIn = (int)($longLivedResponse['json']['expires_in'] ?? 5184000);
    }
} catch (Exception $e) {
    // Gunakan short-lived token jika long-lived gagal
    error_log('Gagal menukar long-lived token IG: ' . $e->getMessage());
}

// 3. Ambil profil pengguna Instagram
$username = 'instagram_user_' . $instagramUserId;
$displayName = $username;
$avatarUrl = null;

try {
    $profileUrl = 'https://graph.instagram.com/v19.0/me?' . http_build_query([
        'fields' => 'id,username,account_type,profile_picture_url',
        'access_token' => $accessToken,
    ]);
    $profileResponse = instagramHttpRequest($profileUrl, 'GET');
    $profileData = $profileResponse['json'] ?? [];
    if (!empty($profileData['username'])) {
        $username = (string)$profileData['username'];
        $displayName = '@' . $username;
    }
    if (!empty($profileData['profile_picture_url'])) {
        $avatarUrl = (string)$profileData['profile_picture_url'];
    }
} catch (Exception $e) {
    error_log('Gagal mengambil profil IG: ' . $e->getMessage());
}

// 4. Simpan ke database social_accounts
$tokenExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
$scopes = 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$stmt = $conn->prepare("
    INSERT INTO social_accounts (
        user_id, platform, platform_account_id, username, display_name,
        avatar_url, access_token, token_expires_at, scopes, status, last_sync_at, created_at, updated_at
    ) VALUES (
        ?, 'instagram', ?, ?, ?,
        ?, ?, ?, ?, 'connected', NOW(), NOW(), NOW()
    )
    ON DUPLICATE KEY UPDATE
        username = VALUES(username),
        display_name = VALUES(display_name),
        avatar_url = VALUES(avatar_url),
        access_token = VALUES(access_token),
        token_expires_at = VALUES(token_expires_at),
        scopes = VALUES(scopes),
        status = 'connected',
        last_sync_at = NOW(),
        updated_at = NOW()
");

if ($stmt) {
    $stmt->bind_param(
        'isssssss',
        $userId,
        $instagramUserId,
        $username,
        $displayName,
        $avatarUrl,
        $accessToken,
        $tokenExpiresAt,
        $scopes
    );
    $stmt->execute();
    $stmt->close();
} else {
    instagramErrorPage('Database Error', 'Gagal menyimpan akun Instagram: ' . $conn->error, 500);
}

// Redirect ke daftar akun dengan notifikasi sukses
header('Location: ../../accounts/index.php?status=connected&platform=instagram');
exit;
