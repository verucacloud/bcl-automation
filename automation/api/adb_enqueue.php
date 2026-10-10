<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| ADB ENQUEUE API
|--------------------------------------------------------------------------
|
| File:
| public_html/automation/api/adb_enqueue.php
|
| Fungsi:
| - Menerima URL dari halaman Automation Center.
| - Menerima satu atau beberapa serial perangkat.
| - Membuat satu job "open_url" untuk setiap perangkat.
| - Worker kemudian membuka URL menggunakan Android ACTION_VIEW.
|
| Endpoint ini TIDAK membuka YouTube secara langsung.
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config.php';

/*
|--------------------------------------------------------------------------
| ERROR HANDLER
|--------------------------------------------------------------------------
*/

set_exception_handler(
    static function (Throwable $exception): never {
        error_log(
            '[adb_enqueue] '
            . $exception->getMessage()
            . ' in '
            . $exception->getFile()
            . ':'
            . $exception->getLine()
        );

        sendJson(
            500,
            false,
            'Terjadi kesalahan pada server. Periksa error log hosting.'
        );
    }
);

/*
|--------------------------------------------------------------------------
| VALIDASI DASAR
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    sendJson(
        500,
        false,
        'Koneksi database tidak tersedia. Periksa config.php.'
    );
}

mysqli_set_charset($conn, 'utf8mb4');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendJson(
        405,
        false,
        'Metode tidak diizinkan. Gunakan POST.'
    );
}

/*
|--------------------------------------------------------------------------
| VALIDASI URL
|--------------------------------------------------------------------------
*/

$url = trim((string) ($_POST['content_url'] ?? ''));

if ($url === '') {
    sendJson(
        422,
        false,
        'URL wajib diisi.'
    );
}

if (
    strlen($url) > 2048
    || !filter_var($url, FILTER_VALIDATE_URL)
    || !preg_match('#^https?://#i', $url)
) {
    sendJson(
        422,
        false,
        'URL tidak valid.'
    );
}

$host = strtolower(
    (string) parse_url($url, PHP_URL_HOST)
);

$host = preg_replace('/^www\./i', '', $host) ?? $host;

$allowedHosts = [
    'youtube.com',
    'm.youtube.com',
    'music.youtube.com',
    'youtu.be'
];

if (!in_array($host, $allowedHosts, true)) {
    sendJson(
        422,
        false,
        'Saat ini hanya URL YouTube yang diperbolehkan.'
    );
}

/*
|--------------------------------------------------------------------------
| AMBIL SERIAL PERANGKAT
|--------------------------------------------------------------------------
|
| Multi-device:
| device_serials[] = SERIAL_1
| device_serials[] = SERIAL_2
|
| Kompatibilitas form lama:
| device_serial = SERIAL_1
|--------------------------------------------------------------------------
*/

$requestedSerials = $_POST['device_serials'] ?? [];

if (!is_array($requestedSerials)) {
    $requestedSerials = [];
}

$singleSerial = trim(
    (string) ($_POST['device_serial'] ?? '')
);

if ($singleSerial !== '') {
    $requestedSerials[] = $singleSerial;
}

$requestedSerials = array_values(
    array_unique(
        array_filter(
            array_map(
                static fn(mixed $value): string =>
                    trim((string) $value),
                $requestedSerials
            ),
            static fn(string $value): bool =>
                $value !== ''
        )
    )
);

if ($requestedSerials === []) {
    sendJson(
        422,
        false,
        'Pilih minimal satu perangkat.'
    );
}

if (count($requestedSerials) > 100) {
    sendJson(
        422,
        false,
        'Maksimal 100 perangkat dalam satu pengiriman.'
    );
}

foreach ($requestedSerials as $serial) {
    if (
        strlen($serial) > 100
        || !preg_match('/^[A-Za-z0-9._:-]+$/', $serial)
    ) {
        sendJson(
            422,
            false,
            'Format serial perangkat tidak valid: ' . $serial
        );
    }
}

/*
|--------------------------------------------------------------------------
| VALIDASI DEVICE_MANAGER
|--------------------------------------------------------------------------
*/

$placeholders = implode(
    ', ',
    array_fill(0, count($requestedSerials), '?')
);

$sqlDevice = "
    SELECT
        device_serial,
        device_name,
        status
    FROM device_manager
    WHERE device_serial IN ($placeholders)
";

$stmtDevice = mysqli_prepare($conn, $sqlDevice);

if (!$stmtDevice) {
    sendJson(
        500,
        false,
        'Gagal menyiapkan validasi perangkat: '
        . mysqli_error($conn)
    );
}

$deviceTypes = str_repeat(
    's',
    count($requestedSerials)
);

bindStatementParams(
    $stmtDevice,
    $deviceTypes,
    $requestedSerials
);

if (!mysqli_stmt_execute($stmtDevice)) {
    $error = mysqli_stmt_error($stmtDevice);
    mysqli_stmt_close($stmtDevice);

    sendJson(
        500,
        false,
        'Gagal memvalidasi perangkat: ' . $error
    );
}

mysqli_stmt_store_result($stmtDevice);

mysqli_stmt_bind_result(
    $stmtDevice,
    $dbSerial,
    $dbDeviceName,
    $dbStatus
);

$registeredDevices = [];

while (mysqli_stmt_fetch($stmtDevice)) {
    $serialValue = trim((string) $dbSerial);

    if ($serialValue === '') {
        continue;
    }

    $registeredDevices[$serialValue] = [
        'device_serial' => $serialValue,
        'device_name' => trim((string) $dbDeviceName),
        'status' => trim((string) $dbStatus)
    ];
}

mysqli_stmt_close($stmtDevice);

$invalidDevices = [];

foreach ($requestedSerials as $serial) {
    if (!isset($registeredDevices[$serial])) {
        $invalidDevices[] = $serial;
    }
}

if ($invalidDevices !== []) {
    sendJson(
        422,
        false,
        'Perangkat tidak terdaftar pada device_manager: '
        . implode(', ', $invalidDevices),
        [
            'invalid_devices' => $invalidDevices
        ]
    );
}

/*
|--------------------------------------------------------------------------
| BUAT JOB OPEN_URL
|--------------------------------------------------------------------------
|
| Penting:
| Action selalu dipaksa menjadi "open_url".
| Nilai action dari browser tidak digunakan, sehingga endpoint ini tidak
| dapat membuat job "open_youtube" secara langsung.
|--------------------------------------------------------------------------
*/

$insertSql = "
    INSERT INTO adb_jobs (
        device_serial,
        action,
        payload,
        status,
        created_at
    )
    VALUES (
        ?,
        'open_url',
        ?,
        'waiting',
        NOW()
    )
";

$insertStmt = mysqli_prepare(
    $conn,
    $insertSql
);

if (!$insertStmt) {
    sendJson(
        500,
        false,
        'Gagal menyiapkan query antrean: '
        . mysqli_error($conn)
    );
}

$createdJobs = [];

if (!mysqli_begin_transaction($conn)) {
    mysqli_stmt_close($insertStmt);

    sendJson(
        500,
        false,
        'Gagal memulai transaksi database.'
    );
}

try {
    foreach ($requestedSerials as $serial) {
        mysqli_stmt_bind_param(
            $insertStmt,
            'ss',
            $serial,
            $url
        );

        if (!mysqli_stmt_execute($insertStmt)) {
            throw new RuntimeException(
                'Gagal membuat job untuk perangkat '
                . $serial
                . ': '
                . mysqli_stmt_error($insertStmt)
            );
        }

        $jobId = (int) mysqli_insert_id($conn);

        $createdJobs[] = [
            'job_id' => $jobId,
            'device_serial' => $serial,
            'device_name' => (
                $registeredDevices[$serial]['device_name']
                !== ''
            )
                ? $registeredDevices[$serial]['device_name']
                : $serial,
            'action' => 'open_url'
        ];
    }

    if (!mysqli_commit($conn)) {
        throw new RuntimeException(
            'Gagal menyimpan transaksi antrean.'
        );
    }
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    mysqli_stmt_close($insertStmt);

    error_log(
        '[adb_enqueue] Insert job gagal: '
        . $exception->getMessage()
    );

    sendJson(
        500,
        false,
        $exception->getMessage()
    );
}

mysqli_stmt_close($insertStmt);

$jobIds = array_map(
    static fn(array $job): int =>
        (int) $job['job_id'],
    $createdJobs
);

$createdCount = count($createdJobs);

sendJson(
    200,
    true,
    $createdCount === 1
        ? 'Perintah View berhasil dimasukkan ke antrean.'
        : $createdCount
            . ' perintah View berhasil dimasukkan ke antrean.',
    [
        'job_id' => $jobIds[0] ?? null,
        'job_ids' => $jobIds,
        'jobs' => $createdJobs,
        'created_count' => $createdCount,
        'total_jobs' => $createdCount,
        'device_count' => $createdCount,
        'action' => 'open_url'
    ]
);

/*
|--------------------------------------------------------------------------
| FUNCTIONS
|--------------------------------------------------------------------------
*/

function bindStatementParams(
    mysqli_stmt $stmt,
    string $types,
    array &$values
): void {
    $bindArguments = [
        &$stmt,
        &$types
    ];

    foreach ($values as &$value) {
        $bindArguments[] = &$value;
    }

    unset($value);

    $success = call_user_func_array(
        'mysqli_stmt_bind_param',
        $bindArguments
    );

    if ($success !== true) {
        throw new RuntimeException(
            'Gagal melakukan binding parameter database.'
        );
    }
}

function sendJson(
    int $status,
    bool $success,
    string $message,
    array $extra = []
): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);

    $response = array_merge(
        [
            'success' => $success,
            'message' => $message
        ],
        $extra
    );

    $json = json_encode(
        $response,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);

        echo '{"success":false,"message":"Gagal membuat response JSON."}';
        exit;
    }

    echo $json;
    exit;
}