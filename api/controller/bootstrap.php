<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireControllerKey(): void {
    $provided = $_SERVER['HTTP_X_CONTROLLER_KEY'] ?? '';
    if (CONTROLLER_API_KEY === '' || !hash_equals(CONTROLLER_API_KEY, (string)$provided)) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

function inputJson(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
    return $data;
}
