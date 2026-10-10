<?php

require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Periksa error dari Google
|--------------------------------------------------------------------------
*/

if (isset($_GET['error'])) {
    $error = trim(
        (string) $_GET['error']
    );

    $description = isset(
        $_GET['error_description']
    )
        ? trim(
            (string) $_GET['error_description']
        )
        : 'Pengguna membatalkan atau menolak izin.';

    youtubeErrorPage(
        'Otorisasi dibatalkan',
        $error . ': ' . $description,
        400
    );
}

/*
|--------------------------------------------------------------------------
| Validasi state untuk mencegah CSRF
|--------------------------------------------------------------------------
*/

$receivedState = isset($_GET['state'])
    ? trim((string) $_GET['state'])
    : '';

if (!youtubeValidateState($receivedState)) {
    youtubeErrorPage(
        'State OAuth tidak valid',
        'Sesi OAuth sudah kedaluwarsa atau permintaan tidak sesuai. Silakan hubungkan ulang akun.',
        400
    );
}

$authorizationCode = isset($_GET['code'])
    ? trim((string) $_GET['code'])
    : '';

if ($authorizationCode === '') {
    youtubeErrorPage(
        'Kode OAuth tidak tersedia',
        'Google tidak mengirim authorization code.',
        400
    );
}

/*
|--------------------------------------------------------------------------
| Ambil konfigurasi
|--------------------------------------------------------------------------
*/

$clientId = youtubeConfigValue(
    'google_client_id'
);

$clientSecret = youtubeConfigValue(
    'google_client_secret'
);

$redirectUri = youtubeConfigValue(
    'google_redirect_uri'
);

if (
    $clientId === '' ||
    $clientSecret === '' ||
    $redirectUri === ''
) {
    youtubeErrorPage(
        'Konfigurasi OAuth belum lengkap',
        'Client ID, Client Secret, atau Redirect URI belum tersedia.',
        500
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Tukarkan authorization code menjadi token
    |--------------------------------------------------------------------------
    */

    $tokenRequest = youtubeHttpRequest(
        'https://oauth2.googleapis.com/token',
        'POST',
        [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        http_build_query(
            [
                'code' => $authorizationCode,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        )
    );

    if (
        $tokenRequest['status'] < 200 ||
        $tokenRequest['status'] >= 300
    ) {
        $errorMessage = isset(
            $tokenRequest['body']['error_description']
        )
            ? $tokenRequest['body']['error_description']
            : 'Pertukaran token gagal.';

        throw new RuntimeException(
            $errorMessage
        );
    }

    $tokenData = $tokenRequest['body'];

    $accessToken = isset(
        $tokenData['access_token']
    )
        ? trim(
            (string) $tokenData['access_token']
        )
        : '';

    $refreshToken = isset(
        $tokenData['refresh_token']
    )
        ? trim(
            (string) $tokenData['refresh_token']
        )
        : '';

    $expiresIn = isset(
        $tokenData['expires_in']
    )
        ? (int) $tokenData['expires_in']
        : 3600;

    $scope = isset($tokenData['scope'])
        ? trim((string) $tokenData['scope'])
        : '';

    if ($accessToken === '') {
        throw new RuntimeException(
            'Access token tidak diterima dari Google.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ambil channel milik akun yang login
    |--------------------------------------------------------------------------
    */

    $channelRequest = youtubeHttpRequest(
        'https://www.googleapis.com/youtube/v3/channels?' .
        http_build_query(
            [
                'part' =>
                    'snippet,statistics,contentDetails',
                'mine' => 'true'
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        ),
        'GET',
        [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ],
        null
    );

    if (
        $channelRequest['status'] < 200 ||
        $channelRequest['status'] >= 300
    ) {
        $apiMessage = isset(
            $channelRequest['body']['error']['message']
        )
            ? $channelRequest['body']['error']['message']
            : 'Gagal membaca channel YouTube.';

        throw new RuntimeException(
            $apiMessage
        );
    }

    $items = isset(
        $channelRequest['body']['items']
    )
        ? $channelRequest['body']['items']
        : [];

    if (empty($items)) {
        throw new RuntimeException(
            'Akun Google ini belum memiliki channel YouTube.'
        );
    }

    $channel = $items[0];

    $channelId = isset($channel['id'])
        ? trim((string) $channel['id'])
        : '';

    $snippet = isset($channel['snippet'])
        ? $channel['snippet']
        : [];

    $statistics = isset($channel['statistics'])
        ? $channel['statistics']
        : [];

    $displayName = isset($snippet['title'])
        ? trim((string) $snippet['title'])
        : 'YouTube Channel';

    $username = isset($snippet['customUrl'])
        ? trim((string) $snippet['customUrl'])
        : null;

    $avatarUrl = null;

    if (
        isset(
            $snippet['thumbnails']['high']['url']
        )
    ) {
        $avatarUrl =
            $snippet['thumbnails']['high']['url'];
    } elseif (
        isset(
            $snippet['thumbnails']['default']['url']
        )
    ) {
        $avatarUrl =
            $snippet['thumbnails']['default']['url'];
    }

    $subscriberCount = isset(
        $statistics['subscriberCount']
    )
        ? (int) $statistics['subscriberCount']
        : 0;

    $videoCount = isset(
        $statistics['videoCount']
    )
        ? (int) $statistics['videoCount']
        : 0;

    $viewCount = isset(
        $statistics['viewCount']
    )
        ? (int) $statistics['viewCount']
        : 0;

    if ($channelId === '') {
        throw new RuntimeException(
            'Channel ID tidak ditemukan.'
        );
    }

    $tokenExpiresAt = date(
        'Y-m-d H:i:s',
        time() + $expiresIn
    );

    /*
    |--------------------------------------------------------------------------
    | Periksa channel lama
    |--------------------------------------------------------------------------
    */

    $checkStatement = $conn->prepare(
        "
        SELECT
            id,
            refresh_token
        FROM social_accounts
        WHERE platform = 'youtube'
          AND platform_account_id = ?
        LIMIT 1
        "
    );

    if (!$checkStatement) {
        throw new RuntimeException(
            'Prepare check account gagal: ' .
            $conn->error
        );
    }

    $checkStatement->bind_param(
        's',
        $channelId
    );

    $checkStatement->execute();

    $existingResult =
        $checkStatement->get_result();

    $existingAccount =
        $existingResult->fetch_assoc();

    $checkStatement->close();

    /*
    | Google tidak selalu mengirim refresh token
    | ketika izin sebelumnya masih tersimpan.
    | Pertahankan refresh token lama jika yang baru kosong.
    */

    if (
        $refreshToken === '' &&
        is_array($existingAccount) &&
        !empty($existingAccount['refresh_token'])
    ) {
        $refreshToken =
            $existingAccount['refresh_token'];
    }

    if (is_array($existingAccount)) {
        /*
        |--------------------------------------------------------------------------
        | Update akun
        |--------------------------------------------------------------------------
        */

        $updateStatement = $conn->prepare(
            "
            UPDATE social_accounts
            SET
                username = ?,
                display_name = ?,
                avatar_url = ?,
                access_token = ?,
                refresh_token = ?,
                token_expires_at = ?,
                scopes = ?,
                subscriber_count = ?,
                video_count = ?,
                view_count = ?,
                status = 'connected',
                last_sync_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
            "
        );

        if (!$updateStatement) {
            throw new RuntimeException(
                'Prepare update account gagal: ' .
                $conn->error
            );
        }

        $accountId = (int) $existingAccount['id'];

        $updateStatement->bind_param(
            'sssssssiiii',
            $username,
            $displayName,
            $avatarUrl,
            $accessToken,
            $refreshToken,
            $tokenExpiresAt,
            $scope,
            $subscriberCount,
            $videoCount,
            $viewCount,
            $accountId
        );

        if (!$updateStatement->execute()) {
            throw new RuntimeException(
                'Update akun gagal: ' .
                $updateStatement->error
            );
        }

        $updateStatement->close();
    } else {
        /*
        |--------------------------------------------------------------------------
        | Simpan akun baru
        |--------------------------------------------------------------------------
        */

        $insertStatement = $conn->prepare(
            "
            INSERT INTO social_accounts (
                user_id,
                platform,
                platform_account_id,
                username,
                display_name,
                avatar_url,
                access_token,
                refresh_token,
                token_expires_at,
                scopes,
                subscriber_count,
                video_count,
                view_count,
                status,
                last_sync_at,
                created_at,
                updated_at
            ) VALUES (
                NULL,
                'youtube',
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'connected',
                NOW(),
                NOW(),
                NOW()
            )
            "
        );

        if (!$insertStatement) {
            throw new RuntimeException(
                'Prepare insert account gagal: ' .
                $conn->error
            );
        }

        $insertStatement->bind_param(
            'ssssssssiii',
            $channelId,
            $username,
            $displayName,
            $avatarUrl,
            $accessToken,
            $refreshToken,
            $tokenExpiresAt,
            $scope,
            $subscriberCount,
            $videoCount,
            $viewCount
        );

        if (!$insertStatement->execute()) {
            throw new RuntimeException(
                'Simpan akun gagal: ' .
                $insertStatement->error
            );
        }

        $insertStatement->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan notifikasi sukses ke session
    |--------------------------------------------------------------------------
    */

    $_SESSION['account_success'] =
        'Channel YouTube “' .
        $displayName .
        '” berhasil dihubungkan.';

    header(
        'Location: ../../accounts/index.php',
        true,
        302
    );

    exit;
} catch (Throwable $exception) {
    youtubeErrorPage(
        'Gagal menghubungkan YouTube',
        $exception->getMessage(),
        500
    );
}