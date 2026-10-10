<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function compactNumber(mixed $value): string
{
    $number = is_numeric($value) ? (float) $value : 0.0;

    if ($number >= 1_000_000_000) {
        return rtrim(rtrim(number_format($number / 1_000_000_000, 1, '.', ''), '0'), '.') . 'B';
    }

    if ($number >= 1_000_000) {
        return rtrim(rtrim(number_format($number / 1_000_000, 1, '.', ''), '0'), '.') . 'M';
    }

    if ($number >= 1_000) {
        return rtrim(rtrim(number_format($number / 1_000, 1, '.', ''), '0'), '.') . 'K';
    }

    return number_format($number, 0, ',', '.');
}

function formatDateTime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Belum pernah';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? $value
        : date('d M Y, H:i', $timestamp);
}

$accounts = [];
$queryError = '';

$sql = "
    SELECT
        id,
        platform,
        platform_account_id,
        username,
        display_name,
        avatar_url,
        subscriber_count,
        video_count,
        view_count,
        status,
        last_sync_at,
        token_expires_at,
        created_at,
        updated_at
    FROM social_accounts
    ORDER BY
        CASE WHEN status = 'connected' THEN 0 ELSE 1 END,
        updated_at DESC,
        id DESC
";

$result = $conn->query($sql);

if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
} else {
    $queryError = 'Data analytics belum dapat dibaca.';
    error_log('Analytics query failed: ' . $conn->error);
}

$totalAccounts = count($accounts);
$connectedAccounts = 0;
$totalSubscribers = 0;
$totalVideos = 0;
$totalViews = 0;
$latestSyncTimestamp = 0;
$latestSyncLabel = 'Belum pernah';

$chartLabels = [];
$chartSubscribers = [];
$chartVideos = [];
$chartViews = [];

foreach ($accounts as $account) {
    if (($account['status'] ?? '') === 'connected') {
        $connectedAccounts++;
    }

    $subscriberCount = (int) ($account['subscriber_count'] ?? 0);
    $videoCount = (int) ($account['video_count'] ?? 0);
    $viewCount = (int) ($account['view_count'] ?? 0);

    $totalSubscribers += $subscriberCount;
    $totalVideos += $videoCount;
    $totalViews += $viewCount;

    $lastSync = trim((string) ($account['last_sync_at'] ?? ''));
    $lastSyncTimestamp = $lastSync !== '' ? strtotime($lastSync) : false;

    if ($lastSyncTimestamp !== false && $lastSyncTimestamp > $latestSyncTimestamp) {
        $latestSyncTimestamp = $lastSyncTimestamp;
        $latestSyncLabel = formatDateTime($lastSync);
    }

    $chartLabels[] = (string) ($account['display_name'] ?? 'Akun');
    $chartSubscribers[] = $subscriberCount;
    $chartVideos[] = $videoCount;
    $chartViews[] = $viewCount;
}

$connectionRate = $totalAccounts > 0
    ? round(($connectedAccounts / $totalAccounts) * 100)
    : 0;

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">
    <title>Analytics Dashboard — Social Intelligence</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">

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
                        glow: '0 0 45px rgba(99, 102, 241, .25)'
                    }
                }
            }
        };
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 8% 4%, rgba(99, 102, 241, .25), transparent 25rem),
                radial-gradient(circle at 90% 82%, rgba(6, 182, 212, .16), transparent 27rem),
                #020617;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            opacity: .16;
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 88%);
        }
        .glass {
            background: linear-gradient(145deg, rgba(255,255,255,.10), rgba(255,255,255,.045));
            border: 1px solid rgba(255,255,255,.11);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.06), 0 22px 55px rgba(0,0,0,.17);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .card-hover {
            transition: transform .3s ease, border-color .3s ease, background .3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            border-color: rgba(129,140,248,.42);
        }
    </style>
</head>
<body class="font-sans text-white antialiased">
    <header class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/65 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="../dashboard.php" class="inline-flex items-center gap-3 font-bold text-slate-300 transition hover:text-white">
                <span class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-white/5">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                </span>
                <span class="hidden sm:inline">Dashboard</span>
            </a>

            <div class="text-right">
                <p class="font-display font-extrabold">Analytics Dashboard</p>
                <p class="text-xs text-slate-500">Ringkasan semua akun terhubung</p>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <?php if ($queryError !== ''): ?>
            <div class="mb-6 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm font-semibold text-rose-200">
                <?= e($queryError) ?>
            </div>
        <?php endif; ?>

        <section class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-indigo-600/25 via-slate-950/30 to-cyan-500/15 p-6 sm:p-9">
            <div class="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-indigo-500/15 blur-3xl"></div>
            <div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">Intelligence overview</p>
                    <h1 class="mt-3 max-w-3xl font-display text-3xl font-extrabold sm:text-5xl">Pantau performa seluruh akun dari satu dashboard.</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">Data diambil dari tabel <strong>social_accounts</strong> dan diperbarui melalui fitur sinkronisasi akun (Instagram, TikTok, YouTube).</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="index.php" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-5 font-bold transition hover:bg-white/10">
                        <i data-lucide="users" class="h-5 w-5"></i>
                        Kelola Akun
                    </a>
                    <a href="index.php#platforms" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-cyan-500 px-5 font-bold shadow-glow transition hover:-translate-y-0.5">
                        <i data-lucide="plus" class="h-5 w-5"></i>
                        Tambah Akun
                    </a>
                </div>
            </div>
        </section>

        <section class="mt-7 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-5">
            <article class="glass card-hover rounded-[1.6rem] p-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-400">Akun</p>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-400/10 text-indigo-300"><i data-lucide="users" class="h-5 w-5"></i></span>
                </div>
                <p class="mt-6 font-display text-4xl font-extrabold"><?= number_format($totalAccounts, 0, ',', '.') ?></p>
                <p class="mt-2 text-xs text-slate-500"><?= number_format($connectedAccounts, 0, ',', '.') ?> akun connected</p>
            </article>

            <article class="glass card-hover rounded-[1.6rem] p-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-400">Subscribers</p>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-violet-400/10 text-violet-300"><i data-lucide="user-plus" class="h-5 w-5"></i></span>
                </div>
                <p class="mt-6 font-display text-4xl font-extrabold"><?= e(compactNumber($totalSubscribers)) ?></p>
                <p class="mt-2 text-xs text-slate-500"><?= number_format($totalSubscribers, 0, ',', '.') ?> total subscriber</p>
            </article>

            <article class="glass card-hover rounded-[1.6rem] p-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-400">Videos</p>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-400/10 text-rose-300"><i data-lucide="clapperboard" class="h-5 w-5"></i></span>
                </div>
                <p class="mt-6 font-display text-4xl font-extrabold"><?= e(compactNumber($totalVideos)) ?></p>
                <p class="mt-2 text-xs text-slate-500">Video publik terdata</p>
            </article>

            <article class="glass card-hover rounded-[1.6rem] p-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-400">Views</p>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-400/10 text-cyan-300"><i data-lucide="eye" class="h-5 w-5"></i></span>
                </div>
                <p class="mt-6 font-display text-4xl font-extrabold"><?= e(compactNumber($totalViews)) ?></p>
                <p class="mt-2 text-xs text-slate-500"><?= number_format($totalViews, 0, ',', '.') ?> total views</p>
            </article>

            <article class="relative overflow-hidden rounded-[1.6rem] border border-emerald-300/20 bg-gradient-to-br from-emerald-600 via-cyan-600 to-indigo-700 p-6 shadow-glow">
                <div class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>
                <div class="relative flex items-center justify-between">
                    <p class="text-sm font-semibold text-white/80">Connection Health</p>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/15"><i data-lucide="activity" class="h-5 w-5"></i></span>
                </div>
                <p class="relative mt-6 font-display text-4xl font-extrabold"><?= (int) $connectionRate ?>%</p>
                <p class="relative mt-2 text-xs text-white/70">Akun berstatus connected</p>
            </article>
        </section>

        <section class="mt-7 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
            <article class="glass rounded-[1.75rem] p-5 sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">Account comparison</p>
                        <h2 class="mt-2 font-display text-2xl font-extrabold">Perbandingan performa akun</h2>
                    </div>
                    <i data-lucide="chart-no-axes-combined" class="h-7 w-7 text-indigo-300"></i>
                </div>
                <div class="mt-7 h-[340px]">
                    <canvas id="accountChart"></canvas>
                </div>
            </article>

            <article class="glass rounded-[1.75rem] p-5 sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">System status</p>
                        <h2 class="mt-2 font-display text-2xl font-extrabold">Ringkasan sinkronisasi</h2>
                    </div>
                    <i data-lucide="refresh-cw" class="h-7 w-7 text-cyan-300"></i>
                </div>

                <div class="mt-7 grid gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Sinkronisasi terbaru</p>
                        <p class="mt-2 font-bold text-cyan-300"><?= e($latestSyncLabel) ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-5">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Akun connected</p>
                        <p class="mt-2 font-display text-3xl font-extrabold"><?= number_format($connectedAccounts, 0, ',', '.') ?> / <?= number_format($totalAccounts, 0, ',', '.') ?></p>
                    </div>
                    <div class="rounded-2xl border border-amber-400/15 bg-amber-400/[.06] p-5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-amber-300"></i>
                            <p class="text-sm leading-6 text-slate-400">Grafik saat ini memakai snapshot terbaru. Grafik pertumbuhan harian membutuhkan tabel riwayat analytics yang akan dibuat pada tahap berikutnya.</p>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-7">
            <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Connected accounts</p>
                    <h2 class="mt-2 font-display text-2xl font-extrabold">Daftar akun & statistik</h2>
                </div>
                <span class="w-fit rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300"><?= number_format($totalAccounts, 0, ',', '.') ?> akun</span>
            </div>

            <?php if (empty($accounts)): ?>
                <div class="glass rounded-[1.75rem] p-8 text-center">
                    <i data-lucide="circle-user-round" class="mx-auto h-10 w-10 text-slate-500"></i>
                    <h3 class="mt-4 font-display text-xl font-extrabold">Belum ada akun</h3>
                    <p class="mt-2 text-sm text-slate-400">Hubungkan akun YouTube untuk mulai menampilkan analytics.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $status = strtolower((string) ($account['status'] ?? 'error'));
                        $isConnected = $status === 'connected';
                        $platform = strtolower((string) ($account['platform'] ?? 'instagram'));
                        $displayName = (string) ($account['display_name'] ?? 'Akun Sosial');
                        $username = (string) ($account['username'] ?? '');
                        $avatarUrl = trim((string) ($account['avatar_url'] ?? ''));
                        $initial = strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'));

                        $platformLabel = match($platform) {
                            'instagram' => 'Instagram',
                            'tiktok' => 'TikTok',
                            'youtube' => 'YouTube',
                            default => ucfirst($platform)
                        };

                        $metric1Label = match($platform) {
                            'youtube' => 'Subscribers',
                            default => 'Followers'
                        };

                        $metric2Label = match($platform) {
                            'instagram' => 'Posts',
                            default => 'Videos'
                        };

                        $syncUrl = match($platform) {
                            'instagram' => "../connectors/instagram/sync.php?id=" . (int)$account['id'],
                            'tiktok' => "../connectors/tiktok/sync.php?id=" . (int)$account['id'],
                            default => "../connectors/youtube/sync.php?id=" . (int)$account['id'],
                        };
                        ?>
                        <article class="glass card-hover rounded-[1.6rem] p-5 sm:p-6">
                            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                                <div class="flex min-w-0 items-center gap-4">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?= e($avatarUrl) ?>" alt="<?= e($displayName) ?>" class="h-14 w-14 shrink-0 rounded-2xl object-cover" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 font-display text-xl font-extrabold"><?= e($initial) ?></div>
                                    <?php endif; ?>

                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate font-display text-lg font-extrabold"><?= e($displayName) ?></h3>
                                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold <?= $isConnected ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300' : 'border-rose-400/20 bg-rose-400/10 text-rose-300' ?>"><?= e($status) ?></span>
                                        </div>
                                        <p class="mt-1 truncate text-sm text-slate-500"><?= e($platformLabel) ?><?= $username !== '' ? ' • @' . ltrim(e($username), '@') : '' ?></p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3 sm:min-w-[360px]">
                                    <div class="rounded-xl bg-white/[.04] px-3 py-3 text-center">
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500"><?= $metric1Label ?></p>
                                        <p class="mt-1 font-bold"><?= e(compactNumber($account['subscriber_count'] ?? 0)) ?></p>
                                    </div>
                                    <div class="rounded-xl bg-white/[.04] px-3 py-3 text-center">
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500"><?= $metric2Label ?></p>
                                        <p class="mt-1 font-bold"><?= e(compactNumber($account['video_count'] ?? 0)) ?></p>
                                    </div>
                                    <div class="rounded-xl bg-white/[.04] px-3 py-3 text-center">
                                        <p class="text-[10px] uppercase tracking-wider text-slate-500">Views</p>
                                        <p class="mt-1 font-bold"><?= e(compactNumber($account['view_count'] ?? 0)) ?></p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
                                    <a href="detail.php?id=<?= (int) $account['id'] ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-slate-950 transition hover:bg-cyan-200">
                                        <i data-lucide="monitor" class="h-4 w-4"></i>
                                        Monitoring
                                    </a>
                                    <a href="<?= e($syncUrl) ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 text-sm font-bold text-cyan-300 transition hover:bg-cyan-400/20">
                                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                        Sync
                                    </a>
                                </div>
                            </div>
                            <p class="mt-4 text-xs text-slate-600">Terakhir sinkron: <?= e(formatDateTime($account['last_sync_at'] ?? null)) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="mt-8 border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:justify-between sm:px-6 lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform</p>
            <p>Analytics • OAuth • Monitoring • Sync</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();

        const chartElement = document.getElementById('accountChart');

        if (chartElement) {
            new Chart(chartElement, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                    datasets: [
                        {
                            label: 'Subscribers',
                            data: <?= json_encode($chartSubscribers) ?>,
                            borderWidth: 1
                        },
                        {
                            label: 'Videos',
                            data: <?= json_encode($chartVideos) ?>,
                            borderWidth: 1
                        },
                        {
                            label: 'Views',
                            data: <?= json_encode($chartViews) ?>,
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#cbd5e1'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148,163,184,.08)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#94a3b8' },
                            grid: { color: 'rgba(148,163,184,.08)' }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>