<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clampScore(mixed $value): float
{
    $score = is_numeric($value) ? (float) $value : 0;
    return max(0, min(100, $score));
}

function scoreMeta(float $score): array
{
    if ($score >= 85) {
        return [
            'label' => 'Excellent Match',
            'text' => 'text-emerald-300',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10',
            'bar' => 'from-emerald-400 via-cyan-400 to-sky-400',
        ];
    }

    if ($score >= 70) {
        return [
            'label' => 'Strong Match',
            'text' => 'text-cyan-300',
            'badge' => 'border-cyan-400/20 bg-cyan-400/10',
            'bar' => 'from-cyan-400 via-sky-400 to-indigo-500',
        ];
    }

    if ($score >= 50) {
        return [
            'label' => 'Moderate Match',
            'text' => 'text-amber-300',
            'badge' => 'border-amber-400/20 bg-amber-400/10',
            'bar' => 'from-amber-400 via-orange-400 to-rose-400',
        ];
    }

    return [
        'label' => 'Low Match',
        'text' => 'text-rose-300',
        'badge' => 'border-rose-400/20 bg-rose-400/10',
        'bar' => 'from-rose-500 via-pink-500 to-fuchsia-500',
    ];
}

function buildInsight(array $strategies): string
{
    if (count($strategies) === 0) {
        return 'Belum ada data strategi upload. Tambahkan histori performa untuk menghasilkan rekomendasi berbasis data.';
    }

    $top = $strategies[0];
    $topDay = (string) ($top['day'] ?? '-');
    $topTime = (string) ($top['best_time'] ?? '-');
    $topTheme = (string) ($top['theme'] ?? '-');
    $topScore = clampScore($top['performance_score'] ?? 0);

    if ($topScore >= 85) {
        return "Kombinasi terbaik saat ini adalah {$topDay} pukul {$topTime} dengan tema {$topTheme}. Pola ini memiliki kecocokan algoritma yang sangat kuat dan layak diprioritaskan sebagai jadwal utama.";
    }

    if ($topScore >= 70) {
        return "Strategi paling konsisten saat ini adalah {$topDay} pukul {$topTime} dengan tema {$topTheme}. Gunakan sebagai baseline, lalu lakukan A/B testing pada hook, durasi, dan CTA.";
    }

    return "Data terbaik saat ini mengarah ke {$topDay} pukul {$topTime} dengan tema {$topTheme}, tetapi confidence-nya belum tinggi. Perbanyak sampel sebelum menjadikannya aturan permanen.";
}

$query = $conn->query(
    'SELECT * FROM upload_strategy ORDER BY performance_score DESC'
);

if (!$query) {
    http_response_code(500);
    exit('Database Error: ' . e($conn->error));
}

$strategies = [];

while ($row = $query->fetch_assoc()) {
    $row['performance_score'] = clampScore($row['performance_score'] ?? 0);
    $strategies[] = $row;
}

$totalStrategies = count($strategies);

$bestTime = $totalStrategies > 0
    ? (string) ($strategies[0]['best_time'] ?? '-')
    : '-';

$bestTheme = $totalStrategies > 0
    ? (string) ($strategies[0]['theme'] ?? '-')
    : '-';

$bestDay = $totalStrategies > 0
    ? (string) ($strategies[0]['day'] ?? '-')
    : '-';

$bestScore = $totalStrategies > 0
    ? (float) $strategies[0]['performance_score']
    : 0;

$averageScore = $totalStrategies > 0
    ? array_sum(
        array_map(
            static fn(array $item): float => (float) $item['performance_score'],
            $strategies
        )
    ) / $totalStrategies
    : 0;

$confidence = min(99, max(0, round(($bestScore * 0.75) + (min($totalStrategies, 10) * 2.5))));
$insight = buildInsight($strategies);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>AI Upload Strategy</title>

    <meta
        name="description"
        content="Rekomendasi waktu upload, tema, dan kecocokan algoritma berdasarkan histori performa."
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

        .rank-number {
            background:
                linear-gradient(
                    135deg,
                    rgba(99, 102, 241, .95),
                    rgba(34, 211, 238, .85)
                );
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
                    Upload Strategy
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
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-violet-400/20 bg-violet-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-violet-300">
                        <span class="pulse-dot h-2 w-2 rounded-full bg-violet-400"></span>
                        AI Recommendation Engine
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            AI Upload
                        </span>

                        <br>

                        Strategy
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Rekomendasi waktu upload, hari, dan tema berdasarkan performa historis serta kecocokan algoritma.
                    </p>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Top recommendation
                            </p>

                            <p class="mt-3 font-display text-3xl font-extrabold text-cyan-300">
                                <?= e($bestTime) ?>
                            </p>

                            <p class="mt-2 text-sm font-semibold text-violet-300">
                                <?= e($bestDay) ?> • <?= e($bestTheme) ?>
                            </p>
                        </div>

                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500/30 to-cyan-500/20 text-cyan-200">
                            <i data-lucide="calendar-clock" class="h-6 w-6"></i>
                        </span>
                    </div>

                    <div class="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="progress-shine h-full rounded-full bg-gradient-to-r from-indigo-400 via-cyan-300 to-emerald-300"
                            style="width: <?= number_format($bestScore, 2, '.', '') ?>%"
                        ></div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Best score
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold text-emerald-300">
                                <?= number_format($bestScore, 0) ?>%
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/[.04] p-4">
                            <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                Data points
                            </p>

                            <p class="mt-2 font-display text-3xl font-extrabold">
                                <?= number_format($totalStrategies, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($totalStrategies === 0): ?>
            <section
                class="glass rounded-[1.75rem] p-8 text-center sm:p-12"
                data-aos="fade-up"
            >
                <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white/5 text-slate-400">
                    <i data-lucide="database-zap" class="h-8 w-8"></i>
                </span>

                <h2 class="mt-6 font-display text-2xl font-extrabold">
                    Data strategi belum tersedia
                </h2>

                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-slate-400">
                    Tambahkan data ke tabel
                    <code class="rounded bg-white/5 px-2 py-1 text-cyan-300">upload_strategy</code>
                    untuk menampilkan rekomendasi waktu, tema, dan performance score.
                </p>
            </section>
        <?php else: ?>
            <section aria-labelledby="summaryTitle">
                <div
                    class="mb-5 flex items-end justify-between gap-4"
                    data-aos="fade-right"
                >
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                            AI summary
                        </p>

                        <h2
                            id="summaryTitle"
                            class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                        >
                            Recommended publishing setup
                        </h2>
                    </div>

                    <p class="hidden text-sm text-slate-500 sm:block">
                        Berdasarkan data performa tertinggi
                    </p>
                </div>

                <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                    >
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                            <i data-lucide="clock-3" class="h-6 w-6"></i>
                        </span>

                        <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                            Best upload time
                        </p>

                        <p class="mt-2 font-display text-3xl font-extrabold text-cyan-300">
                            <?= e($bestTime) ?>
                        </p>
                    </article>

                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                        data-aos-delay="80"
                    >
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300">
                            <i data-lucide="calendar-days" class="h-6 w-6"></i>
                        </span>

                        <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                            Best day
                        </p>

                        <p class="mt-2 font-display text-3xl font-extrabold text-indigo-200">
                            <?= e($bestDay) ?>
                        </p>
                    </article>

                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                        data-aos-delay="160"
                    >
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                            <i data-lucide="sparkles" class="h-6 w-6"></i>
                        </span>

                        <p class="mt-6 text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                            Best theme
                        </p>

                        <p class="mt-2 line-clamp-2 font-display text-2xl font-extrabold text-violet-200">
                            <?= e($bestTheme) ?>
                        </p>
                    </article>

                    <article
                        class="relative overflow-hidden rounded-[1.65rem] border border-emerald-300/20 bg-gradient-to-br from-emerald-600 via-cyan-600 to-indigo-700 p-6 shadow-cyanGlow"
                        data-aos="fade-up"
                        data-aos-delay="240"
                    >
                        <div class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>

                        <span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-white">
                            <i data-lucide="brain-circuit" class="h-6 w-6"></i>
                        </span>

                        <p class="relative mt-6 text-xs font-bold uppercase tracking-[.16em] text-white/65">
                            AI confidence
                        </p>

                        <p class="relative mt-2 font-display text-3xl font-extrabold">
                            <?= number_format((float) $confidence, 0) ?>%
                        </p>
                    </article>
                </div>
            </section>

            <section aria-labelledby="strategyTitle">
                <div
                    class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                    data-aos="fade-right"
                >
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">
                            Ranked strategy
                        </p>

                        <h2
                            id="strategyTitle"
                            class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                        >
                            Upload strategy ranking
                        </h2>
                    </div>

                    <div class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-400">
                        <i data-lucide="arrow-down-wide-narrow" class="h-4 w-4"></i>
                        Highest score first
                    </div>
                </div>

                <div class="grid gap-5">
                    <?php foreach ($strategies as $index => $strategy): ?>
                        <?php
                        $score = (float) $strategy['performance_score'];
                        $meta = scoreMeta($score);
                        ?>

                        <article
                            class="glass card-hover rounded-[1.75rem] p-5 sm:p-7"
                            data-aos="fade-up"
                            data-aos-delay="<?= min($index * 45, 250) ?>"
                        >
                            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                                <div class="flex min-w-0 items-start gap-4">
                                    <span class="rank-number grid h-12 w-12 shrink-0 place-items-center rounded-2xl font-display text-lg font-extrabold shadow-glow">
                                        <?= $index + 1 ?>
                                    </span>

                                    <div class="min-w-0">
                                        <p class="text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                                            Recommended day
                                        </p>

                                        <h3 class="mt-2 font-display text-2xl font-extrabold sm:text-3xl">
                                            <?= e($strategy['day'] ?? '-') ?>
                                        </h3>

                                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                            <div class="rounded-2xl border border-cyan-300/10 bg-cyan-400/[.035] p-4">
                                                <div class="flex items-center gap-3">
                                                    <i data-lucide="clock-3" class="h-5 w-5 text-cyan-300"></i>

                                                    <div>
                                                        <p class="text-xs uppercase tracking-[.14em] text-slate-500">
                                                            Best time
                                                        </p>

                                                        <p class="mt-1 font-bold text-cyan-200">
                                                            <?= e($strategy['best_time'] ?? '-') ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-2xl border border-violet-300/10 bg-violet-400/[.035] p-4">
                                                <div class="flex items-center gap-3">
                                                    <i data-lucide="sparkles" class="h-5 w-5 text-violet-300"></i>

                                                    <div>
                                                        <p class="text-xs uppercase tracking-[.14em] text-slate-500">
                                                            Theme
                                                        </p>

                                                        <p class="mt-1 font-bold text-violet-200">
                                                            <?= e($strategy['theme'] ?? '-') ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="lg:min-w-[230px] lg:text-right">
                                    <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                        Performance score
                                    </p>

                                    <p class="mt-1 font-display text-5xl font-extrabold <?= e($meta['text']) ?>">
                                        <?= number_format($score, 0) ?><span class="text-xl">%</span>
                                    </p>

                                    <span class="mt-3 inline-flex rounded-full border px-3 py-1.5 text-xs font-bold <?= e($meta['badge']) ?> <?= e($meta['text']) ?>">
                                        <?= e($meta['label']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mt-7">
                                <div class="mb-2 flex items-center justify-between gap-3 text-xs font-semibold text-slate-400">
                                    <span>Algorithm match</span>
                                    <span><?= number_format($score, 0) ?>%</span>
                                </div>

                                <div class="h-3 overflow-hidden rounded-full bg-white/10">
                                    <div
                                        class="progress-shine h-full rounded-full bg-gradient-to-r <?= e($meta['bar']) ?>"
                                        style="width: <?= number_format($score, 2, '.', '') ?>%"
                                    ></div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mt-8 grid gap-5 lg:grid-cols-[1.15fr_.85fr]">
                <article
                    class="glass rounded-[1.75rem] p-6 sm:p-8"
                    data-aos="fade-right"
                >
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.2em] text-violet-300">
                                AI insight
                            </p>

                            <h2 class="mt-2 font-display text-xl font-extrabold sm:text-2xl">
                                Recommended next action
                            </h2>
                        </div>

                        <span class="w-fit rounded-full border border-violet-300/15 bg-violet-400/10 px-3 py-1.5 text-xs font-bold text-violet-200">
                            Dynamic summary
                        </span>
                    </div>

                    <div class="mt-6 rounded-2xl border border-white/10 bg-white/[.035] p-5">
                        <div class="flex gap-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                                <i data-lucide="brain-circuit" class="h-6 w-6"></i>
                            </span>

                            <p class="text-sm leading-7 text-slate-300 sm:text-base">
                                <?= e($insight) ?>
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                            <i data-lucide="flask-conical" class="h-5 w-5 text-amber-300"></i>

                            <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">
                                Experiment
                            </p>

                            <p class="mt-1 font-bold">
                                A/B test hook
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                            <i data-lucide="repeat-2" class="h-5 w-5 text-emerald-300"></i>

                            <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">
                                Review cycle
                            </p>

                            <p class="mt-1 font-bold">
                                7–14 hari
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                            <i data-lucide="database" class="h-5 w-5 text-cyan-300"></i>

                            <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">
                                Average score
                            </p>

                            <p class="mt-1 font-bold">
                                <?= number_format($averageScore, 1) ?>%
                            </p>
                        </div>
                    </div>
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
                                    Scheduler action
                                </p>

                                <h2 class="mt-2 font-display text-xl font-extrabold">
                                    Apply best strategy
                                </h2>
                            </div>

                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15">
                                <i data-lucide="workflow" class="h-6 w-6"></i>
                            </span>
                        </div>

                        <p class="mt-8 text-sm leading-7 text-white/75">
                            Gunakan rekomendasi terbaik sebagai draft jadwal sebelum dikirim ke automation center.
                        </p>

                        <div class="mt-6 rounded-2xl bg-white/10 p-5">
                            <p class="text-xs uppercase tracking-[.16em] text-white/60">
                                Suggested schedule
                            </p>

                            <p class="mt-2 font-display text-2xl font-extrabold">
                                <?= e($bestDay) ?>, <?= e($bestTime) ?>
                            </p>

                            <p class="mt-1 text-sm text-cyan-100">
                                Theme: <?= e($bestTheme) ?>
                            </p>
                        </div>

                        <a
                            href="../automation/automation_center.php"
                            class="mt-7 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-950 transition hover:-translate-y-1 hover:bg-cyan-100"
                        >
                            Open Automation Center
                            <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                </article>
            </section>
        <?php endif; ?>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Upload Time • Theme • Performance Score • AI Recommendation</p>
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