<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Verifikasi Webhook Meta (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubChallenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
    if ($hubChallenge !== '') {
        header('Content-Type: text/plain');
        http_response_code(200);
        echo $hubChallenge;
        exit;
    }
}

// Menerima Event Webhook Meta (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if ($input) {
        $logFile = __DIR__ . '/webhook_events.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $input . PHP_EOL, FILE_APPEND);
    }
    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

http_response_code(400);
echo 'Invalid request';
