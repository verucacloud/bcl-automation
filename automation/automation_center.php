<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function safeCountQuery(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return isset($row['total']) && is_numeric($row['total'])
        ? (int) $row['total']
        : 0;
}

function statusMeta(string $status): array
{
    $normalized = strtolower(trim($status));

    return match ($normalized) {
        'waiting' => [
            'label' => 'Waiting',
            'icon' => 'hourglass',
            'text' => 'text-amber-300',
            'badge' => 'border-amber-400/20 bg-amber-400/10',
            'dot' => 'bg-amber-400',
        ],
        'scheduled' => [
            'label' => 'Scheduled',
            'icon' => 'calendar-clock',
            'text' => 'text-blue-300',
            'badge' => 'border-blue-400/20 bg-blue-400/10',
            'dot' => 'bg-blue-400',
        ],
        'processing' => [
            'label' => 'Processing',
            'icon' => 'loader-circle',
            'text' => 'text-violet-300',
            'badge' => 'border-violet-400/20 bg-violet-400/10',
            'dot' => 'bg-violet-400',
        ],
        'running' => [
            'label' => 'Running',
            'icon' => 'activity',
            'text' => 'text-emerald-300',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10',
            'dot' => 'bg-emerald-400',
        ],
        'completed' => [
            'label' => 'Completed',
            'icon' => 'circle-check-big',
            'text' => 'text-cyan-300',
            'badge' => 'border-cyan-400/20 bg-cyan-400/10',
            'dot' => 'bg-cyan-400',
        ],
        'failed' => [
            'label' => 'Failed',
            'icon' => 'circle-x',
            'text' => 'text-rose-300',
            'badge' => 'border-rose-400/20 bg-rose-400/10',
            'dot' => 'bg-rose-400',
        ],
        default => [
            'label' => $status !== '' ? ucfirst($status) : 'Unknown',
            'icon' => 'circle',
            'text' => 'text-slate-300',
            'badge' => 'border-white/10 bg-white/5',
            'dot' => 'bg-slate-400',
        ],
    };
}

function formatSchedule(string $value): string
{
    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y, H:i', $timestamp);
}


function getTableColumns(mysqli $conn, string $table): array
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return [];
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");

    if (!$result) {
        return [];
    }

    $columns = [];

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['Field'])) {
            $columns[] = (string) $row['Field'];
        }
    }

    return $columns;
}

function firstExistingColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function loadAdbDevices(mysqli $conn): array
{
    $columns = getTableColumns($conn, 'device_manager');

    if ($columns === []) {
        return [];
    }

    $serialColumn = firstExistingColumn(
        $columns,
        ['device_serial', 'adb_serial', 'serial_number', 'serial', 'device_id']
    );

    if ($serialColumn === null) {
        return [];
    }

    $nameColumn = firstExistingColumn(
        $columns,
        ['device_name', 'name', 'model', 'device_model', 'label']
    );

    $statusColumn = firstExistingColumn(
        $columns,
        ['status', 'connection_status', 'device_status']
    );

    $select = [
        "`{$serialColumn}` AS device_serial",
        $nameColumn !== null
            ? "`{$nameColumn}` AS device_name"
            : "'' AS device_name",
        $statusColumn !== null
            ? "`{$statusColumn}` AS device_status"
            : "'Unknown' AS device_status",
    ];

    $sql = 'SELECT ' . implode(', ', $select)
        . ' FROM `device_manager`'
        . " WHERE `{$serialColumn}` IS NOT NULL"
        . " AND TRIM(`{$serialColumn}`) <> ''";

    if ($statusColumn !== null) {
        $sql .= " ORDER BY CASE"
            . " WHEN LOWER(TRIM(`{$statusColumn}`)) = 'online' THEN 0"
            . " WHEN LOWER(TRIM(`{$statusColumn}`)) = 'device' THEN 0"
            . " ELSE 1 END, ";
    } else {
        $sql .= ' ORDER BY ';
    }

    $sql .= $nameColumn !== null
        ? "`{$nameColumn}` ASC"
        : "`{$serialColumn}` ASC";

    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    $devices = [];
    $seen = [];

    while ($row = $result->fetch_assoc()) {
        $serial = trim((string) ($row['device_serial'] ?? ''));

        if (
            $serial === ''
            || isset($seen[$serial])
            || !preg_match('/^[A-Za-z0-9._:-]{4,100}$/', $serial)
        ) {
            continue;
        }

        $seen[$serial] = true;

        $name = trim((string) ($row['device_name'] ?? ''));
        $status = trim((string) ($row['device_status'] ?? 'Unknown'));

        $devices[] = [
            'serial' => $serial,
            'name' => $name !== '' ? $name : 'Android Device',
            'status' => $status !== '' ? $status : 'Unknown',
            'online' => in_array(
                strtolower($status),
                ['online', 'device', 'connected', 'active'],
                true
            ),
        ];
    }

    return $devices;
}

$adbDevices = loadAdbDevices($conn);

$queueResult = $conn->query(
    "
    SELECT
        id,
        device_serial,
        action,
        payload,
        status,
        created_at,
        started_at,
        completed_at,
        result
    FROM adb_jobs
    ORDER BY id DESC
    LIMIT 100
    "
);

if (!$queueResult) {
    http_response_code(500);
    exit('Database Error: ' . e($conn->error));
}

$queueItems = [];

while ($row = $queueResult->fetch_assoc()) {
    $queueItems[] = $row;
}

$totalQueue = count($queueItems);
$onlineDevice = count(
    array_filter(
        $adbDevices,
        static fn (array $device): bool => (bool) ($device['online'] ?? false)
    )
);

if ($onlineDevice === 0 && $adbDevices === []) {
    $onlineDevice = safeCountQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM device_manager WHERE status = 'Online'"
    );
}

$waitingCount = 0;
$processingCount = 0;
$completedCount = 0;
$failedCount = 0;

foreach ($queueItems as $item) {
    $status = strtolower(trim((string) ($item['status'] ?? '')));

    if (in_array($status, ['waiting', 'scheduled'], true)) {
        $waitingCount++;
    }

    if (in_array($status, ['processing', 'running'], true)) {
        $processingCount++;
    }

    if ($status === 'completed') {
        $completedCount++;
    }

    if ($status === 'failed') {
        $failedCount++;
    }
}

$schedulerRunning = $processingCount > 0 || $waitingCount > 0;
$successRate = $totalQueue > 0
    ? ($completedCount / $totalQueue) * 100
    : 0;

$latestSchedule = $totalQueue > 0
    ? (string) ($queueItems[0]['created_at'] ?? '-')
    : '-';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Automation Center</title>

    <meta
        name="description"
        content="Dashboard scheduler, queue management, automation logs, dan device monitoring."
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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
                        sans: ['Inter', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'sans-serif']
                    },
                    boxShadow: {
                        glow: '0 0 48px rgba(99, 102, 241, .24)',
                        cyanGlow: '0 0 46px rgba(34, 211, 238, .20)'
                    }
                }
            }
        };
    </script>

    <link
        rel="stylesheet"
        href="https://unpkg.com/aos@2.3.1/dist/aos.css"
    >

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
                radial-gradient(circle at 5% 5%, rgba(99, 102, 241, .28), transparent 25rem),
                radial-gradient(circle at 94% 88%, rgba(6, 182, 212, .18), transparent 28rem),
                radial-gradient(circle at 72% 8%, rgba(168, 85, 247, .10), transparent 20rem),
                #020617;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            opacity: .17;
            background-image:
                linear-gradient(rgba(255, 255, 255, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .04) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 90%);
        }

        .noise {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            opacity: .035;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.55'/%3E%3C/svg%3E");
        }

        .glass {
            background:
                linear-gradient(
                    145deg,
                    rgba(255, 255, 255, .10),
                    rgba(255, 255, 255, .045)
                );
            border: 1px solid rgba(255, 255, 255, .11);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .06),
                0 22px 55px rgba(0, 0, 0, .17);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .card-hover {
            transition:
                transform .35s cubic-bezier(.2, .8, .2, 1),
                border-color .35s ease,
                box-shadow .35s ease;
        }

        .card-hover:hover {
            transform: translateY(-7px);
            border-color: rgba(129, 140, 248, .42);
            box-shadow:
                0 24px 60px rgba(2, 6, 23, .44),
                0 0 35px rgba(99, 102, 241, .12);
        }

        .animated-gradient-text {
            background:
                linear-gradient(
                    90deg,
                    #ffffff,
                    #a5b4fc,
                    #67e8f9,
                    #ffffff
                );
            background-size: 220% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: gradientText 7s linear infinite;
        }

        .pulse-dot {
            animation: pulseDot 1.8s ease-in-out infinite;
        }

        .spin-slow {
            animation: spinSlow 5s linear infinite;
        }

        .orb {
            animation: orbMove 10s ease-in-out infinite alternate;
        }

        .table-scroll::-webkit-scrollbar {
            height: 8px;
        }

        .table-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, .04);
            border-radius: 999px;
        }

        .table-scroll::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, .5);
            border-radius: 999px;
        }

        @keyframes gradientText {
            to {
                background-position: 220% center;
            }
        }

        @keyframes pulseDot {
            0%, 100% {
                opacity: .45;
                transform: scale(.85);
            }

            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }

        @keyframes spinSlow {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes orbMove {
            from {
                transform: translate3d(0, 0, 0) scale(1);
            }

            to {
                transform: translate3d(25px, -20px, 0) scale(1.15);
            }
        }


        /* Device selection state */
        .device-option {
            --device-accent: 34, 211, 238;
            --device-accent-strong: 6, 182, 212;
        }

        .device-option.device-accent-violet {
            --device-accent: 167, 139, 250;
            --device-accent-strong: 124, 58, 237;
        }

        .device-option.device-accent-emerald {
            --device-accent: 52, 211, 153;
            --device-accent-strong: 5, 150, 105;
        }

        .device-option.device-accent-amber {
            --device-accent: 251, 191, 36;
            --device-accent-strong: 217, 119, 6;
        }

        .device-option.is-selected {
            border-color: rgba(var(--device-accent), .78) !important;
            background:
                linear-gradient(
                    135deg,
                    rgba(var(--device-accent-strong), .22),
                    rgba(var(--device-accent), .08)
                ) !important;
            box-shadow:
                inset 0 0 0 1px rgba(var(--device-accent), .18),
                0 0 0 3px rgba(var(--device-accent), .08),
                0 16px 38px rgba(2, 6, 23, .34);
            transform: translateY(-2px);
        }

        .device-option.is-selected .device-icon {
            background: rgba(var(--device-accent), .18) !important;
            color: rgb(var(--device-accent)) !important;
            box-shadow: 0 0 22px rgba(var(--device-accent), .18);
        }

        .device-option.is-selected .device-check {
            border-color: rgb(var(--device-accent)) !important;
            background: rgb(var(--device-accent)) !important;
            color: #020617 !important;
            transform: scale(1.06);
        }

        .device-option .selected-label {
            display: none;
        }

        .device-option.is-selected .selected-label {
            display: inline-flex;
            border-color: rgba(var(--device-accent), .28);
            background: rgba(var(--device-accent), .12);
            color: rgb(var(--device-accent));
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body class="min-h-screen font-sans text-white antialiased selection:bg-indigo-500/40">
    <div class="noise" aria-hidden="true"></div>

    <header class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/45 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a
                href="../dashboard.php"
                class="inline-flex items-center gap-3 text-sm font-bold text-slate-300 transition hover:text-white"
            >
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                </span>

                <span class="hidden sm:inline">Kembali ke Dashboard</span>
            </a>

            <div class="text-right">
                <p class="font-display text-sm font-extrabold sm:text-base">
                    Automation Center
                </p>

                <p class="text-[11px] text-slate-500 sm:text-xs">
                    Scheduler • Queue • Devices
                </p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pb-24">
        <section
            class="relative mb-8 overflow-hidden rounded-[2rem] border border-white/10 px-5 py-8 sm:px-8 sm:py-10 lg:px-11"
            data-aos="fade-down"
        >
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-indigo-600/25 via-slate-950/20 to-cyan-500/15"></div>
            <div class="orb absolute -right-16 -top-20 -z-10 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="orb absolute -bottom-28 left-1/3 -z-10 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"></div>

            <div class="grid items-center gap-8 lg:grid-cols-[1.15fr_.85fr]">
                <div>
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-emerald-300">
                        <span class="pulse-dot h-2 w-2 rounded-full bg-emerald-400"></span>
                        Automation System Active
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            Automation
                        </span>

                        <br>

                        Control Center
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Kelola scheduler, antrean publikasi, aktivitas automation, dan status device melalui satu dashboard operasional.
                    </p>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Scheduler status
                            </p>

                            <p class="mt-3 font-display text-3xl font-extrabold text-emerald-300">
                                <?= $schedulerRunning ? 'Running' : 'Idle' ?>
                            </p>

                            <p class="mt-2 text-sm text-slate-400">
                                Latest queue: <?= e(formatSchedule($latestSchedule)) ?>
                            </p>
                        </div>

                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300">
                            <i data-lucide="settings-2" class="spin-slow h-6 w-6"></i>
                        </span>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Success rate
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold text-cyan-300">
                                <?= number_format($successRate, 1) ?>%
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Online device
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold text-violet-300">
                                <?= number_format($onlineDevice, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="statusTitle">
            <div
                class="mb-5 flex items-end justify-between gap-4"
                data-aos="fade-right"
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                        System overview
                    </p>

                    <h2
                        id="statusTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Automation status
                    </h2>
                </div>

                <p class="hidden text-sm text-slate-500 sm:block">
                    Realtime operational snapshot
                </p>
            </div>

            <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    class="glass card-hover rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300">
                        <i data-lucide="activity" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Scheduler
                    </p>

                    <p class="mt-2 font-display text-3xl font-extrabold text-emerald-300">
                        Running
                    </p>
                </article>

                <article
                    class="glass card-hover rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="80"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                        <i data-lucide="list-checks" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Total queue
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-cyan-300">
                        <?= number_format($totalQueue, 0, ',', '.') ?>
                    </p>
                </article>

                <article
                    class="glass card-hover rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="160"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                        <i data-lucide="smartphone" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Online device
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-violet-300">
                        <?= number_format($onlineDevice, 0, ',', '.') ?>
                    </p>
                </article>

                <article
                    class="relative overflow-hidden rounded-[1.65rem] border border-indigo-300/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-600 p-6 shadow-glow"
                    data-aos="fade-up"
                    data-aos-delay="240"
                >
                    <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/15 blur-2xl"></div>

                    <span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white">
                        <i data-lucide="circle-check-big" class="h-6 w-6"></i>
                    </span>

                    <p class="relative mt-6 text-xs font-bold uppercase tracking-[.16em] text-white/65">
                        Completed task
                    </p>

                    <p class="relative mt-2 font-display text-4xl font-extrabold">
                        <?= number_format($completedCount, 0, ',', '.') ?>
                    </p>
                </article>
            </div>
        </section>

        <section aria-labelledby="quickMenuTitle">
            <div
                class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                data-aos="fade-right"
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                        Quick access
                    </p>

                    <h2
                        id="quickMenuTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Automation modules
                    </h2>
                </div>

                <p class="max-w-md text-sm leading-6 text-slate-400">
                    Akses pengelolaan device, log aktivitas, dan scheduler engine.
                </p>
            </div>

            <div class="mb-8 grid gap-5 md:grid-cols-3">
                <a
                    href="device_manager.php"
                    class="glass card-hover group rounded-[1.7rem] p-6"
                    data-aos="fade-up"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300 transition group-hover:scale-110">
                        <i data-lucide="smartphone" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-xl font-extrabold">
                        Device Manager
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Monitor status, koneksi, dan kesehatan perangkat.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-cyan-300">
                        Open module
                        <i data-lucide="arrow-up-right" class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"></i>
                    </span>
                </a>

                <a
                    href="logs.php"
                    class="glass card-hover group rounded-[1.7rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="90"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-amber-400/10 text-amber-300 transition group-hover:scale-110">
                        <i data-lucide="scroll-text" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-xl font-extrabold">
                        Automation Logs
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Lihat riwayat aktivitas, error, dan proses automation.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-amber-300">
                        Open logs
                        <i data-lucide="arrow-up-right" class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"></i>
                    </span>
                </a>

                <a
                    href="scheduler.php"
                    class="card-hover group relative overflow-hidden rounded-[1.7rem] border border-indigo-300/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-600 p-6 shadow-glow"
                    data-aos="fade-up"
                    data-aos-delay="180"
                >
                    <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/15 blur-2xl"></div>

                    <span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white transition group-hover:scale-110">
                        <i data-lucide="calendar-clock" class="h-6 w-6"></i>
                    </span>

                    <h3 class="relative mt-6 font-display text-xl font-extrabold">
                        Scheduler Engine
                    </h3>

                    <p class="relative mt-2 text-sm leading-6 text-white/75">
                        Atur jadwal dan antrean eksekusi task otomatis.
                    </p>

                    <span class="relative mt-5 inline-flex items-center gap-2 text-sm font-bold text-white">
                        Open scheduler
                        <i data-lucide="arrow-up-right" class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"></i>
                    </span>
                </a>
            </div>
        </section>



        <!-- ADB REMOTE CONTROL -->
        <section
            class="glass relative mb-8 overflow-hidden rounded-[1.75rem] p-5 sm:p-8"
            data-aos="fade-up"
            aria-labelledby="adbControlTitle"
        >
            <div class="absolute -right-24 -top-24 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl"></div>

            <div class="relative">
                <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-cyan-300">
                            <span class="pulse-dot h-2 w-2 rounded-full bg-cyan-400"></span>
                            Android ADB Control
                        </div>

                        <h2
                            id="adbControlTitle"
                            class="font-display text-2xl font-extrabold sm:text-3xl"
                        >
                            Buka URL di beberapa perangkat
                        </h2>

                        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">
                            Pilih satu atau beberapa HP, lalu masukkan URL YouTube atau deep link. Sistem akan membuat satu job untuk setiap perangkat yang dipilih.
                        </p>
                    </div>

                    <div class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-300">
                        <i data-lucide="server-cog" class="h-4 w-4"></i>
                        Multi-device queue
                    </div>
                </div>

                <form id="adbUrlForm" class="space-y-5">
                    <div>
                        <div class="mb-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <label class="block text-sm font-bold text-slate-200">
                                    Pilih perangkat
                                </label>

                                <p class="mt-1 text-xs text-slate-500">
                                    Perangkat diambil dari tabel <code class="rounded bg-black/20 px-1.5 py-0.5 text-slate-300">device_manager</code>.
                                </p>
                            </div>

                            <?php if ($adbDevices !== []): ?>
                                <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-cyan-300">
                                    <input
                                        type="checkbox"
                                        id="selectAllDevices"
                                        class="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-500 focus:ring-cyan-500/30"
                                    >
                                    Pilih semua
                                </label>
                            <?php endif; ?>
                        </div>

                        <?php if ($adbDevices === []): ?>
                            <div class="rounded-2xl border border-amber-400/20 bg-amber-400/[.07] p-4 text-sm leading-6 text-amber-100">
                                Belum ada serial perangkat yang dapat dibaca dari tabel <strong>device_manager</strong>. Tambahkan kolom serial seperti
                                <code class="rounded bg-black/20 px-1.5 py-0.5">device_serial</code>, lalu isi serial hasil <code class="rounded bg-black/20 px-1.5 py-0.5">adb devices</code>.
                            </div>
                        <?php else: ?>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <?php foreach ($adbDevices as $index => $device): ?>
                                    <?php
                                    $statusText = (string) $device['status'];
                                    $isOnline = (bool) $device['online'];

                                    $deviceAccentClass = match ($index % 4) {
                                        0 => 'device-accent-cyan',
                                        1 => 'device-accent-violet',
                                        2 => 'device-accent-emerald',
                                        default => 'device-accent-amber',
                                    };
                                    ?>
                                    <label class="device-option <?= e($deviceAccentClass) ?> group relative cursor-pointer overflow-hidden rounded-2xl border border-white/10 bg-slate-950/45 p-4 transition duration-300 hover:-translate-y-0.5 hover:border-white/20">
                                        <input
                                            type="checkbox"
                                            name="device_serials[]"
                                            value="<?= e($device['serial']) ?>"
                                            class="device-checkbox peer sr-only"
                                            <?= $index === 0 ? 'checked' : '' ?>
                                        >

                                        <span class="pointer-events-none absolute inset-0 rounded-2xl ring-2 ring-transparent transition peer-checked:bg-cyan-400/[.06] peer-checked:ring-cyan-400/50"></span>

                                        <span class="relative flex items-start gap-3">
                                            <span class="device-icon grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-cyan-400/10 text-cyan-300 transition duration-300">
                                                <i data-lucide="smartphone" class="h-5 w-5"></i>
                                            </span>

                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-extrabold text-white">
                                                    <?= e($device['name']) ?>
                                                </span>

                                                <span class="mt-1 block break-all text-xs font-semibold text-slate-400">
                                                    <?= e($device['serial']) ?>
                                                </span>

                                                <span class="mt-3 inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[11px] font-bold <?= $isOnline ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300' : 'border-slate-400/20 bg-slate-400/10 text-slate-300' ?>">
                                                    <span class="h-1.5 w-1.5 rounded-full <?= $isOnline ? 'bg-emerald-400' : 'bg-slate-400' ?>"></span>
                                                    <?= e($statusText) ?>
                                                </span>

                                                <span class="selected-label mt-2 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-extrabold">
                                                    <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                                                    Dipilih
                                                </span>
                                            </span>

                                            <span class="device-check grid h-6 w-6 shrink-0 place-items-center rounded-full border border-white/15 bg-white/5 text-transparent transition duration-300">
                                                <i data-lucide="check" class="h-4 w-4"></i>
                                            </span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <p id="selectedDeviceCount" class="mt-3 text-xs font-semibold text-cyan-300">
                                1 perangkat dipilih
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <label for="content_url" class="mb-2 block text-sm font-bold text-slate-200">
                                URL atau deep link
                            </label>

                            <div class="relative">
                                <i data-lucide="link-2" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500"></i>

                                <input
                                    type="url"
                                    id="content_url"
                                    name="content_url"
                                    maxlength="2048"
                                    autocomplete="off"
                                    required
                                    class="h-13 w-full rounded-2xl border border-white/10 bg-slate-950/55 py-3.5 pl-12 pr-4 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-indigo-400/60 focus:ring-4 focus:ring-indigo-400/10"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                >
                            </div>
                        </div>

                        <button
                            type="submit"
                            id="adbSubmitButton"
                            <?= $adbDevices === [] ? 'disabled' : '' ?>
                            class="inline-flex min-h-[52px] items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 via-violet-600 to-cyan-600 px-7 py-3.5 text-sm font-extrabold text-white shadow-glow transition hover:-translate-y-0.5 hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i data-lucide="send" class="h-5 w-5"></i>
                            <span>Kirim ke perangkat</span>
                        </button>
                    </div>
                </form>

                <div
                    id="adbMessage"
                    class="mt-5 hidden rounded-2xl border px-4 py-3.5 text-sm font-semibold"
                    role="status"
                    aria-live="polite"
                ></div>

                <div class="mt-5 flex flex-col gap-2 rounded-2xl border border-amber-400/15 bg-amber-400/[.06] p-4 text-xs leading-6 text-amber-100/80 sm:flex-row sm:items-start">
                    <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-amber-300"></i>

                    <p>
                        Hostinger hanya menyimpan job. ADB Worker pada komputer lokal harus aktif dan setiap perintah wajib memakai
                        <code class="rounded bg-black/20 px-1.5 py-0.5 text-amber-200">adb -s SERIAL</code>
                        agar dikirim ke HP yang tepat.
                    </p>
                </div>
            </div>
        </section>

        <section
            class="glass rounded-[1.75rem] p-5 sm:p-8"
            data-aos="fade-up"
            aria-labelledby="queueTitle"
        >
            <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                        Queue management
                    </p>

                    <h2
                        id="queueTitle"
                        class="mt-2 font-display text-xl font-extrabold sm:text-2xl"
                    >
                        Automation queue
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="rounded-full border border-amber-400/15 bg-amber-400/10 px-3 py-1.5 text-amber-200">
                        <?= number_format($waitingCount, 0, ',', '.') ?> waiting
                    </span>

                    <span class="rounded-full border border-violet-400/15 bg-violet-400/10 px-3 py-1.5 text-violet-200">
                        <?= number_format($processingCount, 0, ',', '.') ?> processing
                    </span>

                    <span class="rounded-full border border-rose-400/15 bg-rose-400/10 px-3 py-1.5 text-rose-200">
                        <?= number_format($failedCount, 0, ',', '.') ?> failed
                    </span>

                    <button
                        type="button"
                        id="refreshQueueButton"
                        class="inline-flex items-center gap-1.5 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 font-bold text-cyan-200 transition hover:bg-cyan-400/15"
                    >
                        <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                        Refresh
                    </button>
                </div>
            </div>

            <?php if ($totalQueue === 0): ?>
                <div class="rounded-2xl border border-white/10 bg-white/[.035] p-8 text-center">
                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white/5 text-slate-400">
                        <i data-lucide="inbox" class="h-8 w-8"></i>
                    </span>

                    <h3 class="mt-5 font-display text-xl font-extrabold">
                        Queue masih kosong
                    </h3>

                    <p class="mx-auto mt-2 max-w-lg text-sm leading-7 text-slate-400">
                        Pilih perangkat, masukkan URL, lalu tekan Kirim ke perangkat untuk membuat job ADB.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-scroll overflow-x-auto">
                    <table class="min-w-[1100px] w-full text-left">
                        <thead>
                            <tr class="border-b border-white/10 text-xs uppercase tracking-[.15em] text-slate-500">
                                <th class="px-4 py-4 font-semibold">Device</th>
                                <th class="px-4 py-4 font-semibold">Action / URL</th>
                                <th class="px-4 py-4 font-semibold">Created</th>
                                <th class="px-4 py-4 font-semibold">Status</th>
                                <th class="px-4 py-4 font-semibold">Result</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($queueItems as $item): ?>
                                <?php
                                $meta = statusMeta((string) ($item['status'] ?? ''));
                                $payloadText = trim((string) ($item['payload'] ?? ''));
                                $resultText = trim((string) ($item['result'] ?? ''));
                                ?>

                                <tr class="border-b border-white/[.07] align-top transition hover:bg-white/[.04]">
                                    <td class="px-4 py-5">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-cyan-400/10 text-cyan-300">
                                                <i data-lucide="smartphone" class="h-5 w-5"></i>
                                            </span>

                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-100">
                                                    <?= e($item['device_serial'] ?? '-') ?>
                                                </p>

                                                <p class="mt-1 text-xs text-slate-500">
                                                    Job ID #<?= e($item['id'] ?? '-') ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-5">
                                        <span class="inline-flex rounded-full border border-indigo-300/15 bg-indigo-400/10 px-3 py-1.5 text-xs font-bold text-indigo-200">
                                            <?= e($item['action'] ?? '-') ?>
                                        </span>

                                        <p class="mt-2 max-w-[420px] break-all text-xs leading-5 text-slate-400">
                                            <?= e($payloadText !== '' ? $payloadText : '-') ?>
                                        </p>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-5">
                                        <div class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                                            <i data-lucide="clock-3" class="h-4 w-4 text-slate-500"></i>
                                            <?= e(formatSchedule((string) ($item['created_at'] ?? '-'))) ?>
                                        </div>
                                    </td>

                                    <td class="px-4 py-5">
                                        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($meta['badge']) ?> <?= e($meta['text']) ?>">
                                            <span class="h-2 w-2 rounded-full <?= e($meta['dot']) ?>"></span>
                                            <i data-lucide="<?= e($meta['icon']) ?>" class="h-4 w-4"></i>
                                            <?= e($meta['label']) ?>
                                        </span>
                                    </td>

                                    <td class="px-4 py-5">
                                        <p class="max-w-[360px] whitespace-pre-wrap break-words text-xs leading-5 text-slate-400">
                                            <?= e($resultText !== '' ? $resultText : '-') ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Scheduler • Queue • Logs • Device Monitoring</p>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        AOS.init({
            duration: 760,
            easing: 'ease-out-cubic',
            once: true,
            offset: 40,
            disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches
        });

        lucide.createIcons();

        const adbForm = document.getElementById('adbUrlForm');
        const adbButton = document.getElementById('adbSubmitButton');
        const adbMessage = document.getElementById('adbMessage');
        const selectAllDevices = document.getElementById('selectAllDevices');
        const selectedDeviceCount = document.getElementById('selectedDeviceCount');

        function getDeviceCheckboxes() {
            return Array.from(
                document.querySelectorAll('.device-checkbox')
            );
        }

        function getSelectedDevices() {
            return getDeviceCheckboxes().filter((checkbox) => checkbox.checked);
        }

        function updateDeviceSelection() {
            const checkboxes = getDeviceCheckboxes();
            const selected = getSelectedDevices();

            checkboxes.forEach((checkbox) => {
                const option = checkbox.closest('.device-option');

                if (option) {
                    option.classList.toggle(
                        'is-selected',
                        checkbox.checked
                    );

                    option.setAttribute(
                        'aria-checked',
                        checkbox.checked ? 'true' : 'false'
                    );
                }
            });

            if (selectedDeviceCount) {
                selectedDeviceCount.textContent =
                    selected.length + ' perangkat dipilih';
            }

            if (selectAllDevices) {
                selectAllDevices.checked =
                    checkboxes.length > 0
                    && selected.length === checkboxes.length;

                selectAllDevices.indeterminate =
                    selected.length > 0
                    && selected.length < checkboxes.length;
            }
        }

        getDeviceCheckboxes().forEach((checkbox) => {
            checkbox.addEventListener('change', updateDeviceSelection);
        });

        if (selectAllDevices) {
            selectAllDevices.addEventListener('change', () => {
                getDeviceCheckboxes().forEach((checkbox) => {
                    checkbox.checked = selectAllDevices.checked;
                });

                updateDeviceSelection();
            });
        }

        updateDeviceSelection();

        function showAdbMessage(type, message) {
            const baseClass =
                'mt-5 rounded-2xl border px-4 py-3.5 text-sm font-semibold';

            const typeClass = type === 'success'
                ? ' border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
                : ' border-rose-400/20 bg-rose-400/10 text-rose-200';

            adbMessage.className = baseClass + typeClass;
            adbMessage.textContent = message;
        }

        if (adbForm) {
            adbForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const selectedDevices = getSelectedDevices();

                if (selectedDevices.length === 0) {
                    showAdbMessage(
                        'error',
                        'Pilih minimal satu perangkat terlebih dahulu.'
                    );

                    return;
                }

                const originalButton = adbButton.innerHTML;

                adbButton.disabled = true;
                adbButton.innerHTML =
                    '<span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>'
                    + '<span>Mengirim...</span>';

                adbMessage.className = 'hidden';
                adbMessage.textContent = '';

                try {
                    const response = await fetch('api/adb_enqueue.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new FormData(adbForm)
                    });

                    const rawResponse = await response.text();
                    let result;

                    try {
                        result = JSON.parse(rawResponse);
                    } catch (parseError) {
                        throw new Error(
                            'Respons server bukan JSON. Periksa file api/adb_enqueue.php.'
                        );
                    }

                    if (!response.ok || result.success !== true) {
                        throw new Error(
                            result.message
                            || 'Perintah gagal dimasukkan ke antrean.'
                        );
                    }

                    const createdCount = Number(
                        result.created_count
                        ?? result.total_jobs
                        ?? selectedDevices.length
                    );

                    let jobInfo = '';

                    if (Array.isArray(result.job_ids) && result.job_ids.length > 0) {
                        jobInfo = ' Job ID: #' + result.job_ids.join(', #') + '.';
                    } else if (result.job_id) {
                        jobInfo = ' Job ID #' + result.job_id + '.';
                    }

                    showAdbMessage(
                        'success',
                        'Berhasil membuat '
                        + createdCount
                        + ' job untuk '
                        + selectedDevices.length
                        + ' perangkat.'
                        + jobInfo
                        + ' Pastikan seluruh ADB Worker sedang berjalan.'
                    );

                    document.getElementById('content_url').value = '';

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 6000);
                } catch (error) {
                    showAdbMessage(
                        'error',
                        error instanceof Error
                            ? error.message
                            : 'Terjadi kesalahan yang tidak diketahui.'
                    );
                } finally {
                    adbButton.disabled = false;
                    adbButton.innerHTML = originalButton;
                    lucide.createIcons();
                }
            });
        }


        const refreshQueueButton = document.getElementById('refreshQueueButton');

        if (refreshQueueButton) {
            refreshQueueButton.addEventListener('click', () => {
                refreshQueueButton.disabled = true;
                refreshQueueButton.innerHTML =
                    '<span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-cyan-200/30 border-t-cyan-200"></span>'
                    + '<span>Refreshing...</span>';

                window.location.reload();
            });
        }

    </script>
</body>
</html>