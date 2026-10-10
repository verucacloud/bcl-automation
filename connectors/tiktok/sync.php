<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$accountId = (int)($_GET['id'] ?? 0);
if ($accountId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID akun tidak valid']);
    exit;
}

$stmt = $conn->prepare("SELECT id, platform_account_id, access_token FROM social_accounts WHERE id = ? AND platform = 'tiktok' LIMIT 1");
$stmt->bind_param('i', $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account || empty($account['access_token'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Akun TikTok tidak ditemukan']);
    exit;
}

$accessToken = $account['access_token'];

try {
    $userInfoUrl = 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,display_name';
    $userInfoResponse = tiktokHttpRequest($userInfoUrl, 'GET', [
        'Authorization: Bearer ' . $accessToken,
    ]);
    $userData = $userInfoResponse['json']['data']['user'] ?? [];

    if (!empty($userData['display_name'])) {
        $displayName = (string)$userData['display_name'];
        $avatar = $userData['avatar_url'] ?? null;

        $update = $conn->prepare("UPDATE social_accounts SET display_name = ?, avatar_url = COALESCE(?, avatar_url), last_sync_at = NOW() WHERE id = ?");
        $update->bind_param('ssi', $displayName, $avatar, $accountId);
        $update->execute();
        $update->close();

        echo json_encode(['success' => true, 'message' => 'Sinkronisasi berhasil', 'data' => $userData]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Gagal membaca data dari TikTok']);
