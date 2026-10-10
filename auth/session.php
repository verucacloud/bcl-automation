<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isHttps =
        (!empty($_SERVER['HTTPS']) &&
            strtolower((string) $_SERVER['HTTPS']) !== 'off')
        ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    if (!session_start()) {
        http_response_code(500);
        exit('Session gagal dijalankan.');
    }
}