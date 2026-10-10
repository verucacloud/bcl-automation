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
            'label' => 'Excellent',
            'description' => 'Distribusi dan respons audiens berada pada level sangat baik.',
            'text' => 'text-emerald-300',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10',
            'dot' => 'bg-emerald-400',
        ];
    }

    if ($score >= 70) {
        return [
            'label' => 'Healthy',
            'description' => 'Performa akun stabil dan memiliki ruang optimasi lanjutan.',
            'text' => 'text-cyan-300',
            'badge' => 'border-cyan-400/20 bg-cyan-400/10',
            'dot' => 'bg-cyan-400',
        ];
    }

    if ($score >= 50) {
        return [
            'label' => 'Needs Attention',
            'description' => 'Beberapa indikator membutuhkan evaluasi dan eksperimen baru.',
            'text' => 'text-amber-300',
            'badge' => 'border-amber-400/20 bg-amber-400/10',
            'dot' => 'bg-amber-400',
        ];
    }

    return [
        'label' => 'Critical',
        'description' => 'Distribusi konten melemah dan perlu tindakan korektif.',
        'text' => 'text-rose-300',
        'badge' => 'border-rose-400/20 bg-rose-400/10',
        'dot' => 'bg-rose-400',
    ];
}

function insightText(float $engagement, float $retention, float $growth, float $health): string
{
    $scores = [
        'engagement' => $engagement,
        'retention' => $retention,
        'growth' => $growth,
    ];

    asort($scores);
    $weakest = array_key_first($scores);
    $strongest = array_key_last($scores);

    $labels = [
        'engagement' => 'engagement',
        'retention' => 'retention',
        'growth' => 'pertumbuhan akun',
    ];

    if ($health >= 85) {
        return 'Kondisi algoritma berada pada level sangat baik. Pertahankan konsistensi pada ' .
            $labels[$strongest] .
            ' dan gunakan eksperimen terkontrol untuk meningkatkan ' .
            $labels[$weakest] .
            '.';
    }

    if ($health >= 70) {
        return 'Performa algoritma tergolong sehat. Area terkuat saat ini adalah ' .
            $labels[$strongest] .
            ', sedangkan optimasi berikutnya sebaiknya difokuskan pada ' .
            $labels[$weakest] .
            '.';
    }

    if ($health >= 50) {
        return 'Performa masih cukup, tetapi distribusi konten belum optimal. Prioritaskan perbaikan pada ' .
            $labels[$weakest] .
            ' melalui pengujian hook, durasi, tema, dan waktu publikasi.';
    }

    return 'Performa algoritma sedang melemah. Lakukan audit konten, kurangi eksperimen yang tidak konsisten, dan fokuskan evaluasi awal pada ' .
        $labels[$weakest] .
        '.';
}

$query = $conn->query('SELECT * FROM algorithm_matrix ORDER BY id ASC LIMIT 1');

if (!$query) {
    http_response_code(500);
    exit('Database Error: ' . e($conn->error));
}

$d = $query->fetch_assoc();

if (!$d) {
    $d = [
        'algorithm_health' => 0,
        'engagement_score' => 0,
        'retention_score' => 0,
        'growth_score' => 0,
    ];
}

$algorithmHealth = clampScore($d['algorithm_health'] ?? 0);
$engagementScore = clampScore($d['engagement_score'] ?? 0);
$retentionScore = clampScore($d['retention_score'] ?? 0);
$growthScore = clampScore($d['growth_score'] ?? 0);

$status = scoreMeta($algorithmHealth);
$insight = insightText(
    $engagementScore,
    $retentionScore,
    $growthScore,
    $algorithmHealth
);

$matrixItems = [
    [
        'title' => 'Engagement',
        'value' => $engagementScore,
        'icon' => 'heart',
        'iconClass' => 'text-pink-300',
        'iconBg' => 'bg-pink-400/10',
        'bar' => 'from-pink-500 to-rose-400',
        'description' => 'Interaksi audiens terhadap konten.',
    ],
    [
        'title' => 'Retention',
        'value' => $retentionScore,
        'icon' => 'timer',
        'iconClass' => 'text-amber-300',
        'iconBg' => 'bg-amber-400/10',
        'bar' => 'from-amber-400 to-orange-400',
        'description' => 'Kemampuan konten mempertahankan perhatian.',
    ],
    [
        'title' => 'Growth',
        'value' => $growthScore,
        'icon' => 'trending-up',
        'iconClass' => 'text-emerald-300',
        'iconBg' => 'bg-emerald-400/10',
        'bar' => 'from-emerald-400 to-cyan-400',
        'description' => 'Kecepatan perkembangan performa akun.',
    ],
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Algorithm Matrix | Social Media Intelligence</title>

    <meta
        name="description"
        content="Dashboard monitoring engagement, retention, growth, dan kesehatan algoritma akun media sosial."
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
            animation: progressShine 2.6s linear infinite;
        }

        .pulse-dot {
            animation: pulseDot 1.8s ease-in-out infinite;
        }

        .ring-progress {
            --score: 0;
            width: 10rem;
            aspect-ratio: 1;
            border-radius: 999px;
            display: grid;
            place-items: center;
            position: relative;
            background:
                conic-gradient(
                    #22d3ee calc(var(--score) * 1%),
                    rgba(255, 255, 255, .10) 0
                );
            box-shadow:
                0 0 45px rgba(34, 211, 238, .18),
                inset 0 0 25px rgba(255, 255, 255, .04);
        }

        .ring-progress::before {
            content: "";
            position: absolute;
            width: 78%;
            aspect-ratio: 1;
            border-radius: inherit;
            background:
                linear-gradient(145deg, #0f172a, #020617);
            border: 1px solid rgba(255, 255, 255, .08);
        }

        .ring-content {
            position: relative;
            z-index: 1;
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

        .orb {
            animation: orbMove 10s ease-in-out infinite alternate;
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

        @media (max-width: 640px) {
            .ring-progress {
                width: 8.5rem;
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
                    Algorithm Matrix
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

            <div class="grid items-center gap-8 lg:grid-cols-[1.1fr_.9fr]">
                <div>
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-cyan-300">
                        <span class="pulse-dot h-2 w-2 rounded-full <?= e($status['dot']) ?>"></span>
                        Live Matrix Monitoring
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">Algorithm Matrix</span>
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Pantau engagement, retention, pertumbuhan akun, dan kesehatan algoritma melalui satu tampilan evaluasi terpadu.
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-300">
                            Engagement Analysis
                        </span>

                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-300">
                            Retention Monitoring
                        </span>

                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-300">
                            Growth Evaluation
                        </span>
                    </div>
                </div>

                <div class="glass rounded-[1.75rem] p-5 sm:p-7">
                    <div class="flex flex-col items-center justify-between gap-7 sm:flex-row">
                        <div class="text-center sm:text-left">
                            <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">
                                Overall Algorithm Health
                            </p>

                            <p class="mt-3 font-display text-5xl font-extrabold text-cyan-300 sm:text-6xl">
                                <?= number_format($algorithmHealth, 0) ?><span class="text-2xl">%</span>
                            </p>

                            <span class="mt-4 inline-flex rounded-full border px-3 py-1.5 text-xs font-bold <?= e($status['badge']) ?> <?= e($status['text']) ?>">
                                <?= e($status['label']) ?>
                            </span>
                        </div>

                        <div
                            class="ring-progress"
                            style="--score: <?= number_format($algorithmHealth, 2, '.', '') ?>"
                            aria-label="Algorithm health <?= number_format($algorithmHealth, 0) ?> persen"
                        >
                            <div class="ring-content text-center">
                                <p class="font-display text-3xl font-extrabold">
                                    <?= number_format($algorithmHealth, 0) ?>
                                </p>

                                <p class="text-[10px] uppercase tracking-[.2em] text-slate-500">
                                    Score
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-7 h-3 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="progress-shine h-full rounded-full bg-gradient-to-r from-indigo-400 via-cyan-300 to-emerald-300"
                            style="width: <?= number_format($algorithmHealth, 2, '.', '') ?>%"
                        ></div>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-400">
                        <?= e($status['description']) ?>
                    </p>
                </div>
            </div>
        </section>

        <section aria-labelledby="matrixTitle">
            <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end" data-aos="fade-right">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                        Performance dimensions
                    </p>

                    <h2
                        id="matrixTitle"
                        class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                    >
                        Matrix performance score
                    </h2>
                </div>

                <p class="max-w-md text-sm leading-6 text-slate-400">
                    Setiap nilai menggambarkan indikator observasi, bukan akses langsung ke algoritma internal platform.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <?php foreach ($matrixItems as $index => $item): ?>
                    <article
                        class="glass card-hover rounded-[1.65rem] p-6"
                        data-aos="fade-up"
                        data-aos-delay="<?= $index * 90 ?>"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.16em] text-slate-500">
                                    Signal <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?>
                                </p>

                                <h3 class="mt-2 font-display text-xl font-extrabold">
                                    <?= e($item['title']) ?>
                                </h3>
                            </div>

                            <span class="grid h-12 w-12 place-items-center rounded-2xl <?= e($item['iconBg']) ?> <?= e($item['iconClass']) ?>">
                                <i data-lucide="<?= e($item['icon']) ?>" class="h-6 w-6"></i>
                            </span>
                        </div>

                        <p class="mt-7 font-display text-5xl font-extrabold">
                            <?= number_format((float) $item['value'], 0) ?><span class="text-xl text-slate-500">%</span>
                        </p>

                        <p class="mt-3 text-sm leading-6 text-slate-400">
                            <?= e($item['description']) ?>
                        </p>

                        <div class="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="progress-shine h-full rounded-full bg-gradient-to-r <?= e($item['bar']) ?>"
                                style="width: <?= number_format((float) $item['value'], 2, '.', '') ?>%"
                            ></div>
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
                            AI Evaluation
                        </p>

                        <h2 class="mt-2 font-display text-xl font-extrabold sm:text-2xl">
                            Insight & recommended focus
                        </h2>
                    </div>

                    <span class="w-fit rounded-full border border-violet-300/15 bg-violet-400/10 px-3 py-1.5 text-xs font-bold text-violet-200">
                        Dynamic insight
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
                        <i data-lucide="mouse-pointer-click" class="h-5 w-5 text-pink-300"></i>
                        <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Hook</p>
                        <p class="mt-1 font-bold">Uji 2 variasi</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <i data-lucide="clock-3" class="h-5 w-5 text-cyan-300"></i>
                        <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Timing</p>
                        <p class="mt-1 font-bold">Evaluasi per jam</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4">
                        <i data-lucide="repeat-2" class="h-5 w-5 text-emerald-300"></i>
                        <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Iteration</p>
                        <p class="mt-1 font-bold">Review 7 hari</p>
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
                                Composite score
                            </p>

                            <h2 class="mt-2 font-display text-xl font-extrabold">
                                AI Performance Level
                            </h2>
                        </div>

                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15">
                            <i data-lucide="sparkles" class="h-6 w-6"></i>
                        </span>
                    </div>

                    <p class="mt-8 font-display text-6xl font-extrabold">
                        <?= number_format($algorithmHealth, 0) ?><span class="text-2xl text-white/65">/100</span>
                    </p>

                    <p class="mt-4 max-w-sm text-sm leading-6 text-white/75">
                        Skor gabungan digunakan untuk membantu tim menentukan prioritas observasi dan evaluasi konten.
                    </p>

                    <a
                        href="evaluation.php"
                        class="mt-7 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-950 transition hover:-translate-y-1 hover:bg-cyan-100"
                    >
                        Buka Evaluation
                        <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                    </a>
                </div>
            </article>
        </section>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Algorithm Matrix • Monitoring • Evaluation</p>
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