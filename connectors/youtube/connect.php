<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| YouTube OAuth Login
|--------------------------------------------------------------------------
| File:
| public_html/connectors/youtube/connect.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Hindari cache pada halaman OAuth
|--------------------------------------------------------------------------
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/*
|--------------------------------------------------------------------------
| Ambil konfigurasi Google OAuth
|--------------------------------------------------------------------------
*/

$clientId = youtubeConfigValue('google_client_id');
$clientSecret = youtubeConfigValue('google_client_secret');
$redirectUri = youtubeConfigValue('google_redirect_uri');

/*
|--------------------------------------------------------------------------
| Validasi konfigurasi
|--------------------------------------------------------------------------
*/

if ($clientId === '') {
    youtubeErrorPage(
        'Konfigurasi belum lengkap',
        'Google Client ID belum diisi pada config.local.php.',
        500
    );
}

if ($clientSecret === '') {
    youtubeErrorPage(
        'Konfigurasi belum lengkap',
        'Google Client Secret belum diisi pada config.local.php.',
        500
    );
}

if ($redirectUri === '') {
    youtubeErrorPage(
        'Konfigurasi belum lengkap',
        'Google Redirect URI belum diisi pada config.local.php.',
        500
    );
}

if (
    filter_var(
        $redirectUri,
        FILTER_VALIDATE_URL
    ) === false
) {
    youtubeErrorPage(
        'Redirect URI tidak valid',
        'Google Redirect URI pada config.local.php bukan URL yang valid.',
        500
    );
}

/*
|--------------------------------------------------------------------------
| Buat OAuth state
|--------------------------------------------------------------------------
| State digunakan untuk mencegah serangan CSRF.
|--------------------------------------------------------------------------
*/

try {
    $state = youtubeCreateState();
} catch (Throwable $exception) {
    youtubeErrorPage(
        'Gagal membuat sesi OAuth',
        $exception->getMessage(),
        500
    );
}

/*
|--------------------------------------------------------------------------
| Scope Google OAuth
|--------------------------------------------------------------------------
| youtube.readonly:
| Membaca informasi channel, video, playlist, dan statistik YouTube.
|
| openid, userinfo.email, userinfo.profile:
| Membaca identitas dasar akun Google.
|--------------------------------------------------------------------------
*/

$scopes = [
    'openid',
    'https://www.googleapis.com/auth/userinfo.email',
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/youtube.readonly',
];

$scope = implode(' ', $scopes);

/*
|--------------------------------------------------------------------------
| Parameter Authorization Request
|--------------------------------------------------------------------------
*/

$authorizationParameters = [
    'client_id' => $clientId,

    'redirect_uri' => $redirectUri,

    'response_type' => 'code',

    'scope' => $scope,

    /*
    | Meminta refresh token agar akun dapat disinkronkan
    | kembali tanpa login setiap saat.
    */

    'access_type' => 'offline',

    /*
    | Memaksa Google menampilkan halaman persetujuan ulang.
    | select_account memudahkan memilih akun lain.
    */

    'prompt' => 'consent select_account',

    /*
    | Gabungkan izin yang sebelumnya sudah diberikan.
    */

    'include_granted_scopes' => 'true',

    /*
    | Melindungi proses OAuth dari CSRF.
    */

    'state' => $state,
];

/*
|--------------------------------------------------------------------------
| Bangun URL login Google
|--------------------------------------------------------------------------
*/

$query = http_build_query(
    $authorizationParameters,
    '',
    '&',
    PHP_QUERY_RFC3986
);

$authorizationUrl =
    'https://accounts.google.com/o/oauth2/v2/auth?' .
    $query;

/*
|--------------------------------------------------------------------------
| Arahkan pengguna ke Google
|--------------------------------------------------------------------------
*/

header(
    'Location: ' . $authorizationUrl,
    true,
    302
);

exit;