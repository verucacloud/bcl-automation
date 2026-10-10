<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function compactNumber(mixed $value): string
{
    $number = is_numeric($value) ? (float) $value : 0;

    if ($number >= 1_000_000_000) {
        return rtrim(rtrim(number_format($number / 1_000_000_000, 1), '0'), '.') . 'B';
    }

    if ($number >= 1_000_000) {
        return rtrim(rtrim(number_format($number / 1_000_000, 1), '0'), '.') . 'M';
    }

    if ($number >= 1_000) {
        return rtrim(rtrim(number_format($number / 1_000, 1), '0'), '.') . 'K';
    }

    return number_format($number, 0, ',', '.');
}

function percentageChange(float $current, float $previous): float
{
    if ($previous == 0.0) {
        return $current > 0 ? 100 : 0;
    }

    return (($current - $previous) / $previous) * 100;
}

function trendMeta(float $change): array
{
    if ($change > 0) {
        return [
            'label' => '+' . number_format($change, 1) . '%',
            'text' => 'text-emerald-300',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10',
            'icon' => 'trending-up',
        ];
    }

    if ($change < 0) {
        return [
            'label' => number_format($change, 1) . '%',
            'text' => 'text-rose-300',
            'badge' => 'border-rose-400/20 bg-rose-400/10',
            'icon' => 'trending-down',
        ];
    }

    return [
        'label' => '0.0%',
        'text' => 'text-slate-300',
        'badge' => 'border-white/10 bg-white/5',
        'icon' => 'minus',
    ];
}

$query = $conn->query(
    'SELECT * FROM account_growth ORDER BY date ASC'
);

if (!$query) {
    http_response_code(500);
    exit('Database Error: ' . e($conn->error));
}

$rows = [];
$dates = [];
$followers = [];
$reach = [];
$impressions = [];

while ($row = $query->fetch_assoc()) {
    $row['followers'] = is_numeric($row['followers'] ?? null) ? (float) $row['followers'] : 0;
    $row['reach'] = is_numeric($row['reach'] ?? null) ? (float) $row['reach'] : 0;
    $row['impression'] = is_numeric($row['impression'] ?? null) ? (float) $row['impression'] : 0;

    $rows[] = $row;
    $dates[] = (string) ($row['date'] ?? '');
    $followers[] = $row['followers'];
    $reach[] = $row['reach'];
    $impressions[] = $row['impression'];
}

$totalRows = count($rows);

$currentFollowers = $totalRows > 0 ? (float) $rows[$totalRows - 1]['followers'] : 0;
$currentReach = $totalRows > 0 ? (float) $rows[$totalRows - 1]['reach'] : 0;
$currentImpression = $totalRows > 0 ? (float) $rows[$totalRows - 1]['impression'] : 0;

$previousFollowers = $totalRows > 1 ? (float) $rows[$totalRows - 2]['followers'] : 0;
$previousReach = $totalRows > 1 ? (float) $rows[$totalRows - 2]['reach'] : 0;
$previousImpression = $totalRows > 1 ? (float) $rows[$totalRows - 2]['impression'] : 0;

$followersChange = percentageChange($currentFollowers, $previousFollowers);
$reachChange = percentageChange($currentReach, $previousReach);
$impressionChange = percentageChange($currentImpression, $previousImpression);

$followersTrend = trendMeta($followersChange);
$reachTrend = trendMeta($reachChange);
$impressionTrend = trendMeta($impressionChange);

$periodStart = $totalRows > 0 ? (string) $rows[0]['date'] : '-';
$periodEnd = $totalRows > 0 ? (string) $rows[$totalRows - 1]['date'] : '-';

$tableRows = array_reverse($rows);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Account Growth Monitoring</title>

    <meta
        name="description"
        content="Dashboard pemantauan perkembangan followers, reach, impression, dan histori performa akun media sosial."
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

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
                    Account Growth
                </p>

                <p class="text-[11px] text-slate-500 sm:text-xs">
                    Social Media Intelligence
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
                        Historical Growth Monitoring
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            Account Growth
                        </span>

                        <br>

                        Monitoring
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Analisis perkembangan followers, reach, dan impression berdasarkan histori performa akun.
                    </p>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-7">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Monitoring period
                            </p>

                            <p class="mt-3 font-display text-xl font-extrabold sm:text-2xl">
                                <?= e($periodStart) ?>
                                <span class="text-slate-500">—</span>
                                <?= e($periodEnd) ?>
                            </p>
                        </div>

                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300">
                            <i data-lucide="calendar-range" class="h-6 w-6"></i>
                        </span>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Data points
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold">
                                <?= number_format($totalRows, 0, ',', '.') ?>
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Latest update
                            </p>

                            <p class="mt-2 truncate font-display text-base font-extrabold text-cyan-200">
                                <?= e($periodEnd) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($totalRows === 0): ?>
            <section
                class="glass rounded-[1.75rem] p-8 text-center sm:p-12"
                data-aos="fade-up"
            >
                <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white/5 text-slate-400">
                    <i data-lucide="database-zap" class="h-8 w-8"></i>
                </span>

                <h2 class="mt-6 font-display text-2xl font-extrabold">
                    Data pertumbuhan belum tersedia
                </h2>

                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-slate-400">
                    Tambahkan data ke tabel
                    <code class="rounded bg-white/5 px-2 py-1 text-cyan-300">account_growth</code>
                    untuk menampilkan KPI, grafik, dan histori pertumbuhan akun.
                </p>
            </section>
        <?php else: ?>
            <section aria-labelledby="overviewTitle">
                <div
                    class="mb-5 flex items-end justify-between gap-4"
                    data-aos="fade-right"
                >
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                            Current performance
                        </p>

                        <h2
                            id="overviewTitle"
                            class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                        >
                            Latest account snapshot
                        </h2>
                    </div>

                    <p class="hidden text-sm text-slate-500 sm:block">
                        Dibandingkan data sebelumnya
                    </p>
                </div>

                <div class="mb-8 grid gap-5 sm:grid-cols-3">
                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-300">
                                    Followers
                                </p>

                                <p class="mt-5 font-display text-4xl font-extrabold text-cyan-300">
                                    <?= e(compactNumber($currentFollowers)) ?>
                                </p>

                                <p class="mt-2 text-xs text-slate-500">
                                    <?= e(number_format($currentFollowers, 0, ',', '.')) ?> total
                                </p>
                            </div>

                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                                <i data-lucide="users" class="h-6 w-6"></i>
                            </span>
                        </div>

                        <div class="mt-6 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($followersTrend['badge']) ?> <?= e($followersTrend['text']) ?>">
                            <i data-lucide="<?= e($followersTrend['icon']) ?>" class="h-4 w-4"></i>
                            <?= e($followersTrend['label']) ?>
                        </div>
                    </article>

                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                        data-aos-delay="80"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-300">
                                    Reach
                                </p>

                                <p class="mt-5 font-display text-4xl font-extrabold text-emerald-300">
                                    <?= e(compactNumber($currentReach)) ?>
                                </p>

                                <p class="mt-2 text-xs text-slate-500">
                                    <?= e(number_format($currentReach, 0, ',', '.')) ?> total
                                </p>
                            </div>

                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300">
                                <i data-lucide="radio-tower" class="h-6 w-6"></i>
                            </span>
                        </div>

                        <div class="mt-6 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($reachTrend['badge']) ?> <?= e($reachTrend['text']) ?>">
                            <i data-lucide="<?= e($reachTrend['icon']) ?>" class="h-4 w-4"></i>
                            <?= e($reachTrend['label']) ?>
                        </div>
                    </article>

                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                        data-aos-delay="160"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-300">
                                    Impression
                                </p>

                                <p class="mt-5 font-display text-4xl font-extrabold text-violet-300">
                                    <?= e(compactNumber($currentImpression)) ?>
                                </p>

                                <p class="mt-2 text-xs text-slate-500">
                                    <?= e(number_format($currentImpression, 0, ',', '.')) ?> total
                                </p>
                            </div>

                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                                <i data-lucide="eye" class="h-6 w-6"></i>
                            </span>
                        </div>

                        <div class="mt-6 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($impressionTrend['badge']) ?> <?= e($impressionTrend['text']) ?>">
                            <i data-lucide="<?= e($impressionTrend['icon']) ?>" class="h-4 w-4"></i>
                            <?= e($impressionTrend['label']) ?>
                        </div>
                    </article>
                </div>
            </section>

            <section
                class="glass mb-8 rounded-[1.75rem] p-5 sm:p-8"
                data-aos="zoom-in"
                aria-labelledby="chartTitle"
            >
                <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                            Growth analytics
                        </p>

                        <h2
                            id="chartTitle"
                            class="mt-2 font-display text-xl font-extrabold sm:text-2xl"
                        >
                            Historical performance chart
                        </h2>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full border border-cyan-400/15 bg-cyan-400/10 px-3 py-1.5 text-cyan-200">
                            Followers
                        </span>

                        <span class="rounded-full border border-emerald-400/15 bg-emerald-400/10 px-3 py-1.5 text-emerald-200">
                            Reach
                        </span>

                        <span class="rounded-full border border-violet-400/15 bg-violet-400/10 px-3 py-1.5 text-violet-200">
                            Impression
                        </span>
                    </div>
                </div>

                <div class="relative h-[320px] sm:h-[420px]">
                    <canvas id="growthChart"></canvas>
                </div>
            </section>

            <section
                class="glass rounded-[1.75rem] p-5 sm:p-8"
                data-aos="fade-up"
                aria-labelledby="historyTitle"
            >
                <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                            Historical records
                        </p>

                        <h2
                            id="historyTitle"
                            class="mt-2 font-display text-xl font-extrabold sm:text-2xl"
                        >
                            Account growth data
                        </h2>
                    </div>

                    <span class="inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-400">
                        <i data-lucide="arrow-down-wide-narrow" class="h-4 w-4"></i>
                        Newest first
                    </span>
                </div>

                <div class="table-scroll overflow-x-auto">
                    <table class="min-w-[760px] w-full text-left">
                        <thead>
                            <tr class="border-b border-white/10 text-xs uppercase tracking-[.15em] text-slate-500">
                                <th class="px-4 py-4 font-semibold">Date</th>
                                <th class="px-4 py-4 font-semibold">Followers</th>
                                <th class="px-4 py-4 font-semibold">Reach</th>
                                <th class="px-4 py-4 font-semibold">Impression</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($tableRows as $index => $row): ?>
                                <?php
                                $nextRow = $tableRows[$index + 1] ?? null;

                                $rowFollowersChange = $nextRow
                                    ? percentageChange((float) $row['followers'], (float) $nextRow['followers'])
                                    : 0;

                                $rowReachChange = $nextRow
                                    ? percentageChange((float) $row['reach'], (float) $nextRow['reach'])
                                    : 0;

                                $rowImpressionChange = $nextRow
                                    ? percentageChange((float) $row['impression'], (float) $nextRow['impression'])
                                    : 0;
                                ?>

                                <tr class="border-b border-white/[.07] transition hover:bg-white/[.04]">
                                    <td class="whitespace-nowrap px-4 py-5">
                                        <span class="inline-flex items-center gap-2 font-semibold text-slate-200">
                                            <i data-lucide="calendar-days" class="h-4 w-4 text-slate-500"></i>
                                            <?= e($row['date'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td class="px-4 py-5">
                                        <p class="font-bold text-cyan-200">
                                            <?= e(number_format((float) $row['followers'], 0, ',', '.')) ?>
                                        </p>

                                        <p class="mt-1 text-xs <?= $rowFollowersChange >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                            <?= $rowFollowersChange > 0 ? '+' : '' ?><?= e(number_format($rowFollowersChange, 1)) ?>%
                                        </p>
                                    </td>

                                    <td class="px-4 py-5">
                                        <p class="font-bold text-emerald-200">
                                            <?= e(number_format((float) $row['reach'], 0, ',', '.')) ?>
                                        </p>

                                        <p class="mt-1 text-xs <?= $rowReachChange >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                            <?= $rowReachChange > 0 ? '+' : '' ?><?= e(number_format($rowReachChange, 1)) ?>%
                                        </p>
                                    </td>

                                    <td class="px-4 py-5">
                                        <p class="font-bold text-violet-200">
                                            <?= e(number_format((float) $row['impression'], 0, ',', '.')) ?>
                                        </p>

                                        <p class="mt-1 text-xs <?= $rowImpressionChange >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                            <?= $rowImpressionChange > 0 ? '+' : '' ?><?= e(number_format($rowImpressionChange, 1)) ?>%
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Followers • Reach • Impression • Historical Growth</p>
        </div>
    </footer>

    <?php if ($totalRows > 0): ?>
        <script>
            const chartLabels = <?= json_encode(
                $dates,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ) ?>;

            const followersData = <?= json_encode($followers, JSON_NUMERIC_CHECK) ?>;
            const reachData = <?= json_encode($reach, JSON_NUMERIC_CHECK) ?>;
            const impressionData = <?= json_encode($impressions, JSON_NUMERIC_CHECK) ?>;

            const canvas = document.getElementById('growthChart');
            const context = canvas.getContext('2d');

            const followersGradient = context.createLinearGradient(0, 0, 0, 420);
            followersGradient.addColorStop(0, 'rgba(34, 211, 238, .35)');
            followersGradient.addColorStop(1, 'rgba(34, 211, 238, 0)');

            const chart = new Chart(context, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [
                        {
                            label: 'Followers',
                            data: followersData,
                            borderColor: '#22d3ee',
                            backgroundColor: followersGradient,
                            fill: true,
                            borderWidth: 3,
                            tension: .38,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#22d3ee',
                            pointBorderColor: '#020617',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Reach',
                            data: reachData,
                            borderColor: '#34d399',
                            backgroundColor: 'rgba(52, 211, 153, .08)',
                            fill: false,
                            borderWidth: 3,
                            tension: .38,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#34d399',
                            pointBorderColor: '#020617',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Impression',
                            data: impressionData,
                            borderColor: '#a78bfa',
                            backgroundColor: 'rgba(167, 139, 250, .08)',
                            fill: false,
                            borderWidth: 3,
                            tension: .38,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#a78bfa',
                            pointBorderColor: '#020617',
                            pointBorderWidth: 2
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
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(2, 6, 23, .94)',
                            borderColor: 'rgba(255, 255, 255, .12)',
                            borderWidth: 1,
                            padding: 12,
                            titleColor: '#ffffff',
                            bodyColor: '#cbd5e1',
                            displayColors: true,
                            callbacks: {
                                label(context) {
                                    const value = Number(context.raw || 0);
                                    return `${context.dataset.label}: ${new Intl.NumberFormat('id-ID').format(value)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, .045)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#94a3b8',
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 8
                            }
                        },
                        y: {
                            beginAtZero: false,
                            grid: {
                                color: 'rgba(255, 255, 255, .055)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#94a3b8',
                                callback(value) {
                                    return new Intl.NumberFormat('id-ID', {
                                        notation: 'compact',
                                        maximumFractionDigits: 1
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>

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