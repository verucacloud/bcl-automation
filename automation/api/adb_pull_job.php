<?php

declare(strict_types=1);

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../config.php';

/*
|--------------------------------------------------------------------------
| TOKEN WORKER
|--------------------------------------------------------------------------
| Token harus sama dengan:
| - C:\android-tools\adb_worker.php
| - adb_pull_job.php
| - adb_complete_job.php
*/

const WORKER_TOKEN = 'MBG_ADB_2026_X9kP7mQ4rT8vN2sL';

/*
|--------------------------------------------------------------------------
| RESPONSE JSON
|--------------------------------------------------------------------------
*/

function sendJsonResponse(array $data, int $statusCode = 200): void
{
    if (ob_get_length() !== false) {
        ob_clean();
    }

    http_response_code($statusCode);

    $json = json_encode(
        $data,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        echo '{"success":false,"message":"Gagal membuat respons JSON."}';
        exit;
    }

    echo $json;
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD
|--------------------------------------------------------------------------
*/

$method = strtoupper(
    isset($_SERVER['REQUEST_METHOD'])
        ? (string) $_SERVER['REQUEST_METHOD']
        : ''
);

if ($method !== 'GET') {
    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Metode tidak diizinkan.'
        ],
        405
    );
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log('[adb_pull_job] Koneksi database tidak tersedia.');

    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Koneksi database tidak tersedia.'
        ],
        500
    );
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log(
        '[adb_pull_job] Gagal mengatur charset: '
        . mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| TOKEN
|--------------------------------------------------------------------------
*/

$requestToken = isset($_SERVER['HTTP_X_WORKER_TOKEN'])
    ? trim((string) $_SERVER['HTTP_X_WORKER_TOKEN'])
    : '';

if ($requestToken === '') {
    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Token worker tidak ditemukan.'
        ],
        401
    );
}

if (!hash_equals(WORKER_TOKEN, $requestToken)) {
    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Token worker tidak valid.'
        ],
        401
    );
}

/*
|--------------------------------------------------------------------------
| SERIAL DEVICE
|--------------------------------------------------------------------------
*/

$serial = isset($_GET['serial'])
    ? trim((string) $_GET['serial'])
    : '';

if ($serial === '') {
    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Serial perangkat wajib diisi.'
        ],
        422
    );
}

if (
    strlen($serial) < 4 ||
    strlen($serial) > 100 ||
    !preg_match('/^[A-Za-z0-9._:-]+$/', $serial)
) {
    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Format serial perangkat tidak valid.'
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| AMBIL JOB
|--------------------------------------------------------------------------
*/

$selectStatement = null;
$updateStatement = null;
$transactionStarted = false;

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException(
            'Gagal memulai transaksi: '
            . mysqli_error($conn)
        );
    }

    $transactionStarted = true;

    /*
     * Gunakan bind_result agar kompatibel dengan hosting
     * yang tidak menyediakan mysqli_stmt_get_result().
     */
    $selectSql = "
        SELECT
            id,
            device_serial,
            action,
            payload,
            created_at
        FROM adb_jobs
        WHERE device_serial = ?
          AND status = 'waiting'
        ORDER BY id ASC
        LIMIT 1
        FOR UPDATE
    ";

    $selectStatement = mysqli_prepare(
        $conn,
        $selectSql
    );

    if ($selectStatement === false) {
        throw new RuntimeException(
            'Prepare SELECT gagal: '
            . mysqli_error($conn)
        );
    }

    if (
        !mysqli_stmt_bind_param(
            $selectStatement,
            's',
            $serial
        )
    ) {
        throw new RuntimeException(
            'Bind serial gagal: '
            . mysqli_stmt_error($selectStatement)
        );
    }

    if (!mysqli_stmt_execute($selectStatement)) {
        throw new RuntimeException(
            'Eksekusi SELECT gagal: '
            . mysqli_stmt_error($selectStatement)
        );
    }

    if (!mysqli_stmt_store_result($selectStatement)) {
        throw new RuntimeException(
            'Store result gagal: '
            . mysqli_stmt_error($selectStatement)
        );
    }

    if (mysqli_stmt_num_rows($selectStatement) === 0) {
        mysqli_commit($conn);
        $transactionStarted = false;

        mysqli_stmt_close($selectStatement);
        $selectStatement = null;

        sendJsonResponse([
            'success' => true,
            'message' => 'Tidak ada job untuk perangkat ini.',
            'device_serial' => $serial,
            'job' => null
        ]);
    }

    $jobId = 0;
    $jobSerial = '';
    $jobAction = '';
    $jobPayload = '';
    $jobCreatedAt = '';

    if (
        !mysqli_stmt_bind_result(
            $selectStatement,
            $jobId,
            $jobSerial,
            $jobAction,
            $jobPayload,
            $jobCreatedAt
        )
    ) {
        throw new RuntimeException(
            'Bind result gagal: '
            . mysqli_stmt_error($selectStatement)
        );
    }

    if (!mysqli_stmt_fetch($selectStatement)) {
        throw new RuntimeException(
            'Fetch job gagal: '
            . mysqli_stmt_error($selectStatement)
        );
    }

    $jobId = (int) $jobId;
    $jobSerial = trim((string) $jobSerial);
    $jobAction = trim((string) $jobAction);
    $jobPayload = (string) $jobPayload;
    $jobCreatedAt = (string) $jobCreatedAt;

    mysqli_stmt_free_result($selectStatement);
    mysqli_stmt_close($selectStatement);
    $selectStatement = null;

    if ($jobId < 1) {
        throw new RuntimeException(
            'Job ID dari database tidak valid.'
        );
    }

    if ($jobSerial !== $serial) {
        throw new RuntimeException(
            'Serial job tidak sesuai dengan serial worker.'
        );
    }

    if ($jobAction === '') {
        throw new RuntimeException(
            'Action job kosong.'
        );
    }

    /*
     * Kunci job menjadi processing.
     */
    $updateSql = "
        UPDATE adb_jobs
        SET
            status = 'processing',
            started_at = NOW()
        WHERE id = ?
          AND device_serial = ?
          AND status = 'waiting'
        LIMIT 1
    ";

    $updateStatement = mysqli_prepare(
        $conn,
        $updateSql
    );

    if ($updateStatement === false) {
        throw new RuntimeException(
            'Prepare UPDATE gagal: '
            . mysqli_error($conn)
        );
    }

    if (
        !mysqli_stmt_bind_param(
            $updateStatement,
            'is',
            $jobId,
            $serial
        )
    ) {
        throw new RuntimeException(
            'Bind UPDATE gagal: '
            . mysqli_stmt_error($updateStatement)
        );
    }

    if (!mysqli_stmt_execute($updateStatement)) {
        throw new RuntimeException(
            'Eksekusi UPDATE gagal: '
            . mysqli_stmt_error($updateStatement)
        );
    }

    $affectedRows = mysqli_stmt_affected_rows(
        $updateStatement
    );

    if ($affectedRows !== 1) {
        throw new RuntimeException(
            'Job gagal dikunci atau sudah diambil worker lain.'
        );
    }

    mysqli_stmt_close($updateStatement);
    $updateStatement = null;

    if (!mysqli_commit($conn)) {
        throw new RuntimeException(
            'Commit transaksi gagal: '
            . mysqli_error($conn)
        );
    }

    $transactionStarted = false;

    sendJsonResponse([
        'success' => true,
        'message' => 'Job berhasil diambil.',
        'device_serial' => $serial,
        'job' => [
            'id' => $jobId,
            'device_serial' => $jobSerial,
            'action' => $jobAction,
            'payload' => $jobPayload,
            'status' => 'processing',
            'created_at' => $jobCreatedAt
        ]
    ]);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }

    if ($selectStatement instanceof mysqli_stmt) {
        mysqli_stmt_close($selectStatement);
    }

    if ($updateStatement instanceof mysqli_stmt) {
        mysqli_stmt_close($updateStatement);
    }

    error_log(
        '[adb_pull_job] '
        . $exception->getMessage()
        . ' | serial='
        . $serial
    );

    sendJsonResponse(
        [
            'success' => false,
            'message' => 'Gagal mengambil job.',
            'detail' => $exception->getMessage(),
            'device_serial' => $serial
        ],
        500
    );
}