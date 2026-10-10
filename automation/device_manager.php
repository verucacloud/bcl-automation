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
 * Membaca daftar kolom yang benar-benar tersedia pada tabel.
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
 * Menentukan tampilan badge berdasarkan status.
 */
function statusMeta(string $status): array
{
    $normalized = strtolower(trim($status));

    if ($normalized === 'online') {
        return [
            'label' => 'Online',
            'text' => 'text-emerald-300',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10',
            'dot' => 'bg-emerald-400',
            'icon' => 'wifi',
        ];
    }

    if ($normalized === 'maintenance') {
        return [
            'label' => 'Maintenance',
            'text' => 'text-amber-300',
            'badge' => 'border-amber-400/20 bg-amber-400/10',
            'dot' => 'bg-amber-400',
            'icon' => 'wrench',
        ];
    }

    return [
        'label' => $status !== '' ? ucfirst($status) : 'Offline',
        'text' => 'text-rose-300',
        'badge' => 'border-rose-400/20 bg-rose-400/10',
        'dot' => 'bg-rose-400',
        'icon' => 'wifi-off',
    ];
}

/**
 * Format tanggal hanya jika kolom last_update memang tersedia.
 */
function formatDateTime(mixed $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y, H:i', $timestamp);
}

$tableName = 'managed_devices';
$columns = getTableColumns($conn, $tableName);

if (count($columns) === 0) {
    http_response_code(500);
    exit('Tabel managed_devices tidak ditemukan atau tidak dapat dibaca.');
}

$hasRigName = in_array('controller_name', $columns, true);
$hasLastUpdate = in_array('last_seen_at', $columns, true);

/**
 * ORDER BY hanya memakai kolom yang benar-benar tersedia.
 * Berdasarkan database Anda, kolom saat ini adalah:
 * id, device_name, status
 */
$orderByParts = [];

if ($hasRigName) {
    $orderByParts[] = '`controller_name` ASC';
}

if (in_array('device_code', $columns, true)) {
    $orderByParts[] = '`device_code` ASC';
} elseif (in_array('id', $columns, true)) {
    $orderByParts[] = '`id` ASC';
}

$orderBy = count($orderByParts) > 0
    ? ' ORDER BY ' . implode(', ', $orderByParts)
    : '';

$query = $conn->query(
    "SELECT * FROM `{$tableName}`{$orderBy}"
);

if (!$query) {
    http_response_code(500);
    exit('Database Error: ' . e($conn->error));
}

$devices = [];

while ($row = $query->fetch_assoc()) {
    $devices[] = $row;
}

$total = count($devices);
$online = 0;
$offline = 0;
$maintenance = 0;
$rigNames = [];

foreach ($devices as $device) {
    $status = strtolower(trim((string) ($device['status'] ?? 'offline')));

    if ($status === 'online') {
        $online++;
    } elseif ($status === 'maintenance') {
        $maintenance++;
    } else {
        $offline++;
    }

    if ($hasRigName) {
        $rigName = trim((string) ($device['controller_name'] ?? ''));

        if ($rigName !== '') {
            $rigNames[$rigName] = true;
        }
    }
}

$rigCount = $hasRigName ? count($rigNames) : 0;
$percentage = $total > 0 ? round(($online / $total) * 100) : 0;

$healthLabel = match (true) {
    $percentage >= 90 => 'Excellent',
    $percentage >= 75 => 'Healthy',
    $percentage >= 50 => 'Needs Attention',
    default => 'Critical',
};

$healthTextClass = match (true) {
    $percentage >= 90 => 'text-emerald-300',
    $percentage >= 75 => 'text-cyan-300',
    $percentage >= 50 => 'text-amber-300',
    default => 'text-rose-300',
};
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Device Manager</title>

    <meta
        name="description"
        content="Dashboard monitoring status perangkat dan kesehatan device."
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

        .progress-shine {
            position: relative;
            overflow: hidden;
        }

        .progress-shine::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, .65),
                    transparent
                );
            animation: progressShine 2.7s linear infinite;
        }

        .pulse-dot {
            animation: pulseDot 1.8s ease-in-out infinite;
        }

        .orb {
            animation: orbMove 10s ease-in-out infinite alternate;
        }

        @keyframes gradientText {
            to {
                background-position: 220% center;
            }
        }

        @keyframes progressShine {
            100% {
                transform: translateX(100%);
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
                    Device Manager
                </p>

                <p class="text-[11px] text-slate-500 sm:text-xs">
                    Device Monitoring
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
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-cyan-300">
                        <span class="pulse-dot h-2 w-2 rounded-full bg-cyan-400"></span>
                        Device Monitoring Active
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            Device Manager
                        </span>
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Pantau status perangkat dan kesehatan keseluruhan device melalui satu dashboard.
                    </p>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Device health
                            </p>

                            <p class="mt-3 font-display text-5xl font-extrabold <?= e($healthTextClass) ?>">
                                <?= number_format($percentage, 0) ?><span class="text-2xl">%</span>
                            </p>

                            <p class="mt-2 text-sm font-semibold <?= e($healthTextClass) ?>">
                                <?= e($healthLabel) ?>
                            </p>
                        </div>

                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500/30 to-cyan-500/20 text-cyan-200">
                            <i data-lucide="server-cog" class="h-6 w-6"></i>
                        </span>
                    </div>

                    <div class="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="progress-shine h-full rounded-full bg-gradient-to-r from-emerald-400 via-cyan-300 to-indigo-400"
                            style="width: <?= number_format($percentage, 2, '.', '') ?>%"
                        ></div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Total device
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold text-indigo-200">
                                <?= number_format($total, 0, ',', '.') ?>
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Online device
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold text-emerald-300">
                                <?= number_format($online, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="summaryTitle">
            <div
                class="mb-5 flex items-end justify-between gap-4"
                data-aos="fade-right"
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                        Device overview
                    </p>

                    <h2
                        id="summaryTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Fleet status
                    </h2>
                </div>

                <p class="hidden text-sm text-slate-500 sm:block">
                    Current device snapshot
                </p>
            </div>

            <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    class="glass card-hover rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                        <i data-lucide="smartphone" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Total device
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-cyan-300">
                        <?= number_format($total, 0, ',', '.') ?>
                    </p>
                </article>

                <article
                    class="glass card-hover rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="80"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300">
                        <i data-lucide="wifi" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Online
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-emerald-300">
                        <?= number_format($online, 0, ',', '.') ?>
                    </p>
                </article>

                <article
                    class="glass card-hover rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="160"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-rose-400/10 text-rose-300">
                        <i data-lucide="wifi-off" class="h-6 w-6"></i>
                    </span>

                    <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                        Offline
                    </p>

                    <p class="mt-2 font-display text-4xl font-extrabold text-rose-300">
                        <?= number_format($offline, 0, ',', '.') ?>
                    </p>
                </article>

                <article
                    class="relative overflow-hidden rounded-[1.65rem] border border-amber-300/20 bg-gradient-to-br from-amber-600 via-orange-600 to-rose-700 p-6 shadow-glow"
                    data-aos="fade-up"
                    data-aos-delay="240"
                >
                    <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/15 blur-2xl"></div>

                    <span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white">
                        <i data-lucide="wrench" class="h-6 w-6"></i>
                    </span>

                    <p class="relative mt-6 text-xs font-bold uppercase tracking-[.16em] text-white/65">
                        Maintenance
                    </p>

                    <p class="relative mt-2 font-display text-4xl font-extrabold">
                        <?= number_format($maintenance, 0, ',', '.') ?>
                    </p>
                </article>
            </div>
        </section>

        <section
            class="glass rounded-[1.75rem] p-5 sm:p-8"
            data-aos="fade-up"
            aria-labelledby="deviceListTitle"
        >
            <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                        Device monitoring
                    </p>

                    <h2
                        id="deviceListTitle"
                        class="mt-2 font-display text-xl font-extrabold sm:text-2xl"
                    >
                        Device status list
                    </h2>
                </div>

                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full border border-emerald-400/15 bg-emerald-400/10 px-3 py-1.5 text-emerald-200">
                        <?= number_format($online, 0, ',', '.') ?> online
                    </span>

                    <span class="rounded-full border border-rose-400/15 bg-rose-400/10 px-3 py-1.5 text-rose-200">
                        <?= number_format($offline, 0, ',', '.') ?> offline
                    </span>

                    <?php if ($maintenance > 0): ?>
                        <span class="rounded-full border border-amber-400/15 bg-amber-400/10 px-3 py-1.5 text-amber-200">
                            <?= number_format($maintenance, 0, ',', '.') ?> maintenance
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($total === 0): ?>
                <div class="rounded-2xl border border-white/10 bg-white/[.035] p-8 text-center">
                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white/5 text-slate-400">
                        <i data-lucide="smartphone-off" class="h-8 w-8"></i>
                    </span>

                    <h3 class="mt-5 font-display text-xl font-extrabold">
                        Belum ada device terdaftar
                    </h3>

                    <p class="mx-auto mt-2 max-w-lg text-sm leading-7 text-slate-400">
                        Tambahkan data ke tabel
                        <code class="rounded bg-white/5 px-2 py-1 text-cyan-300">managed_devices</code>
                        untuk menampilkan status perangkat.
                    </p>
                </div>
            <?php else: ?>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($devices as $index => $device): ?>
                        <?php
                        $meta = statusMeta((string) ($device['status'] ?? 'Offline'));
                        ?>

                        <article
                            class="card-hover rounded-[1.6rem] border border-white/10 bg-white/[.035] p-5"
                            data-aos="fade-up"
                            data-aos-delay="<?= min($index * 40, 240) ?>"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300">
                                        <i data-lucide="smartphone" class="h-6 w-6"></i>
                                    </span>

                                    <div class="min-w-0">
                                        <h3 class="truncate font-display text-lg font-extrabold">
                                            <?= e($device['device_code'] ?? 'Unnamed Device') ?>
                                        </h3>

                                        <p class="mt-1 truncate text-sm text-slate-400">
                                            Device ID #<?= e($device['id'] ?? '-') ?>
                                        </p>
                                    </div>
                                </div>

                                <span class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($meta['badge']) ?> <?= e($meta['text']) ?>">
                                    <span class="h-2 w-2 rounded-full <?= e($meta['dot']) ?>"></span>
                                    <?= e($meta['label']) ?>
                                </span>
                            </div>

                            <div class="mt-6 grid <?= $hasRigName ? 'grid-cols-2' : 'grid-cols-1' ?> gap-3">
                                <div class="rounded-2xl bg-white/[.035] p-4">
                                    <p class="text-[11px] uppercase tracking-[.14em] text-slate-500">
                                        Status
                                    </p>

                                    <div class="mt-2 flex items-center gap-2 font-bold <?= e($meta['text']) ?>">
                                        <i data-lucide="<?= e($meta['icon']) ?>" class="h-4 w-4"></i>
                                        <?= e($meta['label']) ?>
                                    </div>
                                </div>

                                <?php if ($hasRigName): ?>
                                    <div class="rounded-2xl bg-white/[.035] p-4">
                                        <p class="text-[11px] uppercase tracking-[.14em] text-slate-500">
                                            Rig
                                        </p>

                                        <p class="mt-2 truncate font-bold text-slate-200">
                                            <?= e($device['controller_name'] ?? '-') ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($hasLastUpdate): ?>
                                <div class="mt-4 rounded-2xl border border-white/10 bg-slate-950/20 p-4">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/5 text-slate-400">
                                            <i data-lucide="clock-3" class="h-4 w-4"></i>
                                        </span>

                                        <div class="min-w-0">
                                            <p class="text-[11px] uppercase tracking-[.14em] text-slate-500">
                                                Last update
                                            </p>

                                            <p class="mt-1 truncate text-sm font-semibold text-slate-300">
                                                <?= e(formatDateTime($device['last_seen_at'] ?? '')) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Device Monitoring • Connectivity Health</p>
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