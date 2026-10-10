<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konfigurasi error production
|--------------------------------------------------------------------------
| Error tidak ditampilkan kepada pengunjung, tetapi tetap dicatat ke log.
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

/*
|--------------------------------------------------------------------------
| Koneksi database
|--------------------------------------------------------------------------
| Dashboard dibuat publik, jadi auth_check.php tidak digunakan.
*/

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Status konfigurasi Google OAuth / YouTube
|--------------------------------------------------------------------------
| Dashboard hanya membaca apakah konfigurasi tersedia. Client Secret tidak
| pernah ditampilkan ke halaman.
*/

$localConfigFile = __DIR__ . '/config.local.php';
$localConfig = [];

if (is_file($localConfigFile)) {
    $loadedConfig = require $localConfigFile;

    if (is_array($loadedConfig)) {
        $localConfig = $loadedConfig;
    }
}

$googleClientReady =
    !empty($localConfig['google_client_id']) &&
    !empty($localConfig['google_client_secret']) &&
    !empty($localConfig['google_redirect_uri']);

$connectedYouTubeAccounts = 0;

$tableCheck = $conn->query("SHOW TABLES LIKE 'social_accounts'");

if ($tableCheck instanceof mysqli_result && $tableCheck->num_rows > 0) {
    $youtubeCountResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM social_accounts
         WHERE platform = 'youtube'
           AND status = 'connected'"
    );

    if ($youtubeCountResult instanceof mysqli_result) {
        $youtubeCountRow = $youtubeCountResult->fetch_assoc();
        $connectedYouTubeAccounts = isset($youtubeCountRow['total'])
            ? (int) $youtubeCountRow['total']
            : 0;
    }
}

/**
 * Escape output agar aman dari XSS.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Batasi skor antara 0 sampai 100.
 */
function clampScore(mixed $value): float
{
    $score = is_numeric($value)
        ? (float) $value
        : 0.0;

    return max(0.0, min(100.0, $score));
}

/**
 * Format angka menjadi 1.2K, 2.5M, dan seterusnya.
 */
function compactNumber($value): string
{
    $number = is_numeric($value)
        ? (float) $value
        : 0.0;

    if ($number >= 1000000000) {
        return rtrim(
            rtrim(
                number_format(
                    $number / 1000000000,
                    1,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . 'B';
    }

    if ($number >= 1000000) {
        return rtrim(
            rtrim(
                number_format(
                    $number / 1000000,
                    1,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . 'M';
    }

    if ($number >= 1000) {
        return rtrim(
            rtrim(
                number_format(
                    $number / 1000,
                    1,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . 'K';
    }

    return number_format($number, 0, ',', '.');
}

/**
 * Tentukan status berdasarkan algorithm score.
 *
 * @return array{
 *     label: string,
 *     text: string,
 *     badge: string,
 *     dot: string
 * }
 */
function algorithmStatus(float $score): array
{
    if ($score >= 85) {
        return [
            'label' => 'Excellent',
            'text' => 'text-emerald-300',
            'badge' => 'bg-emerald-400/10 border-emerald-400/20',
            'dot' => 'bg-emerald-400',
        ];
    }

    if ($score >= 70) {
        return [
            'label' => 'Healthy',
            'text' => 'text-cyan-300',
            'badge' => 'bg-cyan-400/10 border-cyan-400/20',
            'dot' => 'bg-cyan-400',
        ];
    }

    if ($score >= 50) {
        return [
            'label' => 'Needs Attention',
            'text' => 'text-amber-300',
            'badge' => 'bg-amber-400/10 border-amber-400/20',
            'dot' => 'bg-amber-400',
        ];
    }

    return [
        'label' => 'Critical',
        'text' => 'text-rose-300',
        'badge' => 'bg-rose-400/10 border-rose-400/20',
        'dot' => 'bg-rose-400',
    ];
}

/*
|--------------------------------------------------------------------------
| Data default
|--------------------------------------------------------------------------
| Digunakan jika tabel account kosong atau query gagal.
*/

$a = [
    'username' => 'Belum ada akun',
    'platform' => 'Hubungkan akun media sosial',
    'followers' => 0,
    'growth' => 0,
    'engagement_rate' => 0,
    'algorithm_score' => 0,
];

/*
|--------------------------------------------------------------------------
| Ambil akun pertama untuk dashboard publik
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT
        id,
        username,
        platform,
        followers,
        growth,
        engagement_rate,
        algorithm_score
     FROM account
     ORDER BY id ASC
     LIMIT 1'
);

if (!$stmt) {
    error_log(
        'Prepare account query failed: ' .
        $conn->error
    );
} else {
    if (!$stmt->execute()) {
        error_log(
            'Execute account query failed: ' .
            $stmt->error
        );
    } else {
        $result = $stmt->get_result();

        if ($result instanceof mysqli_result) {
            $accountData = $result->fetch_assoc();

            if (is_array($accountData)) {
                $a = $accountData;
            }
        }
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Prioritaskan akun sosial yang benar-benar terhubung
|--------------------------------------------------------------------------
| Jika tersedia, akun YouTube terbaru dari social_accounts digunakan sebagai
| akun utama dashboard. Data tabel account lama tetap menjadi fallback.
*/

if ($connectedYouTubeAccounts > 0) {
    $socialAccountStatement = $conn->prepare(
        "SELECT
            id,
            username,
            display_name,
            platform,
            subscriber_count,
            video_count,
            view_count,
            status,
            last_sync_at
         FROM social_accounts
         WHERE platform = 'youtube'
           AND status = 'connected'
         ORDER BY COALESCE(last_sync_at, created_at) DESC, id DESC
         LIMIT 1"
    );

    if (!$socialAccountStatement) {
        error_log(
            'Prepare social account query failed: ' .
            $conn->error
        );
    } elseif (!$socialAccountStatement->execute()) {
        error_log(
            'Execute social account query failed: ' .
            $socialAccountStatement->error
        );
    } else {
        $socialAccountResult = $socialAccountStatement->get_result();

        if ($socialAccountResult instanceof mysqli_result) {
            $socialAccount = $socialAccountResult->fetch_assoc();

            if (is_array($socialAccount)) {
                $a['username'] = !empty($socialAccount['display_name'])
                    ? $socialAccount['display_name']
                    : ($socialAccount['username'] ?? 'YouTube Channel');

                $a['platform'] = 'YouTube';

                $a['followers'] = is_numeric(
                    $socialAccount['subscriber_count'] ?? null
                )
                    ? (float) $socialAccount['subscriber_count']
                    : 0.0;
            }
        }
    }

    if ($socialAccountStatement instanceof mysqli_stmt) {
        $socialAccountStatement->close();
    }
}

/*
|--------------------------------------------------------------------------
| Siapkan data untuk tampilan
|--------------------------------------------------------------------------
*/

$username = e(
    $a['username'] ?? 'Belum ada akun'
);

$platform = e(
    $a['platform'] ?? 'Unknown Platform'
);

$followers = is_numeric($a['followers'] ?? null)
    ? (float) $a['followers']
    : 0.0;

$growth = is_numeric($a['growth'] ?? null)
    ? (float) $a['growth']
    : 0.0;

$engagementRate = is_numeric(
    $a['engagement_rate'] ?? null
)
    ? (float) $a['engagement_rate']
    : 0.0;

$algorithmScore = clampScore(
    $a['algorithm_score'] ?? 0
);

$status = algorithmStatus($algorithmScore);

$growthPrefix = $growth > 0
    ? '+'
    : '';

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Social Media Intelligence Platform</title>

    <meta
        name="description"
        content="Dashboard monitoring algoritma, pertumbuhan akun, evaluasi konten, strategi upload, dan automation media sosial."
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
                        glow: '0 0 45px rgba(99, 102, 241, .25)',
                        cyanGlow: '0 0 45px rgba(34, 211, 238, .20)'
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
                radial-gradient(circle at 8% 4%, rgba(99, 102, 241, .28), transparent 25rem),
                radial-gradient(circle at 90% 84%, rgba(6, 182, 212, .18), transparent 27rem),
                radial-gradient(circle at 65% 5%, rgba(168, 85, 247, .11), transparent 20rem),
                #020617;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            opacity: .18;
            background-image:
                linear-gradient(rgba(255, 255, 255, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .04) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 88%);
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
                box-shadow .35s ease,
                background .35s ease;
        }

        .card-hover:hover {
            transform: translateY(-7px);
            border-color: rgba(129, 140, 248, .42);
            box-shadow:
                0 24px 60px rgba(2, 6, 23, .44),
                0 0 35px rgba(99, 102, 241, .12);
        }

        .float {
            animation: floating 5s ease-in-out infinite;
        }

        .pulse-dot {
            animation: pulseDot 1.8s ease-in-out infinite;
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
            animation: progressShine 2.6s linear infinite;
        }

        .module-icon {
            transition:
                transform .35s ease,
                background .35s ease;
        }

        .module-card:hover .module-icon {
            transform: scale(1.08) rotate(-4deg);
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

        .nav-link {
            position: relative;
        }

        .nav-link::after {
            content: "";
            position: absolute;
            left: 0;
            right: 100%;
            bottom: -6px;
            height: 2px;
            border-radius: 999px;
            background:
                linear-gradient(
                    90deg,
                    #818cf8,
                    #22d3ee
                );
            transition: right .25s ease;
        }

        .nav-link:hover::after {
            right: 0;
        }

        .orb {
            animation: orbMove 10s ease-in-out infinite alternate;
        }

        @keyframes floating {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-10px) rotate(1deg);
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

        @keyframes progressShine {
            100% {
                transform: translateX(100%);
            }
        }

        @keyframes gradientText {
            to {
                background-position: 220% center;
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

    <div
        id="mobileOverlay"
        class="fixed inset-0 z-40 hidden bg-slate-950/75 backdrop-blur-sm lg:hidden"
    ></div>

    <aside
        id="mobileMenu"
        class="fixed right-0 top-0 z-50 flex h-full w-[86%] max-w-sm translate-x-full flex-col border-l border-white/10 bg-slate-950/95 p-6 shadow-2xl backdrop-blur-2xl transition-transform duration-300 lg:hidden"
        aria-label="Navigasi mobile"
    >
        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-lg font-extrabold">
                    Intelligence Platform
                </p>

                <p class="mt-1 text-xs text-slate-400">
                    Social Media Operations
                </p>
            </div>

            <button
                id="closeMenu"
                type="button"
                class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300"
                aria-label="Tutup menu"
            >
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <nav class="mt-10 grid gap-3">
            <a
                href="#overview"
                class="rounded-2xl bg-white/5 px-4 py-3 font-semibold text-slate-200"
            >
                Overview
            </a>

            <a
                href="#analytics"
                class="rounded-2xl bg-white/5 px-4 py-3 font-semibold text-slate-200"
            >
                Analytics Module
            </a>

            <a
                href="api_configuration.php"
                class="rounded-2xl bg-white/5 px-4 py-3 font-semibold text-slate-200"
            >
                API Configuration
            </a>

            <a
                href="accounts/analytics.php"
                class="rounded-2xl bg-white/5 px-4 py-3 font-semibold text-slate-200"
            >
                Account Analytics
            </a>

            <a
                href="automation/automation_center.php"
                class="rounded-2xl bg-gradient-to-r from-indigo-600 to-cyan-600 px-4 py-3 font-bold text-white"
            >
                Automation Center
            </a>
        </nav>

        <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs uppercase tracking-[.2em] text-slate-500">
                Account aktif
            </p>

            <p class="mt-2 font-bold text-white">
                <?= $username ?>
            </p>

            <p class="text-sm text-indigo-300">
                <?= $platform ?>
            </p>
        </div>
    </aside>

    <header class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/45 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
    
            <!-- Logo / Brand -->
            <a
                href="#overview"
                class="flex min-w-0 items-center gap-3"
                aria-label="Kembali ke overview"
            >
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-cyan-500 shadow-glow">
                    <i
                        data-lucide="brain-circuit"
                        class="h-6 w-6"
                    ></i>
                </div>
    
                <div class="min-w-0">
                    <p class="truncate font-display text-sm font-extrabold text-white sm:text-base">
                        Social Intelligence
                    </p>
    
                    <p class="truncate text-[11px] text-slate-400 sm:text-xs">
                        Monitoring • Analytics • Automation
                    </p>
                </div>
            </a>
    
            <!-- Desktop Navigation -->
            <nav
                class="hidden items-center gap-2 text-sm font-semibold text-slate-300 lg:flex"
                aria-label="Navigasi utama"
            >
                <a
                    href="#overview"
                    class="nav-link rounded-xl px-4 py-2.5 transition hover:bg-white/5 hover:text-white"
                >
                    Overview
                </a>
    
                <a
                    href="#analytics"
                    class="nav-link rounded-xl px-4 py-2.5 transition hover:bg-white/5 hover:text-white"
                >
                    Modules
                </a>
    
                <a
                    href="api_configuration.php"
                    class="nav-link rounded-xl px-4 py-2.5 transition hover:bg-white/5 hover:text-white"
                >
                    API Configuration
                </a>

                <a
                    href="accounts/analytics.php"
                    class="nav-link rounded-xl px-4 py-2.5 transition hover:bg-white/5 hover:text-white"
                >
                    Analytics
                </a>

                <a
                    href="accounts/index.php"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-400/20 bg-indigo-400/10 px-4 py-2.5 font-bold text-indigo-200 transition hover:-translate-y-0.5 hover:border-indigo-300/40 hover:bg-indigo-400/20 hover:text-white"
                >
                    <i
                        data-lucide="circle-plus"
                        class="h-4 w-4"
                    ></i>
    
                    Hubungkan Akun
                </a>
    
                <a
                    href="automation/automation_center.php"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 font-bold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200"
                >
                    <i
                        data-lucide="workflow"
                        class="h-4 w-4"
                    ></i>
    
                    Automation Center
                </a>
            </nav>
    
            <!-- Mobile Menu Button -->
            <button
                id="openMenu"
                type="button"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/5 text-white transition hover:border-white/20 hover:bg-white/10 lg:hidden"
                aria-label="Buka menu"
                aria-controls="mobileMenu"
                aria-expanded="false"
            >
                <i
                    data-lucide="menu"
                    class="h-5 w-5"
                ></i>
            </button>
        </div>
    </header>

    <main
        id="overview"
        class="mx-auto max-w-7xl px-4 pb-16 pt-8 sm:px-6 sm:pt-12 lg:px-8 lg:pb-24"
    >
        <section
            class="relative mb-10 overflow-hidden rounded-[2rem] border border-white/10 px-5 py-8 sm:px-8 sm:py-10 lg:px-11 lg:py-12"
            data-aos="fade-down"
        >
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-indigo-600/25 via-slate-950/25 to-cyan-500/15"></div>

            <div class="orb absolute -right-16 -top-20 -z-10 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"></div>

            <div class="orb absolute -bottom-28 left-1/3 -z-10 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"></div>

            <div class="grid items-center gap-8 lg:grid-cols-[1.15fr_.85fr]">
                <div>
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-emerald-300">
                        <span class="pulse-dot h-2 w-2 rounded-full bg-emerald-400"></span>
                        System Online
                    </div>

                    <h1 class="max-w-4xl font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            Social Media Algorithm
                        </span>

                        <br>

                        Intelligence Platform
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Pantau perkembangan akun, evaluasi performa konten, temukan pola traffic, dan kelola strategi publikasi melalui satu dashboard terpadu.
                    </p>

                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="accounts/analytics.php"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-500 to-cyan-500 px-6 font-bold shadow-glow transition hover:-translate-y-1"
                        >
                            <i data-lucide="chart-no-axes-combined" class="h-5 w-5"></i>
                            Open Account Analytics
                        </a>

                        <a
                            href="automation/automation_center.php"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-6 font-bold text-slate-100 transition hover:-translate-y-1 hover:bg-white/10"
                        >
                            <i data-lucide="calendar-clock" class="h-5 w-5"></i>
                            Open Scheduler
                        </a>
                    </div>
                </div>

                <div class="glass float rounded-[1.75rem] p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Monitoring account
                            </p>

                            <h2 class="mt-3 truncate font-display text-2xl font-extrabold sm:text-3xl">
                                <?= $username ?>
                            </h2>

                            <p class="mt-1 text-sm font-semibold text-indigo-300">
                                <?= $platform ?>
                            </p>
                        </div>

                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500/30 to-cyan-500/20 text-cyan-200">
                            <i data-lucide="radio-tower" class="h-6 w-6"></i>
                        </div>
                    </div>

                    <div class="mt-7 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-400">
                                Algorithm health
                            </p>

                            <p class="mt-1 font-display text-5xl font-extrabold text-cyan-300">
                                <?= number_format($algorithmScore, 0) ?><span class="text-2xl">%</span>
                            </p>
                        </div>

                        <span class="rounded-full border px-3 py-1.5 text-xs font-bold <?= e($status['badge']) ?> <?= e($status['text']) ?>">
                            <?= e($status['label']) ?>
                        </span>
                    </div>

                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="progress-shine h-full rounded-full bg-gradient-to-r from-indigo-400 via-cyan-300 to-emerald-300 transition-all duration-1000"
                            style="width: <?= number_format($algorithmScore, 2, '.', '') ?>%"
                        ></div>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-2xl bg-white/[.045] p-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">
                                Growth
                            </p>

                            <p class="mt-1 font-bold text-emerald-300">
                                <?= e($growthPrefix . number_format($growth, 1)) ?>%
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/[.045] p-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">
                                Engagement
                            </p>

                            <p class="mt-1 font-bold text-pink-300">
                                <?= e(number_format($engagementRate, 1)) ?>%
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/[.045] p-3">
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">
                                Status
                            </p>

                            <p class="mt-1 font-bold text-cyan-300">
                                Live
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="kpiTitle">
            <div
                class="mb-5 flex items-end justify-between gap-4"
                data-aos="fade-right"
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                        Account overview
                    </p>

                    <h2
                        id="kpiTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Performance snapshot
                    </h2>
                </div>

                <p class="hidden text-sm text-slate-500 sm:block">
                    Data akun utama
                </p>
            </div>

            <div class="mb-12 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
                <article
                    class="glass card-hover rounded-[1.6rem] p-5 sm:p-6"
                    data-aos="fade-up"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-300">
                            Followers
                        </span>

                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-400/10 text-indigo-300">
                            <i data-lucide="users" class="h-5 w-5"></i>
                        </span>
                    </div>

                    <p class="mt-6 font-display text-3xl font-extrabold sm:text-4xl">
                        <?= e(compactNumber($followers)) ?>
                    </p>

                    <p class="mt-2 text-xs text-slate-500">
                        <?= e(number_format($followers, 0, ',', '.')) ?> total followers
                    </p>
                </article>

                <article
                    class="glass card-hover rounded-[1.6rem] p-5 sm:p-6"
                    data-aos="fade-up"
                    data-aos-delay="80"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-300">
                            Growth
                        </span>

                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-400/10 text-emerald-300">
                            <i data-lucide="trending-up" class="h-5 w-5"></i>
                        </span>
                    </div>

                    <p class="mt-6 font-display text-3xl font-extrabold text-emerald-300 sm:text-4xl">
                        <?= e($growthPrefix . number_format($growth, 1)) ?>%
                    </p>

                    <p class="mt-2 text-xs text-slate-500">
                        Perubahan periode berjalan
                    </p>
                </article>

                <article
                    class="glass card-hover rounded-[1.6rem] p-5 sm:p-6"
                    data-aos="fade-up"
                    data-aos-delay="160"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-300">
                            Engagement
                        </span>

                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-pink-400/10 text-pink-300">
                            <i data-lucide="heart" class="h-5 w-5"></i>
                        </span>
                    </div>

                    <p class="mt-6 font-display text-3xl font-extrabold text-pink-300 sm:text-4xl">
                        <?= e(number_format($engagementRate, 1)) ?>%
                    </p>

                    <p class="mt-2 text-xs text-slate-500">
                        Rasio interaksi akun
                    </p>
                </article>

                <article
                    class="card-hover relative overflow-hidden rounded-[1.6rem] border border-indigo-300/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-600 p-5 shadow-glow sm:p-6"
                    data-aos="fade-up"
                    data-aos-delay="240"
                >
                    <div class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>

                    <div class="relative flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-white/85">
                            Algorithm Score
                        </span>

                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/15 text-white">
                            <i data-lucide="cpu" class="h-5 w-5"></i>
                        </span>
                    </div>

                    <p class="relative mt-6 font-display text-3xl font-extrabold sm:text-4xl">
                        <?= number_format($algorithmScore, 0) ?><span class="text-xl text-white/70">/100</span>
                    </p>

                    <div class="relative mt-4 h-2.5 overflow-hidden rounded-full bg-white/25">
                        <div
                            class="progress-shine h-full rounded-full bg-white"
                            style="width: <?= number_format($algorithmScore, 2, '.', '') ?>%"
                        ></div>
                    </div>
                </article>
            </div>
        </section>

        <section id="analytics" aria-labelledby="moduleTitle">
            <div
                class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                data-aos="fade-right"
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                        Intelligence modules
                    </p>

                    <h2
                        id="moduleTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Analytics & automation center
                    </h2>
                </div>

                <p class="max-w-md text-sm leading-6 text-slate-400">
                    Pilih modul untuk membaca pola, mengevaluasi performa, dan menjalankan strategi konten.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <a
                    href="modules/algorithm_matrix.php"
                    class="module-card glass card-hover group rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                >
                    <span class="module-icon grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                        <i data-lucide="bar-chart-3" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-lg font-extrabold">
                        Algorithm Matrix
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Pantau indikator kesehatan dan perubahan distribusi akun.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-cyan-300">
                        Buka modul

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>

                <a
                    href="modules/account_growth.php"
                    class="module-card glass card-hover group rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="70"
                >
                    <span class="module-icon grid h-12 w-12 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300">
                        <i data-lucide="chart-no-axes-combined" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-lg font-extrabold">
                        Account Growth
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Analisis followers, engagement, reach, dan tren pertumbuhan.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-emerald-300">
                        Buka modul

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>

                <a
                    href="modules/evaluation.php"
                    class="module-card glass card-hover group rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="140"
                >
                    <span class="module-icon grid h-12 w-12 place-items-center rounded-2xl bg-amber-400/10 text-amber-300">
                        <i data-lucide="search-check" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-lg font-extrabold">
                        Evaluation
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Observasi hasil konten dan identifikasi peluang perbaikan.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-amber-300">
                        Buka modul

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>

                <a
                    href="modules/upload_strategy.php"
                    class="module-card glass card-hover group rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="210"
                >
                    <span class="module-icon grid h-12 w-12 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                        <i data-lucide="calendar-clock" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-lg font-extrabold">
                        Upload Strategy
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Rekomendasi jam upload, tema, format, dan frekuensi konten.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-violet-300">
                        Buka modul

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>

                <a
                    href="accounts/analytics.php"
                    class="module-card glass card-hover group rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="280"
                >
                    <span class="module-icon grid h-12 w-12 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300">
                        <i data-lucide="chart-spline" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-lg font-extrabold">
                        Account Analytics
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Lihat ringkasan subscriber, video, views, status koneksi, dan sinkronisasi seluruh akun.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-indigo-300">
                        Buka analytics

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>

                <a
                    href="api_configuration.php"
                    class="module-card glass card-hover group rounded-[1.65rem] p-6"
                    data-aos="fade-up"
                    data-aos-delay="350"
                >
                    <span class="module-icon grid h-12 w-12 place-items-center rounded-2xl bg-red-400/10 text-red-300">
                        <i data-lucide="plug-zap" class="h-6 w-6"></i>
                    </span>

                    <h3 class="mt-6 font-display text-lg font-extrabold">
                        API Configuration
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        Kelola Google OAuth, YouTube API, redirect URI, dan status koneksi akun.
                    </p>

                    <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-red-300">
                        Kelola API

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>

                <a
                    href="automation/automation_center.php"
                    class="module-card card-hover group relative overflow-hidden rounded-[1.65rem] border border-cyan-300/20 bg-gradient-to-br from-cyan-600 via-blue-600 to-indigo-700 p-6 shadow-cyanGlow"
                    data-aos="fade-up"
                    data-aos-delay="420"
                >
                    <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/15 blur-2xl"></div>

                    <span class="module-icon relative grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white">
                        <i data-lucide="workflow" class="h-6 w-6"></i>
                    </span>

                    <h3 class="relative mt-6 font-display text-lg font-extrabold">
                        Automation Center
                    </h3>

                    <p class="relative mt-2 text-sm leading-6 text-white/75">
                        Scheduler, publishing queue, job status, dan device control.
                    </p>

                    <span class="relative mt-5 inline-flex items-center gap-2 text-sm font-bold text-white">
                        Jalankan automation

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 transition group-hover:translate-x-1 group-hover:-translate-y-1"
                        ></i>
                    </span>
                </a>
            </div>
        </section>

        <section class="mt-12 grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
            <article
                class="glass rounded-[1.75rem] p-6 sm:p-8"
                data-aos="fade-right"
            >
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                            System insight
                        </p>

                        <h2 class="mt-2 font-display text-xl font-extrabold sm:text-2xl">
                            Recommended next action
                        </h2>
                    </div>

                    <span class="w-fit rounded-full border border-indigo-300/15 bg-indigo-400/10 px-3 py-1.5 text-xs font-bold text-indigo-200">
                        AI Recommendation
                    </span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <i data-lucide="clock-3" class="h-5 w-5 text-cyan-300"></i>

                        <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">
                            Upload window
                        </p>

                        <p class="mt-1 font-bold text-white">
                            18:30–20:00
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <i data-lucide="sparkles" class="h-5 w-5 text-violet-300"></i>

                        <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">
                            Theme focus
                        </p>

                        <p class="mt-1 font-bold text-white">
                            High-retention content
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <i data-lucide="flask-conical" class="h-5 w-5 text-amber-300"></i>

                        <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">
                            Next experiment
                        </p>

                        <p class="mt-1 font-bold text-white">
                            Hook A/B testing
                        </p>
                    </div>
                </div>

                <p class="mt-5 text-xs leading-5 text-slate-500">
                    Data rekomendasi di atas masih placeholder tampilan. Hubungkan dengan hasil perhitungan database agar nilainya dinamis.
                </p>
            </article>

            <article
                class="glass rounded-[1.75rem] p-6 sm:p-8"
                data-aos="fade-left"
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                            Platform status
                        </p>

                        <h2 class="mt-2 font-display text-xl font-extrabold">
                            Connector health
                        </h2>
                    </div>

                    <i data-lucide="plug-zap" class="h-6 w-6 text-cyan-300"></i>
                </div>

                <?php
                $youtubeConnectorStatus = 'Not configured';
                $youtubeConnectorDot = 'bg-rose-400';

                if ($googleClientReady && $connectedYouTubeAccounts > 0) {
                    $youtubeConnectorStatus =
                        $connectedYouTubeAccounts . ' channel connected';
                    $youtubeConnectorDot = 'bg-emerald-400';
                } elseif ($googleClientReady) {
                    $youtubeConnectorStatus = 'OAuth ready';
                    $youtubeConnectorDot = 'bg-cyan-400';
                }

                $connectors = [
                    ['Meta API', 'Not configured', 'bg-slate-500'],
                    ['TikTok API', 'Not configured', 'bg-slate-500'],
                    ['YouTube API', $youtubeConnectorStatus, $youtubeConnectorDot],
                ];
                ?>

                <div class="mt-6 grid gap-3">
                    <?php foreach ($connectors as [$name, $connectorStatus, $dotClass]): ?>
                        <div class="flex items-center justify-between rounded-2xl bg-white/[.035] px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full <?= e($dotClass) ?>"></span>

                                <span class="text-sm font-semibold text-slate-200">
                                    <?= e($name) ?>
                                </span>
                            </div>

                            <span class="text-xs font-medium text-slate-500">
                                <?= e($connectorStatus) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>
                © <?= date('Y') ?> Social Media Intelligence Platform.
            </p>

            <p>
                Monitoring • Analytics • Evaluation • Automation
            </p>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        if (window.AOS) {
            AOS.init({
                duration: 760,
                easing: 'ease-out-cubic',
                once: true,
                offset: 40,
                disable: window.matchMedia(
                    '(prefers-reduced-motion: reduce)'
                ).matches
            });
        }

        if (window.lucide) {
            lucide.createIcons();
        }

        const openMenuButton = document.getElementById('openMenu');
        const closeMenuButton = document.getElementById('closeMenu');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function setMobileMenu(open) {
            if (
                !openMenuButton ||
                !mobileMenu ||
                !mobileOverlay
            ) {
                return;
            }

            mobileMenu.classList.toggle('translate-x-full', !open);
            mobileOverlay.classList.toggle('hidden', !open);
            document.body.classList.toggle('overflow-hidden', open);
            openMenuButton.setAttribute(
                'aria-expanded',
                open ? 'true' : 'false'
            );
        }

        openMenuButton?.addEventListener(
            'click',
            () => setMobileMenu(true)
        );

        closeMenuButton?.addEventListener(
            'click',
            () => setMobileMenu(false)
        );

        mobileOverlay?.addEventListener(
            'click',
            () => setMobileMenu(false)
        );

        mobileMenu
            ?.querySelectorAll('a')
            .forEach((link) => {
                link.addEventListener(
                    'click',
                    () => setMobileMenu(false)
                );
            });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMobileMenu(false);
            }
        });
    </script>
</body>
</html>