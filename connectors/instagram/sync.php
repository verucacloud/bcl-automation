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

$stmt = $conn->prepare("SELECT id, platform_account_id, access_token FROM social_accounts WHERE id = ? AND platform = 'instagram' LIMIT 1");
$stmt->bind_param('i', $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account || empty($account['access_token'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Akun Instagram tidak ditemukan']);
    exit;
}

$accessToken = $account['access_token'];

try {
    $profileUrl = 'https://graph.instagram.com/v19.0/me?' . http_build_query([
        'fields' => 'id,username,account_type,media_count,profile_picture_url',
        'access_token' => $accessToken,
    ]);
    $response = instagramHttpRequest($profileUrl, 'GET');
    $data = $response['json'] ?? [];

    if (!empty($data['username'])) {
        $username = $data['username'];
        $avatar = $data['profile_picture_url'] ?? null;

        $update = $conn->prepare("UPDATE social_accounts SET username = ?, display_name = ?, avatar_url = COALESCE(?, avatar_url), last_sync_at = NOW() WHERE id = ?");
        $displayName = '@' . $username;
        $update->bind_param('sssi', $username, $displayName, $avatar, $accountId);
        $update->execute();
        $update->close();

        echo json_encode(['success' => true, 'message' => 'Sinkronisasi berhasil', 'data' => $data]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Gagal membaca data dari Instagram']);
