<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konfigurasi error
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/*
|--------------------------------------------------------------------------
| Session opsional
|--------------------------------------------------------------------------
| Halaman masih bisa dibuka walaupun sistem login operator belum selesai.
*/


require_once dirname(__DIR__) . '/config.php';

$sessionFile = dirname(__DIR__) . '/auth/session.php';

if (is_file($sessionFile)) {
    require_once $sessionFile;
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Data pengguna sementara
|--------------------------------------------------------------------------
*/

$isLoggedIn = isset($_SESSION['user_id'])
    && is_numeric($_SESSION['user_id'])
    && (int) $_SESSION['user_id'] > 0;

$userName = trim(
    (string) ($_SESSION['user_name'] ?? 'Operator')
);

/*
|--------------------------------------------------------------------------
| Daftar platform
|--------------------------------------------------------------------------
| URL connector masih placeholder.
*/

$platforms = [
    [
        'name' => 'Instagram',
        'slug' => 'instagram',
        'description' =>
            'Hubungkan akun Instagram profesional untuk monitoring performa konten, engagement, reach, dan pertumbuhan akun.',
        'status' => 'Available soon',
        'icon' => 'instagram',
        'url' => '#instagram',
        'button' => 'Hubungkan Instagram',
        'gradient' =>
            'from-fuchsia-500 via-pink-500 to-orange-400',
        'background' => 'bg-pink-400/10',
        'border' => 'border-pink-400/20',
        'text' => 'text-pink-300',
        'enabled' => false,
    ],
    [
        'name' => 'TikTok',
        'slug' => 'tiktok',
        'description' =>
            'Hubungkan akun TikTok untuk membaca profil, perkembangan audiens, video, interaksi, dan pola performa konten.',
        'status' => 'Available soon',
        'icon' => 'music-2',
        'url' => '#tiktok',
        'button' => 'Hubungkan TikTok',
        'gradient' =>
            'from-slate-100 via-cyan-300 to-pink-400',
        'background' => 'bg-cyan-400/10',
        'border' => 'border-cyan-400/20',
        'text' => 'text-cyan-300',
        'enabled' => false,
    ],
    [
        'name' => 'YouTube',
        'slug' => 'youtube',
        'description' =>
            'Hubungkan channel YouTube untuk monitoring subscriber, views, video, engagement, dan perkembangan channel.',
        'status' => 'Ready for setup',
        'icon' => 'youtube',
        'url' => '../connectors/youtube/connect.php',
        'button' => 'Hubungkan YouTube',
        'gradient' =>
            'from-red-600 via-red-500 to-orange-400',
        'background' => 'bg-red-400/10',
        'border' => 'border-red-400/20',
        'text' => 'text-red-300',
        'enabled' => true,
    ],
];

/*
|--------------------------------------------------------------------------
| Contoh akun terhubung
|--------------------------------------------------------------------------
| Nantinya data ini diganti dengan query tabel social_accounts.
*/

$connectedAccounts = [];
$accountResult = $conn->query(
    "SELECT id, platform, username, display_name, avatar_url, status, last_sync_at FROM social_accounts ORDER BY id DESC"
);

if (!$accountResult) {
    error_log('Query social_accounts gagal: ' . $conn->error);
}
if ($accountResult) {
    while ($row = $accountResult->fetch_assoc()) {
        $row['last_sync'] = !empty($row['last_sync_at'])
            ? date('d M Y, H:i', strtotime((string)$row['last_sync_at']))
            : '-';
        $connectedAccounts[] = $row;
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

    <meta name="theme-color" content="#020617">

    <title>Hubungkan Akun - Social Intelligence</title>

    <meta
        name="description"
        content="Hubungkan akun Instagram, TikTok, dan YouTube ke sistem monitoring Social Intelligence."
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
                        sans: ['Inter', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'sans-serif']
                    },
                    boxShadow: {
                        glow: '0 0 45px rgba(99, 102, 241, .24)',
                        cyanGlow: '0 0 45px rgba(34, 211, 238, .16)'
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
                    circle at 8% 5%,
                    rgba(99, 102, 241, .25),
                    transparent 28rem
                ),
                radial-gradient(
                    circle at 92% 85%,
                    rgba(6, 182, 212, .17),
                    transparent 30rem
                ),
                radial-gradient(
                    circle at 65% 5%,
                    rgba(168, 85, 247, .10),
                    transparent 24rem
                ),
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
                linear-gradient(
                    rgba(255, 255, 255, .04) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(255, 255, 255, .04) 1px,
                    transparent 1px
                );
            background-size: 42px 42px;
            mask-image:
                linear-gradient(to bottom, black, transparent 90%);
        }

        .glass {
            background:
                linear-gradient(
                    145deg,
                    rgba(255, 255, 255, .09),
                    rgba(255, 255, 255, .035)
                );
            border: 1px solid rgba(255, 255, 255, .10);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .05),
                0 24px 60px rgba(0, 0, 0, .16);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .platform-card {
            transition:
                transform .3s ease,
                border-color .3s ease,
                box-shadow .3s ease,
                background .3s ease;
        }

        .platform-card:hover {
            transform: translateY(-7px);
            border-color: rgba(129, 140, 248, .40);
            box-shadow:
                0 28px 65px rgba(2, 6, 23, .45),
                0 0 35px rgba(99, 102, 241, .10);
        }

        .platform-card:hover .platform-icon {
            transform: scale(1.08) rotate(-4deg);
        }

        .platform-icon {
            transition: transform .3s ease;
        }

        .step-line::after {
            content: "";
            position: absolute;
            top: 1.5rem;
            left: calc(50% + 2.5rem);
            width: calc(100% - 5rem);
            height: 1px;
            background:
                linear-gradient(
                    90deg,
                    rgba(99, 102, 241, .6),
                    rgba(34, 211, 238, .25)
                );
        }

        @media (max-width: 767px) {
            .step-line::after {
                display: none;
            }
        }
    </style>
</head>

<body class="font-sans text-white antialiased">
    <!-- Mobile overlay -->
    <div
        id="mobileOverlay"
        class="fixed inset-0 z-40 hidden bg-slate-950/75 backdrop-blur-sm lg:hidden"
    ></div>

    <!-- Mobile menu -->
    <aside
        id="mobileMenu"
        class="fixed right-0 top-0 z-50 flex h-full w-[86%] max-w-sm translate-x-full flex-col border-l border-white/10 bg-slate-950/95 p-6 shadow-2xl backdrop-blur-2xl transition-transform duration-300 lg:hidden"
        aria-label="Navigasi mobile"
    >
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-lg font-extrabold">
                    Social Intelligence
                </p>

                <p class="mt-1 text-xs text-slate-400">
                    Account Connector
                </p>
            </div>

            <button
                id="closeMenu"
                type="button"
                class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-white/5"
                aria-label="Tutup menu"
            >
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <nav class="mt-10 grid gap-3">
            <a
                href="../dashboard.php"
                class="rounded-2xl bg-white/5 px-4 py-3 font-semibold text-slate-200"
            >
                Dashboard
            </a>

            <a
                href="analytics.php"
                class="rounded-2xl bg-cyan-400/10 px-4 py-3 font-semibold text-cyan-200"
            >
                Analytics
            </a>

            <a
                href="#platforms"
                class="rounded-2xl bg-indigo-500/10 px-4 py-3 font-semibold text-indigo-200"
            >
                Hubungkan Akun
            </a>

            <a
                href="#connectedAccounts"
                class="rounded-2xl bg-white/5 px-4 py-3 font-semibold text-slate-200"
            >
                Akun Terhubung
            </a>
        </nav>

        <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs uppercase tracking-[.18em] text-slate-500">
                Operator
            </p>

            <p class="mt-2 truncate font-bold text-white">
                <?= e($userName) ?>
            </p>

            <p class="mt-1 text-xs text-slate-500">
                <?= $isLoggedIn ? 'Session aktif' : 'Mode tampilan' ?>
            </p>
        </div>
    </aside>

    <!-- Header -->
    <header
        class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/55 backdrop-blur-xl"
    >
        <div
            class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"
        >
            <a
                href="../dashboard.php"
                class="flex min-w-0 items-center gap-3"
            >
                <div
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-cyan-500 shadow-glow"
                >
                    <i
                        data-lucide="brain-circuit"
                        class="h-6 w-6"
                    ></i>
                </div>

                <div class="min-w-0">
                    <p
                        class="truncate font-display text-sm font-extrabold sm:text-base"
                    >
                        Social Intelligence
                    </p>

                    <p
                        class="truncate text-[11px] text-slate-400 sm:text-xs"
                    >
                        Monitoring • Analytics • Automation
                    </p>
                </div>
            </a>

            <nav
                class="hidden items-center gap-3 text-sm font-semibold lg:flex"
            >
                <a
                    href="../dashboard.php"
                    class="rounded-xl px-4 py-2.5 text-slate-300 transition hover:bg-white/5 hover:text-white"
                >
                    Dashboard
                </a>

                <a
                    href="analytics.php"
                    class="rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-2.5 font-bold text-cyan-200 transition hover:-translate-y-0.5 hover:bg-cyan-400/20 hover:text-white"
                >
                    Analytics
                </a>

                <a
                    href="#connectedAccounts"
                    class="rounded-xl px-4 py-2.5 text-slate-300 transition hover:bg-white/5 hover:text-white"
                >
                    Akun Terhubung
                </a>

                <a
                    href="#platforms"
                    class="rounded-xl bg-gradient-to-r from-indigo-500 to-cyan-500 px-4 py-2.5 font-bold text-white shadow-glow transition hover:-translate-y-0.5"
                >
                    + Tambah Akun
                </a>
            </nav>

            <button
                id="openMenu"
                type="button"
                class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-white/5 lg:hidden"
                aria-label="Buka menu"
                aria-expanded="false"
            >
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
        </div>
    </header>

    <main
        class="mx-auto max-w-7xl px-4 pb-20 pt-8 sm:px-6 sm:pt-12 lg:px-8"
    >
        <!-- Breadcrumb -->
        <nav
            class="mb-6 flex items-center gap-2 text-xs text-slate-500"
            aria-label="Breadcrumb"
        >
            <a
                href="../dashboard.php"
                class="transition hover:text-white"
            >
                Dashboard
            </a>

            <i
                data-lucide="chevron-right"
                class="h-3.5 w-3.5"
            ></i>

            <span class="text-indigo-300">
                Hubungkan Akun
            </span>
        </nav>

        <!-- Hero -->
        <section
            class="relative overflow-hidden rounded-[2rem] border border-white/10 px-5 py-8 sm:px-8 sm:py-10 lg:px-11 lg:py-12"
        >
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-br from-indigo-600/25 via-slate-950/30 to-cyan-500/15"
            ></div>

            <div
                class="absolute -right-24 -top-24 -z-10 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"
            ></div>

            <div
                class="absolute -bottom-28 left-1/3 -z-10 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"
            ></div>

            <div
                class="grid items-center gap-8 lg:grid-cols-[1.15fr_.85fr]"
            >
                <div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-cyan-300"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-cyan-400"
                        ></span>

                        Social Account Connector
                    </div>

                    <h1
                        class="mt-5 max-w-3xl font-display text-3xl font-extrabold leading-tight sm:text-5xl"
                    >
                        Hubungkan akun yang akan
                        <span
                            class="bg-gradient-to-r from-indigo-300 via-cyan-300 to-emerald-300 bg-clip-text text-transparent"
                        >
                            dimonitor
                        </span>
                    </h1>

                    <p
                        class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base"
                    >
                        Pilih platform, masuk melalui halaman resmi,
                        setujui izin akses, lalu akun akan ditambahkan
                        ke sistem monitoring.
                    </p>

                    <div
                        class="mt-7 flex flex-wrap gap-3 text-xs font-semibold text-slate-300"
                    >
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2"
                        >
                            <i
                                data-lucide="shield-check"
                                class="h-4 w-4 text-emerald-300"
                            ></i>

                            Tanpa menyimpan password
                        </span>

                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2"
                        >
                            <i
                                data-lucide="key-round"
                                class="h-4 w-4 text-indigo-300"
                            ></i>

                            OAuth resmi
                        </span>

                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2"
                        >
                            <i
                                data-lucide="layers-3"
                                class="h-4 w-4 text-cyan-300"
                            ></i>

                            Mendukung multiakun
                        </span>
                    </div>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <div
                            class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300"
                        >
                            <i
                                data-lucide="workflow"
                                class="h-6 w-6"
                            ></i>
                        </div>

                        <div>
                            <p class="font-display text-lg font-extrabold">
                                Alur koneksi akun
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-slate-400"
                            >
                                Sistem akan mengarahkan pengguna ke
                                halaman resmi platform.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3">
                        <?php
                        $flowItems = [
                            ['1', 'Pilih platform'],
                            ['2', 'Login di platform resmi'],
                            ['3', 'Setujui izin akses'],
                            ['4', 'Akun tersimpan'],
                            ['5', 'Monitoring dijalankan'],
                        ];
                        ?>

                        <?php foreach ($flowItems as [$number, $label]): ?>
                            <div
                                class="flex items-center gap-3 rounded-2xl bg-white/[.045] px-4 py-3"
                            >
                                <span
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-cyan-500 text-xs font-extrabold"
                                >
                                    <?= e($number) ?>
                                </span>

                                <span
                                    class="text-sm font-semibold text-slate-200"
                                >
                                    <?= e($label) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Process steps -->
        <section class="mt-10">
            <div class="mb-6">
                <p
                    class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300"
                >
                    Connection process
                </p>

                <h2
                    class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                >
                    Cara menghubungkan akun
                </h2>
            </div>

            <div
                class="glass grid gap-5 rounded-[1.75rem] p-5 md:grid-cols-4 md:p-7"
            >
                <?php
                $steps = [
                    [
                        'number' => '01',
                        'title' => 'Pilih platform',
                        'description' =>
                            'Pilih Instagram, TikTok, atau YouTube.',
                        'icon' => 'layout-grid',
                    ],
                    [
                        'number' => '02',
                        'title' => 'Masuk resmi',
                        'description' =>
                            'Login langsung melalui halaman platform.',
                        'icon' => 'log-in',
                    ],
                    [
                        'number' => '03',
                        'title' => 'Berikan izin',
                        'description' =>
                            'Setujui akses data yang diperlukan.',
                        'icon' => 'badge-check',
                    ],
                    [
                        'number' => '04',
                        'title' => 'Mulai monitoring',
                        'description' =>
                            'Akun tampil dan siap disinkronkan.',
                        'icon' => 'activity',
                    ],
                ];
                ?>

                <?php foreach ($steps as $index => $step): ?>
                    <article
                        class="step-line relative rounded-2xl border border-white/10 bg-white/[.035] p-5"
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <span
                                class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300"
                            >
                                <i
                                    data-lucide="<?= e($step['icon']) ?>"
                                    class="h-5 w-5"
                                ></i>
                            </span>

                            <span
                                class="font-display text-sm font-extrabold text-slate-600"
                            >
                                <?= e($step['number']) ?>
                            </span>
                        </div>

                        <h3 class="mt-5 font-display font-extrabold">
                            <?= e($step['title']) ?>
                        </h3>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-400"
                        >
                            <?= e($step['description']) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Platform selector -->
        <section id="platforms" class="scroll-mt-28 pt-12">
            <div
                class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300"
                    >
                        Available connectors
                    </p>

                    <h2
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Pilih platform
                    </h2>
                </div>

                <p
                    class="max-w-md text-sm leading-6 text-slate-400"
                >
                    Setiap platform menggunakan izin dan API yang berbeda.
                </p>
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <?php foreach ($platforms as $platform): ?>
                    <article
                        id="<?= e($platform['slug']) ?>"
                        class="platform-card glass group flex min-h-[390px] flex-col rounded-[1.75rem] p-6 sm:p-7"
                    >
                        <div
                            class="flex items-start justify-between gap-4"
                        >
                            <div
                                class="platform-icon grid h-14 w-14 place-items-center rounded-2xl <?= e($platform['background']) ?> <?= e($platform['text']) ?>"
                            >
                                <i
                                    data-lucide="<?= e($platform['icon']) ?>"
                                    class="h-7 w-7"
                                ></i>
                            </div>

                            <span
                                class="rounded-full border <?= e($platform['border']) ?> <?= e($platform['background']) ?> px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider <?= e($platform['text']) ?>"
                            >
                                <?= e($platform['status']) ?>
                            </span>
                        </div>

                        <h3
                            class="mt-7 font-display text-2xl font-extrabold"
                        >
                            <?= e($platform['name']) ?>
                        </h3>

                        <p
                            class="mt-3 text-sm leading-7 text-slate-400"
                        >
                            <?= e($platform['description']) ?>
                        </p>

                        <div class="mt-6 grid gap-3">
                            <div
                                class="flex items-center gap-3 text-sm text-slate-300"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-4 w-4 text-emerald-300"
                                ></i>

                                Profil dan identitas akun
                            </div>

                            <div
                                class="flex items-center gap-3 text-sm text-slate-300"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-4 w-4 text-emerald-300"
                                ></i>

                                Statistik perkembangan
                            </div>

                            <div
                                class="flex items-center gap-3 text-sm text-slate-300"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-4 w-4 text-emerald-300"
                                ></i>

                                Monitoring performa konten
                            </div>
                        </div>

                        <div class="mt-auto pt-7">
                            <?php if ($platform['enabled']): ?>
                                <a
                                    href="<?= e($platform['url']) ?>"
                                    class="flex min-h-13 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r <?= e($platform['gradient']) ?> px-5 py-3.5 font-bold text-white shadow-lg transition hover:-translate-y-0.5"
                                >
                                    <i
                                        data-lucide="link-2"
                                        class="h-5 w-5"
                                    ></i>

                                    <?= e($platform['button']) ?>
                                </a>
                            <?php else: ?>
                                <button
                                    type="button"
                                    disabled
                                    class="flex min-h-13 w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-5 py-3.5 font-bold text-slate-500"
                                >
                                    <i
                                        data-lucide="clock-3"
                                        class="h-5 w-5"
                                    ></i>

                                    Segera tersedia
                                </button>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Connected accounts -->
        <section
            id="connectedAccounts"
            class="scroll-mt-28 pt-12"
        >
            <div
                class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300"
                    >
                        Monitoring accounts
                    </p>

                    <h2
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Akun yang terhubung
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="analytics.php"
                        class="inline-flex items-center gap-2 rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-2 text-xs font-bold text-cyan-200 transition hover:-translate-y-0.5 hover:bg-cyan-400/20"
                    >
                        <i data-lucide="chart-no-axes-combined" class="h-4 w-4"></i>
                        Buka Analytics
                    </a>

                    <span
                        class="w-fit rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300"
                    >
                        <?= count($connectedAccounts) ?> akun
                    </span>
                </div>
            </div>

            <?php if ($connectedAccounts === []): ?>
                <div
                    class="glass rounded-[1.75rem] px-6 py-12 text-center sm:px-10"
                >
                    <div
                        class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300"
                    >
                        <i
                            data-lucide="unplug"
                            class="h-7 w-7"
                        ></i>
                    </div>

                    <h3
                        class="mt-5 font-display text-xl font-extrabold"
                    >
                        Belum ada akun terhubung
                    </h3>

                    <p
                        class="mx-auto mt-3 max-w-lg text-sm leading-7 text-slate-400"
                    >
                        Pilih salah satu platform di atas untuk
                        menambahkan akun pertama ke sistem monitoring.
                    </p>

                    <a
                        href="#platforms"
                        class="mt-7 inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-white px-5 font-bold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200"
                    >
                        <i
                            data-lucide="plus"
                            class="h-5 w-5"
                        ></i>

                        Tambah akun
                    </a>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($connectedAccounts as $account): ?>
                        <article
                            class="glass flex flex-col gap-5 rounded-[1.5rem] p-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-4">
                                <div
                                    class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white/10"
                                >
                                    <?php if (!empty($account['avatar_url'])): ?>
                                        <img
                                            src="<?= e($account['avatar_url']) ?>"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        >
                                    <?php else: ?>
                                        <i
                                            data-lucide="user-round"
                                            class="h-6 w-6 text-slate-400"
                                        ></i>
                                    <?php endif; ?>
                                </div>

                                <div class="min-w-0">
                                    <h3
                                        class="truncate font-display text-lg font-extrabold"
                                    >
                                        <?= e($account['display_name']) ?>
                                    </h3>

                                    <p
                                        class="mt-1 truncate text-sm text-slate-400"
                                    >
                                        <?= e($account['platform']) ?>
                                        •
                                        <?= e($account['username']) ?>
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-3"
                            >
                                <span
                                    class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold text-emerald-300"
                                >
                                    <?= e($account['status']) ?>
                                </span>

                                <?php if (!empty($account['id'])): ?>
                                    <a
                                        href="analytics.php?account_id=<?= (int) $account['id'] ?>"
                                        class="rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-2.5 text-sm font-bold text-cyan-200 transition hover:-translate-y-0.5 hover:bg-cyan-400/20"
                                    >
                                        Analytics
                                    </a>

                                    <a
                                        href="detail.php?id=<?= (int) $account['id'] ?>"
                                        class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:-translate-y-0.5 hover:bg-cyan-200"
                                    >
                                        Buka monitoring
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Security note -->
        <section
            class="mt-12 grid gap-5 lg:grid-cols-[1.15fr_.85fr]"
        >
            <article class="glass rounded-[1.75rem] p-6 sm:p-8">
                <div class="flex items-start gap-4">
                    <div
                        class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300"
                    >
                        <i
                            data-lucide="shield-check"
                            class="h-6 w-6"
                        ></i>
                    </div>

                    <div>
                        <h2
                            class="font-display text-xl font-extrabold"
                        >
                            Koneksi menggunakan OAuth
                        </h2>

                        <p
                            class="mt-3 text-sm leading-7 text-slate-400"
                        >
                            Software tidak meminta atau menyimpan
                            password akun media sosial. Proses login dan
                            persetujuan dilakukan pada halaman resmi
                            platform.
                        </p>
                    </div>
                </div>
            </article>

            <article class="glass rounded-[1.75rem] p-6 sm:p-8">
                <div class="flex items-start gap-4">
                    <div
                        class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-amber-400/10 text-amber-300"
                    >
                        <i
                            data-lucide="info"
                            class="h-6 w-6"
                        ></i>
                    </div>

                    <div>
                        <h2
                            class="font-display text-xl font-extrabold"
                        >
                            Akses dapat dihentikan
                        </h2>

                        <p
                            class="mt-3 text-sm leading-7 text-slate-400"
                        >
                            Pengguna nantinya dapat memutuskan koneksi
                            akun kapan saja melalui menu akun terhubung.
                        </p>
                    </div>
                </div>
            </article>
        </section>
    </main>

    <footer
        class="border-t border-white/5 bg-slate-950/55"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8"
        >
            <p>
                © <?= date('Y') ?> Social Intelligence Platform.
            </p>

            <p>
                Account Connector • Monitoring • Analytics
            </p>
        </div>
    </footer>

    <script>
        if (window.lucide) {
            lucide.createIcons();
        }

        const openMenuButton =
            document.getElementById('openMenu');

        const closeMenuButton =
            document.getElementById('closeMenu');

        const mobileMenu =
            document.getElementById('mobileMenu');

        const mobileOverlay =
            document.getElementById('mobileOverlay');

        function setMobileMenu(open) {
            if (
                !mobileMenu ||
                !mobileOverlay ||
                !openMenuButton
            ) {
                return;
            }

            mobileMenu.classList.toggle(
                'translate-x-full',
                !open
            );

            mobileOverlay.classList.toggle(
                'hidden',
                !open
            );

            document.body.classList.toggle(
                'overflow-hidden',
                open
            );

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

        document.addEventListener(
            'keydown',
            (event) => {
                if (event.key === 'Escape') {
                    setMobileMenu(false);
                }
            }
        );
    </script>
</body>
</html>