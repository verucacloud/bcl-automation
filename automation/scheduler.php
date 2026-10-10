<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';

/**
 * Escape output agar aman dari XSS.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Membaca kolom tabel yang benar-benar tersedia.
 */
function getTableColumns(mysqli $conn, string $table): array
{
    $columns = [];

    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");

    if (!$result) {
        return $columns;
    }

    while ($row = $result->fetch_assoc()) {
        if (isset($row['Field'])) {
            $columns[] = (string) $row['Field'];
        }
    }

    return $columns;
}

/**
 * Menulis log hanya jika tabel dan kolom message tersedia.
 */
function writeAutomationLog(mysqli $conn, string $message): void
{
    $columns = getTableColumns($conn, 'automation_logs');

    if (!in_array('message', $columns, true)) {
        return;
    }

    $statement = $conn->prepare(
        'INSERT INTO `automation_logs` (`message`) VALUES (?)'
    );

    if (!$statement) {
        return;
    }

    $statement->bind_param('s', $message);
    $statement->execute();
    $statement->close();
}

/**
 * Format jadwal agar mudah dibaca.
 */
function formatSchedule(mixed $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y, H:i:s', $timestamp);
}

$queueColumns = getTableColumns($conn, 'automation_queue');

$requiredColumns = [
    'id',
    'content_name',
    'platform',
    'schedule_time',
    'status',
];

$missingColumns = array_values(
    array_diff($requiredColumns, $queueColumns)
);

if (count($missingColumns) > 0) {
    http_response_code(500);

    exit(
        'Kolom automation_queue belum lengkap: ' .
        e(implode(', ', $missingColumns))
    );
}

$executionTime = date('Y-m-d H:i:s');
$schedulerStatus = 'idle';
$message = 'Tidak ada task menunggu';
$taskName = '-';
$taskPlatform = '-';
$taskSchedule = '-';
$executedTaskId = null;
$errorMessage = '';

/**
 * Scheduler memakai tabel automation_queue yang memang tersedia.
 *
 * Task yang dapat diproses:
 * - status Waiting
 * - status Scheduled
 * - schedule_time sudah mencapai waktu sekarang
 */
$selectStatement = $conn->prepare(
    "SELECT
        `id`,
        `content_name`,
        `platform`,
        `schedule_time`,
        `status`
     FROM `automation_queue`
     WHERE LOWER(`status`) IN ('waiting', 'scheduled')
       AND `schedule_time` <= ?
     ORDER BY `schedule_time` ASC, `id` ASC
     LIMIT 1"
);

if (!$selectStatement) {
    http_response_code(500);
    exit('Prepare Error: ' . e($conn->error));
}

$selectStatement->bind_param('s', $executionTime);
$selectStatement->execute();

$result = $selectStatement->get_result();
$task = $result->fetch_assoc();

$selectStatement->close();

if ($task) {
    $executedTaskId = (int) $task['id'];
    $taskName = (string) $task['content_name'];
    $taskPlatform = (string) $task['platform'];
    $taskSchedule = (string) $task['schedule_time'];

    /**
     * Tandai task sebagai Processing lebih dulu.
     */
    $processingStatus = 'Processing';

    $processingStatement = $conn->prepare(
        'UPDATE `automation_queue`
         SET `status` = ?
         WHERE `id` = ?'
    );

    if ($processingStatement) {
        $processingStatement->bind_param(
            'si',
            $processingStatus,
            $executedTaskId
        );

        $processingStatement->execute();
        $processingStatement->close();
    }

    /**
     * Di bagian ini nantinya tempat integrasi upload API/device.
     * Untuk sekarang task dianggap berhasil setelah scheduler mengeksekusinya.
     */
    $completedStatus = 'Completed';

    $completeStatement = $conn->prepare(
        'UPDATE `automation_queue`
         SET `status` = ?
         WHERE `id` = ?'
    );

    if ($completeStatement) {
        $completeStatement->bind_param(
            'si',
            $completedStatus,
            $executedTaskId
        );

        if ($completeStatement->execute()) {
            $schedulerStatus = 'success';
            $message = 'Task berhasil diproses oleh scheduler';

            writeAutomationLog(
                $conn,
                sprintf(
                    'Scheduler completed queue #%d: %s on %s',
                    $executedTaskId,
                    $taskName,
                    $taskPlatform
                )
            );
        } else {
            $schedulerStatus = 'error';
            $message = 'Task ditemukan tetapi gagal diperbarui';
            $errorMessage = $completeStatement->error;

            writeAutomationLog(
                $conn,
                sprintf(
                    'Scheduler failed queue #%d: %s',
                    $executedTaskId,
                    $completeStatement->error
                )
            );
        }

        $completeStatement->close();
    } else {
        $schedulerStatus = 'error';
        $message = 'Gagal menyiapkan update task';
        $errorMessage = $conn->error;
    }
} else {
    writeAutomationLog(
        $conn,
        'Scheduler checked - no due waiting task'
    );
}

/**
 * Statistik queue.
 */
$totalQueue = 0;
$waitingQueue = 0;
$processingQueue = 0;
$completedQueue = 0;
$failedQueue = 0;

$statisticsResult = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(LOWER(`status`) IN ('waiting', 'scheduled')) AS waiting_total,
        SUM(LOWER(`status`) IN ('processing', 'running')) AS processing_total,
        SUM(LOWER(`status`) = 'completed') AS completed_total,
        SUM(LOWER(`status`) = 'failed') AS failed_total
     FROM `automation_queue`"
);

if ($statisticsResult) {
    $statistics = $statisticsResult->fetch_assoc();

    $totalQueue = (int) ($statistics['total'] ?? 0);
    $waitingQueue = (int) ($statistics['waiting_total'] ?? 0);
    $processingQueue = (int) ($statistics['processing_total'] ?? 0);
    $completedQueue = (int) ($statistics['completed_total'] ?? 0);
    $failedQueue = (int) ($statistics['failed_total'] ?? 0);
}

$statusMeta = match ($schedulerStatus) {
    'success' => [
        'label' => 'Task Executed',
        'text' => 'text-emerald-300',
        'badge' => 'border-emerald-400/20 bg-emerald-400/10',
        'dot' => 'bg-emerald-400',
        'icon' => 'circle-check-big',
    ],
    'error' => [
        'label' => 'Execution Error',
        'text' => 'text-rose-300',
        'badge' => 'border-rose-400/20 bg-rose-400/10',
        'dot' => 'bg-rose-400',
        'icon' => 'circle-x',
    ],
    default => [
        'label' => 'Scheduler Idle',
        'text' => 'text-amber-300',
        'badge' => 'border-amber-400/20 bg-amber-400/10',
        'dot' => 'bg-amber-400',
        'icon' => 'pause',
    ],
};
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Scheduler Engine</title>

    <meta
        name="description"
        content="Scheduler engine untuk memproses antrean automation berdasarkan waktu publikasi."
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
                href="automation_center.php"
                class="inline-flex items-center gap-3 text-sm font-bold text-slate-300 transition hover:text-white"
            >
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                </span>

                <span class="hidden sm:inline">Kembali ke Automation</span>
            </a>

            <div class="text-right">
                <p class="font-display text-sm font-extrabold sm:text-base">
                    Scheduler Engine
                </p>

                <p class="text-[11px] text-slate-500 sm:text-xs">
                    Queue Execution Monitor
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
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] <?= e($statusMeta['badge']) ?> <?= e($statusMeta['text']) ?>">
                        <span class="pulse-dot h-2 w-2 rounded-full <?= e($statusMeta['dot']) ?>"></span>
                        <?= e($statusMeta['label']) ?>
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            Scheduler
                        </span>

                        <br>

                        Execution Engine
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Scheduler membaca task dari tabel automation_queue dan memproses task Waiting atau Scheduled yang waktunya sudah tiba.
                    </p>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Execution result
                            </p>

                            <p class="mt-3 font-display text-3xl font-extrabold <?= e($statusMeta['text']) ?>">
                                <?= e($statusMeta['label']) ?>
                            </p>

                            <p class="mt-2 text-sm leading-6 text-slate-400">
                                <?= e($message) ?>
                            </p>
                        </div>

                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300">
                            <i data-lucide="<?= e($statusMeta['icon']) ?>" class="<?= $schedulerStatus === 'success' ? 'spin-slow' : '' ?> h-6 w-6"></i>
                        </span>
                    </div>

                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/[.04] p-4">
                        <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                            Execution time
                        </p>

                        <p class="mt-2 font-display text-lg font-extrabold">
                            <?= e(formatSchedule($executionTime)) ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="queueOverviewTitle">
            <div
                class="mb-5 flex items-end justify-between gap-4"
                data-aos="fade-right"
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                        Queue overview
                    </p>

                    <h2
                        id="queueOverviewTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Current automation queue
                    </h2>
                </div>

                <p class="hidden text-sm text-slate-500 sm:block">
                    Status setelah scheduler dijalankan
                </p>
            </div>

            <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <article class="glass card-hover rounded-[1.65rem] p-6" data-aos="fade-up">
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

                <article class="glass card-hover rounded-[1.65rem] p-6" data-aos="fade-up" data-aos-delay="80">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-amber-400/10 text-amber-300">
                        <i data-lucide="hourglass" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Waiting
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-amber-300">
                        <?= number_format($waitingQueue, 0, ',', '.') ?>
                    </p>
                </article>

                <article class="glass card-hover rounded-[1.65rem] p-6" data-aos="fade-up" data-aos-delay="160">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                        <i data-lucide="loader-circle" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Processing
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-violet-300">
                        <?= number_format($processingQueue, 0, ',', '.') ?>
                    </p>
                </article>

                <article
                    class="relative overflow-hidden rounded-[1.65rem] border border-emerald-300/20 bg-gradient-to-br from-emerald-600 via-cyan-600 to-indigo-700 p-6 shadow-cyanGlow"
                    data-aos="fade-up"
                    data-aos-delay="240"
                >
                    <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/15 blur-2xl"></div>

                    <span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white">
                        <i data-lucide="circle-check-big" class="h-6 w-6"></i>
                    </span>

                    <p class="relative mt-6 text-xs font-bold uppercase tracking-[.16em] text-white/65">
                        Completed
                    </p>

                    <p class="relative mt-2 font-display text-4xl font-extrabold">
                        <?= number_format($completedQueue, 0, ',', '.') ?>
                    </p>
                </article>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-[1.15fr_.85fr]">
            <article class="glass rounded-[1.75rem] p-6 sm:p-8" data-aos="fade-right">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                            Last execution
                        </p>

                        <h2 class="mt-2 font-display text-xl font-extrabold sm:text-2xl">
                            Processed task detail
                        </h2>
                    </div>

                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                        <i data-lucide="workflow" class="h-6 w-6"></i>
                    </span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <p class="text-xs uppercase tracking-[.14em] text-slate-500">
                            Content
                        </p>

                        <p class="mt-2 font-bold text-white">
                            <?= e($taskName) ?>
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <p class="text-xs uppercase tracking-[.14em] text-slate-500">
                            Platform
                        </p>

                        <p class="mt-2 font-bold text-cyan-200">
                            <?= e($taskPlatform) ?>
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:col-span-2">
                        <p class="text-xs uppercase tracking-[.14em] text-slate-500">
                            Scheduled time
                        </p>

                        <p class="mt-2 font-bold text-slate-200">
                            <?= e(formatSchedule($taskSchedule)) ?>
                        </p>
                    </div>
                </div>

                <?php if ($errorMessage !== ''): ?>
                    <div class="mt-5 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200">
                        <?= e($errorMessage) ?>
                    </div>
                <?php endif; ?>
            </article>

            <article
                class="relative overflow-hidden rounded-[1.75rem] border border-indigo-300/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-600 p-6 shadow-glow sm:p-8"
                data-aos="fade-left"
            >
                <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/15 blur-3xl"></div>

                <div class="relative">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.2em] text-white/65">
                                Scheduler control
                            </p>

                            <h2 class="mt-2 font-display text-xl font-extrabold">
                                Run next due task
                            </h2>
                        </div>

                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15">
                            <i data-lucide="play" class="h-6 w-6"></i>
                        </span>
                    </div>

                    <p class="mt-7 text-sm leading-7 text-white/75">
                        Refresh halaman ini untuk memeriksa dan memproses satu task berikutnya yang sudah mencapai schedule_time.
                    </p>

                    <a
                        href="scheduler.php"
                        class="mt-7 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-950 transition hover:-translate-y-1 hover:bg-cyan-100"
                    >
                        Run Scheduler Again
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    </a>

                    <?php if ($failedQueue > 0): ?>
                        <p class="mt-5 text-xs font-semibold text-rose-100">
                            <?= number_format($failedQueue, 0, ',', '.') ?> task berstatus Failed.
                        </p>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Scheduler • Automation Queue • Execution Logs</p>
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
    </script>
</body>
</html>