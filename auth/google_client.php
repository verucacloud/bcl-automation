<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Lokasi Composer autoload
|--------------------------------------------------------------------------
| auth/google_client.php berada satu tingkat di bawah public_html.
*/

$autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadFile)) {
    throw new RuntimeException(
        'File vendor/autoload.php tidak ditemukan. ' .
        'Google API Client belum terinstal melalui Composer. ' .
        'Lokasi yang dicari: ' .
        $autoloadFile
    );
}

require_once $autoloadFile;

/*
|--------------------------------------------------------------------------
| Konfigurasi Google OAuth
|--------------------------------------------------------------------------
| Isi dengan Client ID dan Client Secret dari Google Cloud Console.
*/

const GOOGLE_CLIENT_ID =
    'ISI_CLIENT_ID_ANDA.apps.googleusercontent.com';

const GOOGLE_CLIENT_SECRET =
    'ISI_CLIENT_SECRET_ANDA';

const GOOGLE_REDIRECT_URI =
    'https://ivory-gaur-875366.hostingersite.com/auth/google_callback.php';

/**
 * Membuat Google OAuth Client.
 */
function createGoogleClient(): Google\Client
{
    if (
        GOOGLE_CLIENT_ID === '' ||
        str_contains(GOOGLE_CLIENT_ID, 'ISI_CLIENT_ID')
    ) {
        throw new RuntimeException(
            'GOOGLE_CLIENT_ID belum diisi.'
        );
    }

    if (
        GOOGLE_CLIENT_SECRET === '' ||
        str_contains(GOOGLE_CLIENT_SECRET, 'ISI_CLIENT_SECRET')
    ) {
        throw new RuntimeException(
            'GOOGLE_CLIENT_SECRET belum diisi.'
        );
    }

    if (!class_exists(Google\Client::class)) {
        throw new RuntimeException(
            'Class Google\Client tidak ditemukan. ' .
            'Pastikan package google/apiclient sudah terinstal.'
        );
    }

    $client = new Google\Client();

    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);

    $client->setScopes([
        'openid',
        'email',
        'profile',
    ]);

    $client->setAccessType('online');
    $client->setIncludeGrantedScopes(true);
    $client->setPrompt('select_account');

    return $client;
}