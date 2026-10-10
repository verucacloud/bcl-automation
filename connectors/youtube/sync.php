<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sinkronisasi Akun YouTube
|--------------------------------------------------------------------------
| File:
| public_html/connectors/youtube/sync.php
|--------------------------------------------------------------------------
| Fungsi:
| - Membaca akun YouTube dari database
| - Memeriksa access token
| - Memperbarui token menggunakan refresh token jika kedaluwarsa
| - Mengambil statistik channel terbaru
| - Memperbarui subscriber, video, view, avatar, dan waktu sinkronisasi
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Hindari cache
|--------------------------------------------------------------------------
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/*
|--------------------------------------------------------------------------
| Helper redirect
|--------------------------------------------------------------------------
*/

function redirectToAccountDetail(int $accountId): never
{
    header(
        'Location: ../../accounts/detail.php?id=' . $accountId,
        true,
        302
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Helper membaca pesan error Google
|--------------------------------------------------------------------------
*/

function googleApiErrorMessage(
    array $responseBody,
    string $defaultMessage
): string {
    if (
        isset($responseBody['error']['message']) &&
        is_string($responseBody['error']['message'])
    ) {
        return trim($responseBody['error']['message']);
    }

    if (
        isset($responseBody['error_description']) &&
        is_string($responseBody['error_description'])
    ) {
        return trim($responseBody['error_description']);
    }

    if (
        isset($responseBody['error']) &&
        is_string($responseBody['error'])
    ) {
        return trim($responseBody['error']);
    }

    return $defaultMessage;
}

/*
|--------------------------------------------------------------------------
| Validasi ID akun
|--------------------------------------------------------------------------
*/

$accountId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $accountId === false ||
    $accountId === null ||
    $accountId <= 0
) {
    youtubeErrorPage(
        'ID akun tidak valid',
        'Akun yang akan disinkronkan tidak ditemukan.',
        400
    );
}

/*
|--------------------------------------------------------------------------
| Ambil konfigurasi Google OAuth
|--------------------------------------------------------------------------
*/

$clientId = youtubeConfigValue('google_client_id');
$clientSecret = youtubeConfigValue('google_client_secret');

if ($clientId === '' || $clientSecret === '') {
    youtubeErrorPage(
        'Konfigurasi OAuth belum lengkap',
        'Google Client ID atau Google Client Secret belum tersedia.',
        500
    );
}

/*
|--------------------------------------------------------------------------
| Ambil akun dari database
|--------------------------------------------------------------------------
*/

$statement = $conn->prepare(
    "
    SELECT
        id,
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
        status
    FROM social_accounts
    WHERE id = ?
      AND platform = 'youtube'
    LIMIT 1
    "
);

if (!$statement) {
    youtubeErrorPage(
        'Gagal membaca akun',
        'Prepare database gagal: ' . $conn->error,
        500
    );
}

$statement->bind_param(
    'i',
    $accountId
);

if (!$statement->execute()) {
    $error = $statement->error;

    $statement->close();

    youtubeErrorPage(
        'Gagal membaca akun',
        'Query database gagal: ' . $error,
        500
    );
}

$result = $statement->get_result();

$account = $result instanceof mysqli_result
    ? $result->fetch_assoc()
    : null;

$statement->close();

if (!is_array($account)) {
    youtubeErrorPage(
        'Akun tidak ditemukan',
        'Data akun YouTube tidak tersedia di database.',
        404
    );
}

/*
|--------------------------------------------------------------------------
| Siapkan data token
|--------------------------------------------------------------------------
*/

$channelId = trim(
    (string) (
        $account['platform_account_id'] ?? ''
    )
);

$accessToken = trim(
    (string) (
        $account['access_token'] ?? ''
    )
);

$refreshToken = trim(
    (string) (
        $account['refresh_token'] ?? ''
    )
);

$tokenExpiresAt = trim(
    (string) (
        $account['token_expires_at'] ?? ''
    )
);

$currentSubscriberCount = (int) (
    $account['subscriber_count'] ?? 0
);

$currentVideoCount = (int) (
    $account['video_count'] ?? 0
);

$currentViewCount = (int) (
    $account['view_count'] ?? 0
);

if ($channelId === '') {
    $_SESSION['account_error'] =
        'Channel ID YouTube tidak ditemukan.';

    redirectToAccountDetail($accountId);
}

/*
|--------------------------------------------------------------------------
| Tentukan apakah token harus diperbarui
|--------------------------------------------------------------------------
| Token dianggap hampir kedaluwarsa jika tersisa kurang dari 2 menit.
|--------------------------------------------------------------------------
*/

$expiresTimestamp = $tokenExpiresAt !== ''
    ? strtotime($tokenExpiresAt)
    : false;

$tokenNeedsRefresh =
    $accessToken === '' ||
    $expiresTimestamp === false ||
    $expiresTimestamp <= time() + 120;

try {
    /*
    |--------------------------------------------------------------------------
    | Refresh access token jika diperlukan
    |--------------------------------------------------------------------------
    */

    if ($tokenNeedsRefresh) {
        if ($refreshToken === '') {
            $expiredStatement = $conn->prepare(
                "
                UPDATE social_accounts
                SET
                    status = 'expired',
                    updated_at = NOW()
                WHERE id = ?
                "
            );

            if ($expiredStatement) {
                $expiredStatement->bind_param(
                    'i',
                    $accountId
                );

                $expiredStatement->execute();
                $expiredStatement->close();
            }

            throw new RuntimeException(
                'Refresh token tidak tersedia. ' .
                'Silakan hubungkan ulang akun YouTube.'
            );
        }

        $refreshResponse = youtubeHttpRequest(
            'https://oauth2.googleapis.com/token',
            'POST',
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            http_build_query(
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            )
        );

        if (
            $refreshResponse['status'] < 200 ||
            $refreshResponse['status'] >= 300
        ) {
            $message = googleApiErrorMessage(
                $refreshResponse['body'],
                'Gagal memperbarui access token Google.'
            );

            $expiredStatement = $conn->prepare(
                "
                UPDATE social_accounts
                SET
                    status = 'expired',
                    updated_at = NOW()
                WHERE id = ?
                "
            );

            if ($expiredStatement) {
                $expiredStatement->bind_param(
                    'i',
                    $accountId
                );

                $expiredStatement->execute();
                $expiredStatement->close();
            }

            throw new RuntimeException(
                'Refresh token gagal: ' . $message
            );
        }

        $newTokenData = $refreshResponse['body'];

        $newAccessToken = isset(
            $newTokenData['access_token']
        )
            ? trim(
                (string) $newTokenData['access_token']
            )
            : '';

        $newExpiresIn = isset(
            $newTokenData['expires_in']
        )
            ? (int) $newTokenData['expires_in']
            : 3600;

        /*
        | Google biasanya tidak mengirim refresh token baru
        | pada proses refresh. Jika ada, simpan nilai terbaru.
        */

        $newRefreshToken = isset(
            $newTokenData['refresh_token']
        )
            ? trim(
                (string) $newTokenData['refresh_token']
            )
            : $refreshToken;

        if ($newAccessToken === '') {
            throw new RuntimeException(
                'Google tidak mengirim access token baru.'
            );
        }

        $accessToken = $newAccessToken;
        $refreshToken = $newRefreshToken;

        $tokenExpiresAt = date(
            'Y-m-d H:i:s',
            time() + $newExpiresIn
        );

        $tokenStatement = $conn->prepare(
            "
            UPDATE social_accounts
            SET
                access_token = ?,
                refresh_token = ?,
                token_expires_at = ?,
                status = 'connected',
                updated_at = NOW()
            WHERE id = ?
            "
        );

        if (!$tokenStatement) {
            throw new RuntimeException(
                'Prepare update token gagal: ' .
                $conn->error
            );
        }

        $tokenStatement->bind_param(
            'sssi',
            $accessToken,
            $refreshToken,
            $tokenExpiresAt,
            $accountId
        );

        if (!$tokenStatement->execute()) {
            $error = $tokenStatement->error;

            $tokenStatement->close();

            throw new RuntimeException(
                'Update token gagal: ' . $error
            );
        }

        $tokenStatement->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Ambil data channel terbaru
    |--------------------------------------------------------------------------
    */

    $channelUrl =
        'https://www.googleapis.com/youtube/v3/channels?' .
        http_build_query(
            [
                'part' => 'snippet,statistics,contentDetails',
                'id' => $channelId,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    $channelResponse = youtubeHttpRequest(
        $channelUrl,
        'GET',
        [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        null
    );

    /*
    |--------------------------------------------------------------------------
    | Jika token ditolak Google
    |--------------------------------------------------------------------------
    */

    if (
        $channelResponse['status'] === 401 ||
        $channelResponse['status'] === 403
    ) {
        $message = googleApiErrorMessage(
            $channelResponse['body'],
            'Token Google ditolak atau izin YouTube tidak tersedia.'
        );

        throw new RuntimeException(
            'YouTube API menolak permintaan: ' . $message
        );
    }

    if (
        $channelResponse['status'] < 200 ||
        $channelResponse['status'] >= 300
    ) {
        $message = googleApiErrorMessage(
            $channelResponse['body'],
            'Gagal mengambil data channel YouTube.'
        );

        throw new RuntimeException($message);
    }

    $items = isset(
        $channelResponse['body']['items']
    ) && is_array(
        $channelResponse['body']['items']
    )
        ? $channelResponse['body']['items']
        : [];

    if (empty($items)) {
        throw new RuntimeException(
            'Channel YouTube tidak ditemukan atau tidak dapat diakses.'
        );
    }

    $channel = $items[0];

    $snippet = isset($channel['snippet']) &&
        is_array($channel['snippet'])
        ? $channel['snippet']
        : [];

    $statistics = isset($channel['statistics']) &&
        is_array($channel['statistics'])
        ? $channel['statistics']
        : [];

    /*
    |--------------------------------------------------------------------------
    | Baca data channel
    |--------------------------------------------------------------------------
    */

    $displayName = isset($snippet['title'])
        ? trim((string) $snippet['title'])
        : trim(
            (string) (
                $account['display_name'] ??
                'YouTube Channel'
            )
        );

    $username = isset($snippet['customUrl'])
        ? trim((string) $snippet['customUrl'])
        : trim(
            (string) (
                $account['username'] ?? ''
            )
        );

    $avatarUrl = trim(
        (string) (
            $account['avatar_url'] ?? ''
        )
    );

    if (
        isset($snippet['thumbnails']['high']['url'])
    ) {
        $avatarUrl = trim(
            (string) $snippet['thumbnails']['high']['url']
        );
    } elseif (
        isset($snippet['thumbnails']['medium']['url'])
    ) {
        $avatarUrl = trim(
            (string) $snippet['thumbnails']['medium']['url']
        );
    } elseif (
        isset($snippet['thumbnails']['default']['url'])
    ) {
        $avatarUrl = trim(
            (string) $snippet['thumbnails']['default']['url']
        );
    }

    /*
    | Jika subscriberCount tidak dikirim karena disembunyikan,
    | pertahankan nilai sebelumnya.
    */

    $subscriberCount = array_key_exists(
        'subscriberCount',
        $statistics
    )
        ? (int) $statistics['subscriberCount']
        : $currentSubscriberCount;

    $videoCount = array_key_exists(
        'videoCount',
        $statistics
    )
        ? (int) $statistics['videoCount']
        : $currentVideoCount;

    $viewCount = array_key_exists(
        'viewCount',
        $statistics
    )
        ? (int) $statistics['viewCount']
        : $currentViewCount;

    /*
    |--------------------------------------------------------------------------
    | Update data akun
    |--------------------------------------------------------------------------
    */

    $updateStatement = $conn->prepare(
        "
        UPDATE social_accounts
        SET
            username = ?,
            display_name = ?,
            avatar_url = ?,
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
            'Prepare update statistik gagal: ' .
            $conn->error
        );
    }

    $updateStatement->bind_param(
        'sssiiii',
        $username,
        $displayName,
        $avatarUrl,
        $subscriberCount,
        $videoCount,
        $viewCount,
        $accountId
    );

    if (!$updateStatement->execute()) {
        $error = $updateStatement->error;

        $updateStatement->close();

        throw new RuntimeException(
            'Update statistik gagal: ' . $error
        );
    }

    $updateStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Pesan sukses
    |--------------------------------------------------------------------------
    */

    $_SESSION['account_success'] =
        'Data channel “' .
        $displayName .
        '” berhasil disinkronkan.';

    redirectToAccountDetail($accountId);
} catch (Throwable $exception) {
    /*
    |--------------------------------------------------------------------------
    | Tandai akun error
    |--------------------------------------------------------------------------
    */

    $errorStatement = $conn->prepare(
        "
        UPDATE social_accounts
        SET
            status = CASE
                WHEN status = 'expired' THEN 'expired'
                ELSE 'error'
            END,
            updated_at = NOW()
        WHERE id = ?
        "
    );

    if ($errorStatement) {
        $errorStatement->bind_param(
            'i',
            $accountId
        );

        $errorStatement->execute();
        $errorStatement->close();
    }

    error_log(
        'YouTube sync error, account ID ' .
        $accountId .
        ': ' .
        $exception->getMessage()
    );

    $_SESSION['account_error'] =
        'Sinkronisasi gagal: ' .
        $exception->getMessage();

    redirectToAccountDetail($accountId);
}