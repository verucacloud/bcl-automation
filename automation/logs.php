<?php

/*
|--------------------------------------------------------------------------
| Automation Logs
|--------------------------------------------------------------------------
| Lokasi:
| public_html/automation/logs.php
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';

/*
|--------------------------------------------------------------------------
| Validasi koneksi database
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);

    die(
        '<h3>Koneksi database tidak tersedia.</h3>' .
        '<p>Pastikan file config.php membuat variabel <b>$conn</b>.</p>'
    );
}

$conn->set_charset('utf8mb4');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatLogDate($value)
{
    if (empty($value)) {
        return [
            'date' => '-',
            'time' => '-'
        ];
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return [
            'date' => (string) $value,
            'time' => '-'
        ];
    }

    return [
        'date' => date('d M Y', $timestamp),
        'time' => date('H:i:s', $timestamp)
    ];
}

function normalizeStatus($status)
{
    return strtolower(trim((string) $status));
}

function statusMeta($status)
{
    $status = normalizeStatus($status);

    if (
        $status === 'success' ||
        $status === 'completed' ||
        $status === 'complete'
    ) {
        return [
            'label' => 'Success',
            'icon' => 'circle-check-big',
            'class' => 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300',
            'dot' => 'bg-emerald-400'
        ];
    }

    if (
        $status === 'processing' ||
        $status === 'running' ||
        $status === 'claimed'
    ) {
        return [
            'label' => 'Processing',
            'icon' => 'loader-circle',
            'class' => 'border-violet-400/20 bg-violet-400/10 text-violet-300',
            'dot' => 'bg-violet-400'
        ];
    }

    if (
        $status === 'waiting' ||
        $status === 'scheduled' ||
        $status === 'pending'
    ) {
        return [
            'label' => 'Waiting',
            'icon' => 'clock-3',
            'class' => 'border-amber-400/20 bg-amber-400/10 text-amber-300',
            'dot' => 'bg-amber-400'
        ];
    }

    if (
        $status === 'failed' ||
        $status === 'error'
    ) {
        return [
            'label' => 'Failed',
            'icon' => 'circle-x',
            'class' => 'border-rose-400/20 bg-rose-400/10 text-rose-300',
            'dot' => 'bg-rose-400'
        ];
    }

    if ($status === 'cancelled') {
        return [
            'label' => 'Cancelled',
            'icon' => 'ban',
            'class' => 'border-slate-400/20 bg-slate-400/10 text-slate-300',
            'dot' => 'bg-slate-400'
        ];
    }

    return [
        'label' => $status !== ''
            ? ucfirst($status)
            : 'Unknown',
        'icon' => 'circle-help',
        'class' => 'border-slate-400/20 bg-slate-400/10 text-slate-300',
        'dot' => 'bg-slate-400'
    ];
}

function getTableColumns($conn, $tableName)
{
    $columns = [];

    $safeTable = str_replace('`', '', $tableName);

    $result = $conn->query(
        "SHOW COLUMNS FROM `" . $safeTable . "`"
    );

    if (!$result) {
        return $columns;
    }

    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    return $columns;
}

function firstAvailableColumn($columns, $candidates)
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function getValueFromRow($row, $column, $default)
{
    if (
        $column !== null &&
        array_key_exists($column, $row)
    ) {
        return $row[$column];
    }

    return $default;
}

/*
|--------------------------------------------------------------------------
| Pastikan tabel tersedia
|--------------------------------------------------------------------------
*/

$tableCheck = $conn->query(
    "SHOW TABLES LIKE 'automation_logs'"
);

if (!$tableCheck || $tableCheck->num_rows === 0) {
    http_response_code(500);

    die(
        '<div style="
            max-width:760px;
            margin:60px auto;
            padding:30px;
            border:1px solid #ef4444;
            border-radius:18px;
            background:#111827;
            color:#fff;
            font-family:Arial,sans-serif;
        ">
            <h2>Tabel automation_logs belum tersedia</h2>
            <p>
                Buat atau import tabel
                <b>automation_logs</b>
                terlebih dahulu melalui phpMyAdmin.
            </p>
        </div>'
    );
}

/*
|--------------------------------------------------------------------------
| Deteksi nama kolom
|--------------------------------------------------------------------------
*/

$columns = getTableColumns(
    $conn,
    'automation_logs'
);

$idColumn = firstAvailableColumn(
    $columns,
    [
        'id',
        'log_id'
    ]
);

$moduleColumn = firstAvailableColumn(
    $columns,
    [
        'module',
        'source',
        'log_source',
        'category',
        'type'
    ]
);

$actionColumn = firstAvailableColumn(
    $columns,
    [
        'action',
        'activity',
        'event',
        'process',
        'task'
    ]
);

$statusColumn = firstAvailableColumn(
    $columns,
    [
        'status',
        'level',
        'log_level',
        'result'
    ]
);

$messageColumn = firstAvailableColumn(
    $columns,
    [
        'message',
        'description',
        'log_message',
        'details',
        'error_message'
    ]
);

$dateColumn = firstAvailableColumn(
    $columns,
    [
        'created_at',
        'logged_at',
        'created_date',
        'datetime',
        'date',
        'timestamp'
    ]
);

/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$statusFilter = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$whereParts = [];
$parameters = [];
$parameterTypes = '';

/*
|--------------------------------------------------------------------------
| Filter pencarian berdasarkan kolom yang tersedia
|--------------------------------------------------------------------------
*/

if ($search !== '') {
    $searchColumns = [];

    foreach (
        [
            $moduleColumn,
            $actionColumn,
            $messageColumn,
            $statusColumn
        ] as $searchColumn
    ) {
        if ($searchColumn !== null) {
            $searchColumns[] =
                "`" . $searchColumn . "` LIKE ?";
        }
    }

    if (!empty($searchColumns)) {
        $whereParts[] =
            '(' . implode(' OR ', $searchColumns) . ')';

        $keyword = '%' . $search . '%';

        foreach ($searchColumns as $unused) {
            $parameters[] = $keyword;
            $parameterTypes .= 's';
        }
    }
}

if (
    $statusFilter !== '' &&
    $statusColumn !== null
) {
    $whereParts[] =
        "LOWER(`" . $statusColumn . "`) = LOWER(?)";

    $parameters[] = $statusFilter;
    $parameterTypes .= 's';
}

/*
|--------------------------------------------------------------------------
| Query log
|--------------------------------------------------------------------------
*/

$sql = "SELECT * FROM `automation_logs`";

if (!empty($whereParts)) {
    $sql .= ' WHERE ' . implode(
        ' AND ',
        $whereParts
    );
}

if ($dateColumn !== null) {
    $sql .= " ORDER BY `" . $dateColumn . "` DESC";
} elseif ($idColumn !== null) {
    $sql .= " ORDER BY `" . $idColumn . "` DESC";
}

$sql .= ' LIMIT 200';

$stmt = $conn->prepare($sql);

$logs = [];
$queryError = '';

if (!$stmt) {
    $queryError = $conn->error;
} else {
    if (!empty($parameters)) {
        $bindValues = [];
        $bindValues[] = &$parameterTypes;

        foreach ($parameters as $key => $value) {
            $bindValues[] = &$parameters[$key];
        }

        call_user_func_array(
            [
                $stmt,
                'bind_param'
            ],
            $bindValues
        );
    }

    if (!$stmt->execute()) {
        $queryError = $stmt->error;
    } else {
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
        } else {
            $queryError = $stmt->error;
        }
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Hitung statistik berdasarkan hasil database
|--------------------------------------------------------------------------
*/

$totalLogs = 0;
$successCount = 0;
$processingCount = 0;
$waitingCount = 0;
$failedCount = 0;

$allLogsSql = "SELECT * FROM `automation_logs`";

$allLogsResult = $conn->query($allLogsSql);

if ($allLogsResult) {
    while ($statRow = $allLogsResult->fetch_assoc()) {
        $totalLogs++;

        $currentStatus = normalizeStatus(
            getValueFromRow(
                $statRow,
                $statusColumn,
                ''
            )
        );

        if (
            in_array(
                $currentStatus,
                [
                    'success',
                    'completed',
                    'complete'
                ],
                true
            )
        ) {
            $successCount++;
        }

        if (
            in_array(
                $currentStatus,
                [
                    'processing',
                    'running',
                    'claimed'
                ],
                true
            )
        ) {
            $processingCount++;
        }

        if (
            in_array(
                $currentStatus,
                [
                    'waiting',
                    'scheduled',
                    'pending'
                ],
                true
            )
        ) {
            $waitingCount++;
        }

        if (
            in_array(
                $currentStatus,
                [
                    'failed',
                    'error'
                ],
                true
            )
        ) {
            $failedCount++;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#020617"
    >

    <title>Automation Logs</title>

    <meta
        name="description"
        content="Riwayat aktivitas, error, dan proses automation."
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: [
                            'Inter',
                            'sans-serif'
                        ],
                        display: [
                            'Plus Jakarta Sans',
                            'sans-serif'
                        ]
                    },
                    boxShadow: {
                        glow:
                            '0 0 48px rgba(99,102,241,.24)'
                    }
                }
            }
        };
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            color-scheme: dark;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            overflow-x: hidden;
            background:
                radial-gradient(
                    circle at 5% 5%,
                    rgba(99, 102, 241, .26),
                    transparent 25rem
                ),
                radial-gradient(
                    circle at 94% 88%,
                    rgba(6, 182, 212, .17),
                    transparent 28rem
                ),
                radial-gradient(
                    circle at 72% 8%,
                    rgba(168, 85, 247, .10),
                    transparent 20rem
                ),
                #020617;
        }

        body::before {
            position: fixed;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            content: "";
            opacity: .15;
            background-image:
                linear-gradient(
                    rgba(255,255,255,.04) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(255,255,255,.04) 1px,
                    transparent 1px
                );
            background-size: 42px 42px;
            mask-image:
                linear-gradient(
                    to bottom,
                    black,
                    transparent 90%
                );
        }

        .glass {
            border: 1px solid rgba(255,255,255,.11);
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.10),
                    rgba(255,255,255,.045)
                );
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.06),
                0 22px 55px rgba(0,0,0,.17);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .card-hover {
            transition:
                transform .3s ease,
                border-color .3s ease,
                box-shadow .3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            border-color: rgba(129,140,248,.42);
            box-shadow:
                0 24px 60px rgba(2,6,23,.44),
                0 0 35px rgba(99,102,241,.12);
        }

        .table-scroll::-webkit-scrollbar {
            height: 8px;
        }

        .table-scroll::-webkit-scrollbar-track {
            border-radius: 999px;
            background: rgba(255,255,255,.04);
        }

        .table-scroll::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(99,102,241,.5);
        }

        .spin {
            animation: spin 1.1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body
    class="min-h-screen font-sans text-white antialiased selection:bg-indigo-500/40"
>

<header
    class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/45 backdrop-blur-xl"
>
    <div
        class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"
    >
        <a
            href="automation_center.php"
            class="inline-flex items-center gap-3 text-sm font-bold text-slate-300 transition hover:text-white"
        >
            <span
                class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5"
            >
                <i
                    data-lucide="arrow-left"
                    class="h-5 w-5"
                ></i>
            </span>

            <span class="hidden sm:inline">
                Kembali ke Automation Center
            </span>
        </a>

        <div class="text-right">
            <p
                class="font-display text-sm font-extrabold sm:text-base"
            >
                Automation Logs
            </p>

            <p class="text-[11px] text-slate-500 sm:text-xs">
                Activity • History • Errors
            </p>
        </div>
    </div>
</header>

<main
    class="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pb-24"
>

    <section
        class="relative mb-8 overflow-hidden rounded-[2rem] border border-white/10 px-5 py-8 sm:px-8 sm:py-10 lg:px-11"
    >
        <div
            class="absolute inset-0 -z-10 bg-gradient-to-br from-indigo-600/25 via-slate-950/20 to-cyan-500/15"
        ></div>

        <div
            class="absolute -right-16 -top-20 -z-10 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"
        ></div>

        <div
            class="flex flex-col justify-between gap-7 lg:flex-row lg:items-center"
        >
            <div>
                <div
                    class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-emerald-300"
                >
                    <span
                        class="h-2 w-2 rounded-full bg-emerald-400"
                    ></span>

                    Activity monitoring
                </div>

                <h1
                    class="font-display text-3xl font-extrabold leading-tight sm:text-5xl"
                >
                    Automation
                    <span
                        class="bg-gradient-to-r from-indigo-300 via-violet-300 to-cyan-300 bg-clip-text text-transparent"
                    >
                        Logs
                    </span>
                </h1>

                <p
                    class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base"
                >
                    Pantau seluruh aktivitas, status proses,
                    dan pesan error automation melalui satu halaman.
                </p>
            </div>

            <a
                href="logs.php"
                class="inline-flex w-fit items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-bold text-white transition hover:border-indigo-400/40 hover:bg-indigo-500/10"
            >
                <i
                    data-lucide="refresh-cw"
                    class="h-4 w-4"
                ></i>

                Refresh logs
            </a>
        </div>
    </section>

    <section
        class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-5"
    >
        <article
            class="glass card-hover rounded-[1.65rem] p-6"
        >
            <span
                class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300"
            >
                <i
                    data-lucide="scroll-text"
                    class="h-6 w-6"
                ></i>
            </span>

            <p
                class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500"
            >
                Total logs
            </p>

            <p
                class="mt-2 font-display text-4xl font-extrabold text-indigo-300"
            >
                <?= number_format($totalLogs, 0, ',', '.') ?>
            </p>
        </article>

        <article
            class="glass card-hover rounded-[1.65rem] p-6"
        >
            <span
                class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300"
            >
                <i
                    data-lucide="circle-check-big"
                    class="h-6 w-6"
                ></i>
            </span>

            <p
                class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500"
            >
                Success
            </p>

            <p
                class="mt-2 font-display text-4xl font-extrabold text-emerald-300"
            >
                <?= number_format($successCount, 0, ',', '.') ?>
            </p>
        </article>

        <article
            class="glass card-hover rounded-[1.65rem] p-6"
        >
            <span
                class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-400/10 text-violet-300"
            >
                <i
                    data-lucide="loader-circle"
                    class="spin h-6 w-6"
                ></i>
            </span>

            <p
                class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500"
            >
                Processing
            </p>

            <p
                class="mt-2 font-display text-4xl font-extrabold text-violet-300"
            >
                <?= number_format($processingCount, 0, ',', '.') ?>
            </p>
        </article>

        <article
            class="glass card-hover rounded-[1.65rem] p-6"
        >
            <span
                class="grid h-12 w-12 place-items-center rounded-2xl bg-amber-400/10 text-amber-300"
            >
                <i
                    data-lucide="clock-3"
                    class="h-6 w-6"
                ></i>
            </span>

            <p
                class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500"
            >
                Waiting
            </p>

            <p
                class="mt-2 font-display text-4xl font-extrabold text-amber-300"
            >
                <?= number_format($waitingCount, 0, ',', '.') ?>
            </p>
        </article>

        <article
            class="glass card-hover rounded-[1.65rem] p-6"
        >
            <span
                class="grid h-12 w-12 place-items-center rounded-2xl bg-rose-400/10 text-rose-300"
            >
                <i
                    data-lucide="circle-x"
                    class="h-6 w-6"
                ></i>
            </span>

            <p
                class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500"
            >
                Failed
            </p>

            <p
                class="mt-2 font-display text-4xl font-extrabold text-rose-300"
            >
                <?= number_format($failedCount, 0, ',', '.') ?>
            </p>
        </article>
    </section>

    <section class="glass overflow-hidden rounded-[1.75rem]">

        <div
            class="border-b border-white/10 p-5 sm:p-7"
        >
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300"
                    >
                        Activity history
                    </p>

                    <h2
                        class="mt-2 font-display text-xl font-extrabold sm:text-2xl"
                    >
                        Latest automation logs
                    </h2>
                </div>

                <span
                    class="inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300"
                >
                    <i
                        data-lucide="database"
                        class="h-4 w-4"
                    ></i>

                    <?= count($logs) ?> data
                </span>
            </div>
        </div>

        <form
            method="GET"
            action="logs.php"
            class="grid gap-3 border-b border-white/10 bg-white/[.02] p-5 sm:grid-cols-[1fr_220px_auto_auto] sm:p-7"
        >
            <div class="relative">
                <i
                    data-lucide="search"
                    class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500"
                ></i>

                <input
                    type="text"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Cari log..."
                    class="h-12 w-full rounded-xl border border-white/10 bg-slate-950/60 pl-11 pr-4 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-indigo-400/50"
                >
            </div>

            <select
                name="status"
                class="h-12 rounded-xl border border-white/10 bg-slate-950/60 px-4 text-sm text-white outline-none transition focus:border-indigo-400/50"
            >
                <option value="">Semua status</option>

                <?php
                $statusOptions = [
                    'success' => 'Success',
                    'processing' => 'Processing',
                    'waiting' => 'Waiting',
                    'failed' => 'Failed',
                    'cancelled' => 'Cancelled'
                ];
                ?>

                <?php foreach ($statusOptions as $value => $label): ?>
                    <option
                        value="<?= e($value) ?>"
                        <?= $statusFilter === $value
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button
                type="submit"
                class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 text-sm font-bold text-white transition hover:brightness-110"
            >
                <i
                    data-lucide="funnel"
                    class="h-4 w-4"
                ></i>

                Filter
            </button>

            <a
                href="logs.php"
                class="inline-flex h-12 items-center justify-center rounded-xl border border-white/10 bg-white/5 px-4 text-sm font-bold text-slate-300 transition hover:bg-white/10 hover:text-white"
            >
                Reset
            </a>
        </form>

        <?php if ($queryError !== ''): ?>

            <div class="m-5 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-5 text-sm text-rose-200 sm:m-7">
                <div class="flex items-start gap-3">
                    <i
                        data-lucide="triangle-alert"
                        class="mt-0.5 h-5 w-5 shrink-0"
                    ></i>

                    <div>
                        <p class="font-bold">
                            Query database gagal
                        </p>

                        <p class="mt-2 break-words text-rose-200/80">
                            <?= e($queryError) ?>
                        </p>
                    </div>
                </div>
            </div>

        <?php elseif (empty($logs)): ?>

            <div class="p-10 text-center sm:p-16">
                <span
                    class="mx-auto grid h-16 w-16 place-items-center rounded-2xl border border-white/10 bg-white/5 text-slate-400"
                >
                    <i
                        data-lucide="inbox"
                        class="h-8 w-8"
                    ></i>
                </span>

                <h3
                    class="mt-5 font-display text-xl font-extrabold"
                >
                    Belum ada log
                </h3>

                <p
                    class="mx-auto mt-2 max-w-lg text-sm leading-7 text-slate-400"
                >
                    Aktivitas automation akan muncul setelah
                    scheduler atau controller menjalankan proses.
                </p>
            </div>

        <?php else: ?>

            <div class="table-scroll overflow-x-auto">
                <table class="min-w-[1050px] w-full text-left">

                    <thead>
                        <tr
                            class="border-b border-white/10 bg-slate-950/20 text-xs uppercase tracking-[.15em] text-slate-500"
                        >
                            <th class="px-5 py-4 font-semibold">
                                ID
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Module
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Action
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Status
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Message
                            </th>

                            <th class="px-5 py-4 font-semibold">
                                Created
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($logs as $index => $row): ?>

                        <?php
                        $rowId = getValueFromRow(
                            $row,
                            $idColumn,
                            $index + 1
                        );

                        $rowModule = getValueFromRow(
                            $row,
                            $moduleColumn,
                            'Automation System'
                        );

                        $rowAction = getValueFromRow(
                            $row,
                            $actionColumn,
                            '-'
                        );

                        $rowStatus = getValueFromRow(
                            $row,
                            $statusColumn,
                            'info'
                        );

                        $rowMessage = getValueFromRow(
                            $row,
                            $messageColumn,
                            '-'
                        );

                        $rowDate = getValueFromRow(
                            $row,
                            $dateColumn,
                            ''
                        );

                        $dateMeta = formatLogDate($rowDate);
                        $statusInfo = statusMeta($rowStatus);
                        ?>

                        <tr
                            class="border-b border-white/[.07] transition hover:bg-white/[.04]"
                        >
                            <td class="px-5 py-5">
                                <span
                                    class="text-xs font-bold text-slate-500"
                                >
                                    #<?= e($rowId) ?>
                                </span>
                            </td>

                            <td class="px-5 py-5">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-400/10 text-indigo-300"
                                    >
                                        <i
                                            data-lucide="cpu"
                                            class="h-5 w-5"
                                        ></i>
                                    </span>

                                    <span
                                        class="font-bold text-slate-100"
                                    >
                                        <?= e($rowModule) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-5">
                                <span
                                    class="text-sm font-semibold text-slate-300"
                                >
                                    <?= e($rowAction) ?>
                                </span>
                            </td>

                            <td class="px-5 py-5">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($statusInfo['class']) ?>"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full <?= e($statusInfo['dot']) ?>"
                                    ></span>

                                    <i
                                        data-lucide="<?= e($statusInfo['icon']) ?>"
                                        class="<?= normalizeStatus($rowStatus) === 'processing' ? 'spin' : '' ?> h-4 w-4"
                                    ></i>

                                    <?= e($statusInfo['label']) ?>
                                </span>
                            </td>

                            <td class="max-w-md px-5 py-5">
                                <p
                                    class="whitespace-normal break-words text-sm leading-6 text-slate-400"
                                >
                                    <?= nl2br(e($rowMessage)) ?>
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-5 py-5">
                                <p
                                    class="text-sm font-semibold text-slate-300"
                                >
                                    <?= e($dateMeta['date']) ?>
                                </p>

                                <p class="mt-1 text-xs text-slate-500">
                                    <?= e($dateMeta['time']) ?> WIB
                                </p>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <div
                class="border-t border-white/10 px-5 py-4 text-xs text-slate-500 sm:px-7"
            >
                Menampilkan <?= count($logs) ?> dari maksimal
                200 aktivitas terbaru.
            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="border-t border-white/5 bg-slate-950/50">
    <div
        class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8"
    >
        <p>
            © <?= date('Y') ?>
            Social Media Intelligence Platform.
        </p>

        <p>
            Automation • Logs • Devices • Scheduler
        </p>
    </div>
</footer>

<script>
    lucide.createIcons();
</script>

</body>
</html>