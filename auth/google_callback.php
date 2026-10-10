<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/session.php';

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/google_client.php';

function failOAuth(string $message): never
{
    http_response_code(400);
    exit(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

if (isset($_GET['error'])) {
    failOAuth('Login Google dibatalkan atau ditolak.');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '') {
    failOAuth('Authorization code tidak ditemukan.');
}

if (
    $state === '' ||
    !isset($_SESSION['oauth_state']) ||
    !hash_equals($_SESSION['oauth_state'], $state)
) {
    failOAuth('OAuth state tidak valid.');
}

unset($_SESSION['oauth_state']);

$client = createGoogleClient();

$token = $client->fetchAccessTokenWithAuthCode($code);

if (isset($token['error'])) {
    error_log('Google OAuth error: ' . json_encode($token));
    failOAuth('Gagal menyelesaikan proses login Google.');
}

$client->setAccessToken($token);

$oauthService = new Google\Service\Oauth2($client);
$googleUser = $oauthService->userinfo->get();

$googleId = trim((string) $googleUser->id);
$email = strtolower(trim((string) $googleUser->email));
$name = trim((string) $googleUser->name);
$avatarUrl = trim((string) $googleUser->picture);

if ($googleId === '' || $email === '') {
    failOAuth('Informasi akun Google tidak lengkap.');
}

/*
|--------------------------------------------------------------------------
| Cari pengguna berdasarkan google_id atau email
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT id, role, status
     FROM users
     WHERE google_id = ? OR email = ?
     LIMIT 1'
);

$stmt->bind_param('ss', $googleId, $email);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    if ($user['status'] !== 'active') {
        failOAuth('Akun Anda sedang dinonaktifkan.');
    }

    $userId = (int) $user['id'];

    $update = $conn->prepare(
        'UPDATE users
         SET google_id = ?,
             name = ?,
             avatar_url = ?,
             last_login_at = NOW()
         WHERE id = ?'
    );

    $update->bind_param(
        'sssi',
        $googleId,
        $name,
        $avatarUrl,
        $userId
    );

    $update->execute();

    $role = (string) $user['role'];
} else {
    $role = 'user';

    $insert = $conn->prepare(
        'INSERT INTO users
            (google_id, email, name, avatar_url, role, last_login_at)
         VALUES
            (?, ?, ?, ?, ?, NOW())'
    );

    $insert->bind_param(
        'sssss',
        $googleId,
        $email,
        $name,
        $avatarUrl,
        $role
    );

    $insert->execute();

    $userId = (int) $conn->insert_id;
}

/*
|--------------------------------------------------------------------------
| Buat session login aplikasi
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION['user_id'] = $userId;
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $name;
$_SESSION['user_avatar'] = $avatarUrl;
$_SESSION['user_role'] = $role;
$_SESSION['logged_in_at'] = time();

header('Location: ../dashboard.php');
exit;