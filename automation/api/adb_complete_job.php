<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ADB COMPLETE JOB API
|--------------------------------------------------------------------------
|
| Fungsi:
| - Menerima laporan hasil eksekusi dari ADB Worker lokal.
| - Mengubah status job dari processing menjadi completed/failed.
| - Menyimpan output eksekusi.
| - Mengembalikan respons JSON yang bersih.
|
| Method:
| POST
|
| Header:
| X-Worker-Token: TOKEN_RAHASIA
| Content-Type: application/json
|
| Body:
| {
|   "job_id": 12,
|   "status": "completed",
|   "result": "Starting: Intent ..."
| }
|
*/

ob_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config.php';

/*
|--------------------------------------------------------------------------
| KONFIGURASI TOKEN
|--------------------------------------------------------------------------
|
| Nilai ini harus sama persis dengan token pada:
|
| 1. C:\android-tools\adb_worker.php
| 2. public_html/automation/api/adb_pull_job.php
| 3. public_html/automation/api/adb_complete_job.php
|
*/

const WORKER_TOKEN = 'MBG_ADB_2026_X9kP7mQ4rT8vN2sL';

/*
|--------------------------------------------------------------------------
| HELPER JSON
|--------------------------------------------------------------------------
*/

function sendJson(
    array $data,
    int $httpCode = 200
): never {
    if (ob_get_length() !== false) {
        ob_clean();
    }

    http_response_code($httpCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| ERROR HANDLER
|--------------------------------------------------------------------------
*/

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);

/*
|--------------------------------------------------------------------------
| VALIDASI METHOD
|--------------------------------------------------------------------------
*/

$requestMethod = strtoupper(
    (string) ($_SERVER['REQUEST_METHOD'] ?? '')
);

if ($requestMethod !== 'POST') {
    sendJson(
        [
            'success' => false,
            'message' => 'Metode tidak diizinkan.'
        ],
        405
    );
}

/*
|--------------------------------------------------------------------------
| VALIDASI DATABASE
|--------------------------------------------------------------------------
*/

if (
    !isset($conn)
    || !($conn instanceof mysqli)
) {
    error_log(
        '[adb_complete_job] Koneksi database tidak tersedia.'
    );

    sendJson(
        [
            'success' => false,
            'message' => 'Koneksi database tidak tersedia.'
        ],
        500
    );
}

mysqli_set_charset($conn, 'utf8mb4');

/*
|--------------------------------------------------------------------------
| VALIDASI TOKEN
|--------------------------------------------------------------------------
*/

$requestToken = trim(
    (string) (
        $_SERVER['HTTP_X_WORKER_TOKEN']
        ?? ''
    )
);

if (
    WORKER_TOKEN === ''
    || $requestToken === ''
    || !hash_equals(
        WORKER_TOKEN,
        $requestToken
    )
) {
    sendJson(
        [
            'success' => false,
            'message' => 'Token worker tidak valid.'
        ],
        401
    );
}

/*
|--------------------------------------------------------------------------
| BACA BODY JSON
|--------------------------------------------------------------------------
*/

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    sendJson(
        [
            'success' => false,
            'message' => 'Body JSON kosong.'
        ],
        400
    );
}

$body = json_decode(
    $rawBody,
    true
);

if (
    json_last_error() !== JSON_ERROR_NONE
    || !is_array($body)
) {
    sendJson(
        [
            'success' => false,
            'message' => 'Format JSON tidak valid.'
        ],
        400
    );
}

/*
|--------------------------------------------------------------------------
| VALIDASI DATA
|--------------------------------------------------------------------------
*/

$jobId = filter_var(
    $body['job_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

$status = strtolower(
    trim(
        (string) ($body['status'] ?? '')
    )
);

$resultText = trim(
    (string) ($body['result'] ?? '')
);

if ($jobId === false) {
    sendJson(
        [
            'success' => false,
            'message' => 'Job ID tidak valid.'
        ],
        422
    );
}

if (
    !in_array(
        $status,
        ['completed', 'failed'],
        true
    )
) {
    sendJson(
        [
            'success' => false,
            'message' => 'Status job tidak valid.'
        ],
        422
    );
}

/*
 * Batasi panjang output agar database tidak terlalu besar.
 */
if (strlen($resultText) > 10000) {
    $resultText = substr(
        $resultText,
        0,
        10000
    );
}

if ($resultText === '') {
    $resultText = $status === 'completed'
        ? 'Job berhasil dijalankan.'
        : 'Job gagal dijalankan tanpa detail output.';
}

/*
|--------------------------------------------------------------------------
| UPDATE JOB
|--------------------------------------------------------------------------
*/

$statement = null;

try {
    $sql = "
        UPDATE adb_jobs
        SET
            status = ?,
            result = ?,
            completed_at = NOW()
        WHERE id = ?
          AND status = 'processing'
        LIMIT 1
    ";

    $statement = mysqli_prepare(
        $conn,
        $sql
    );

    if (!$statement) {
        throw new RuntimeException(
            'Gagal menyiapkan query update job: '
            . mysqli_error($conn)
        );
    }

    if (
        !mysqli_stmt_bind_param(
            $statement,
            'ssi',
            $status,
            $resultText,
            $jobId
        )
    ) {
        throw new RuntimeException(
            'Gagal mengikat parameter update job.'
        );
    }

    if (!mysqli_stmt_execute($statement)) {
        throw new RuntimeException(
            'Gagal menjalankan update job: '
            . mysqli_stmt_error($statement)
        );
    }

    $affectedRows = mysqli_stmt_affected_rows(
        $statement
    );

    mysqli_stmt_close($statement);
    $statement = null;

    /*
     * Tidak ada baris berubah berarti:
     * - job tidak ditemukan, atau
     * - status job bukan processing, atau
     * - job sudah diselesaikan sebelumnya.
     */
    if ($affectedRows !== 1) {
        $checkStatement = mysqli_prepare(
            $conn,
            "
            SELECT id, status
            FROM adb_jobs
            WHERE id = ?
            LIMIT 1
            "
        );

        if (!$checkStatement) {
            throw new RuntimeException(
                'Gagal memeriksa status job.'
            );
        }

        mysqli_stmt_bind_param(
            $checkStatement,
            'i',
            $jobId
        );

        mysqli_stmt_execute(
            $checkStatement
        );

        $checkResult = mysqli_stmt_get_result(
            $checkStatement
        );

        $existingJob = $checkResult
            ? mysqli_fetch_assoc($checkResult)
            : null;

        if ($checkResult instanceof mysqli_result) {
            mysqli_free_result($checkResult);
        }

        mysqli_stmt_close($checkStatement);

        if (!$existingJob) {
            sendJson(
                [
                    'success' => false,
                    'message' => 'Job tidak ditemukan.',
                    'job_id' => $jobId
                ],
                404
            );
        }

        $currentStatus = strtolower(
            trim(
                (string) (
                    $existingJob['status']
                    ?? ''
                )
            )
        );

        /*
         * Bila worker mengirim ulang hasil yang sama,
         * anggap sukses agar worker tidak terus retry.
         */
        if (
            in_array(
                $currentStatus,
                ['completed', 'failed'],
                true
            )
        ) {
            sendJson(
                [
                    'success' => true,
                    'message' => 'Job sudah diselesaikan sebelumnya.',
                    'job_id' => $jobId,
                    'status' => $currentStatus,
                    'already_completed' => true
                ]
            );
        }

        sendJson(
            [
                'success' => false,
                'message' =>
                    'Job tidak dapat diselesaikan karena status saat ini: '
                    . (
                        $currentStatus !== ''
                            ? $currentStatus
                            : 'unknown'
                    ),
                'job_id' => $jobId,
                'current_status' => $currentStatus
            ],
            409
        );
    }

    sendJson(
        [
            'success' => true,
            'message' => 'Status job berhasil diperbarui.',
            'job_id' => $jobId,
            'status' => $status
        ]
    );
} catch (Throwable $exception) {
    if ($statement instanceof mysqli_stmt) {
        mysqli_stmt_close($statement);
    }

    error_log(
        '[adb_complete_job] '
        . $exception->getMessage()
        . ' | job_id='
        . (
            is_int($jobId)
                ? $jobId
                : 'invalid'
        )
    );

    sendJson(
        [
            'success' => false,
            'message' => 'Gagal memperbarui status job.'
        ],
        500
    );
}