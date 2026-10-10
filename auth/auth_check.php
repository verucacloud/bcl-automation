<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$maxIdleTime = 60 * 60 * 2; // 2 jam

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$lastActivity = (int) ($_SESSION['last_activity'] ?? time());

if ((time() - $lastActivity) > $maxIdleTime) {
    session_unset();
    session_destroy();

    header('Location: /login.php?expired=1');
    exit;
}

$_SESSION['last_activity'] = time();