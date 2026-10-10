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
            'label' => 'High Potential',
            'text' => 'text-emerald-300',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10',
            'bar' => 'from-emerald-400 via-cyan-400 to-sky-400',
            'icon' => 'flame',
        ];
    }

    if ($score >= 70) {
        return [
            'label' => 'Strong',
            'text' => 'text-cyan-300',
            'badge' => 'border-cyan-400/20 bg-cyan-400/10',
            'bar' => 'from-cyan-400 via-sky-400 to-indigo-500',
            'icon' => 'trending-up',
        ];
    }

    if ($score >= 50) {
        return [
            'label' => 'Average',
            'text' => 'text-amber-300',
            'badge' => 'border-amber-400/20 bg-amber-400/10',
            'bar' => 'from-amber-400 via-orange-400 to-rose-400',
            'icon' => 'activity',
        ];
    }

    return [
        'label' => 'Needs Improvement',
        'text' => 'text-rose-300',
        'badge' => 'border-rose-400/20 bg-rose-400/10',
        'bar' => 'from-rose-500 via-pink-500 to-fuchsia-500',
        'icon' => 'triangle-alert',
    ];
}

function excerpt(string $text, int $length = 150): string
{
    $text = trim($text);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length)) . '…';
}

$result = $conn->query(
    'SELECT * FROM content_evaluation ORDER BY viral_score DESC'
);

if (!$result) {
    http_response_code(500);
    exit('Database Error: ' . e($conn->error));
}

$contents = [];

while ($row = $result->fetch_assoc()) {
    $row['viral_score'] = clampScore($row['viral_score'] ?? 0);
    $contents[] = $row;
}

$totalContent = count($contents);
$averageScore = 0;
$highestScore = 0;
$topTheme = '-';

if ($totalContent > 0) {
    $averageScore = array_sum(
        array_map(
            static fn(array $item): float => (float) $item['viral_score'],
            $contents
        )
    ) / $totalContent;

    $highestScore = (float) $contents[0]['viral_score'];
    $topTheme = (string) ($contents[0]['theme'] ?? '-');
}

$topContents = array_slice($contents, 0, 3);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">

    <title>Content Evaluation Intelligence</title>

    <meta
        name="description"
        content="Analisis performa konten berdasarkan viral score, tema, evaluasi, dan rekomendasi."
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

        .rank-card:nth-child(1) {
            border-color: rgba(250, 204, 21, .25);
        }

        .rank-card:nth-child(2) {
            border-color: rgba(148, 163, 184, .22);
        }

        .rank-card:nth-child(3) {
            border-color: rgba(251, 146, 60, .22);
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

        .pulse-dot {
            animation: pulseDot 1.8s ease-in-out infinite;
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
                    Content Evaluation
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
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.16em] text-cyan-300">
                        <span class="pulse-dot h-2 w-2 rounded-full bg-cyan-400"></span>
                        Content Performance Analysis
                    </div>

                    <h1 class="font-display text-3xl font-extrabold leading-tight sm:text-5xl lg:text-6xl">
                        <span class="animated-gradient-text">
                            Content Evaluation
                        </span>

                        <br>

                        Intelligence
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Identifikasi konten terbaik, evaluasi penyebab performa, dan susun rekomendasi untuk strategi publikasi berikutnya.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <article class="glass rounded-[1.5rem] p-5">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-indigo-400/10 text-indigo-300">
                            <i data-lucide="files" class="h-5 w-5"></i>
                        </span>

                        <p class="mt-5 text-xs uppercase tracking-[.16em] text-slate-500">
                            Total content
                        </p>

                        <p class="mt-1 font-display text-3xl font-extrabold">
                            <?= number_format($totalContent, 0, ',', '.') ?>
                        </p>
                    </article>

                    <article class="glass rounded-[1.5rem] p-5">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-cyan-400/10 text-cyan-300">
                            <i data-lucide="gauge" class="h-5 w-5"></i>
                        </span>

                        <p class="mt-5 text-xs uppercase tracking-[.16em] text-slate-500">
                            Average score
                        </p>

                        <p class="mt-1 font-display text-3xl font-extrabold text-cyan-300">
                            <?= number_format($averageScore, 1) ?>%
                        </p>
                    </article>

                    <article class="glass rounded-[1.5rem] p-5">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300">
                            <i data-lucide="trophy" class="h-5 w-5"></i>
                        </span>

                        <p class="mt-5 text-xs uppercase tracking-[.16em] text-slate-500">
                            Highest score
                        </p>

                        <p class="mt-1 font-display text-3xl font-extrabold text-emerald-300">
                            <?= number_format($highestScore, 0) ?>%
                        </p>
                    </article>

                    <article class="glass rounded-[1.5rem] p-5">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-violet-400/10 text-violet-300">
                            <i data-lucide="sparkles" class="h-5 w-5"></i>
                        </span>

                        <p class="mt-5 text-xs uppercase tracking-[.16em] text-slate-500">
                            Top theme
                        </p>

                        <p class="mt-1 line-clamp-2 font-display text-base font-extrabold text-violet-200">
                            <?= e($topTheme) ?>
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <?php if ($totalContent === 0): ?>
            <section
                class="glass rounded-[1.75rem] p-8 text-center sm:p-12"
                data-aos="fade-up"
            >
                <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white/5 text-slate-400">
                    <i data-lucide="database-zap" class="h-8 w-8"></i>
                </span>

                <h2 class="mt-6 font-display text-2xl font-extrabold">
                    Data evaluasi belum tersedia
                </h2>

                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-slate-400">
                    Tambahkan data ke tabel
                    <code class="rounded bg-white/5 px-2 py-1 text-cyan-300">content_evaluation</code>
                    untuk menampilkan ranking dan analisis konten.
                </p>
            </section>
        <?php else: ?>
            <section aria-labelledby="topContentTitle">
                <div
                    class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                    data-aos="fade-right"
                >
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-amber-300">
                            Top performance
                        </p>

                        <h2
                            id="topContentTitle"
                            class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                        >
                            Best performing content
                        </h2>
                    </div>

                    <p class="max-w-md text-sm leading-6 text-slate-400">
                        Ranking berdasarkan viral score tertinggi pada data evaluasi.
                    </p>
                </div>

                <div class="mb-10 grid gap-5 md:grid-cols-3">
                    <?php foreach ($topContents as $index => $item): ?>
                        <?php
                        $score = (float) $item['viral_score'];
                        $meta = scoreMeta($score);
                        ?>

                        <article
                            class="rank-card glass card-hover relative overflow-hidden rounded-[1.7rem] p-6"
                            data-aos="zoom-in"
                            data-aos-delay="<?= $index * 100 ?>"
                        >
                            <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-indigo-500/10 blur-3xl"></div>

                            <div class="relative flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-500">
                                        Rank <?= $index + 1 ?>
                                    </p>

                                    <h3 class="mt-2 font-display text-xl font-extrabold">
                                        <?= e($item['content_name'] ?? 'Untitled Content') ?>
                                    </h3>
                                </div>

                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-orange-400/10 text-orange-300">
                                    <i data-lucide="<?= e($index === 0 ? 'trophy' : 'flame') ?>" class="h-6 w-6"></i>
                                </span>
                            </div>

                            <span class="relative mt-5 inline-flex rounded-full border border-indigo-300/15 bg-indigo-400/10 px-3 py-1.5 text-xs font-bold text-indigo-200">
                                <?= e($item['theme'] ?? 'No Theme') ?>
                            </span>

                            <div class="relative mt-6 flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                        Viral score
                                    </p>

                                    <p class="mt-1 font-display text-5xl font-extrabold text-cyan-300">
                                        <?= number_format($score, 0) ?><span class="text-xl">%</span>
                                    </p>
                                </div>

                                <span class="rounded-full border px-3 py-1.5 text-xs font-bold <?= e($meta['badge']) ?> <?= e($meta['text']) ?>">
                                    <?= e($meta['label']) ?>
                                </span>
                            </div>

                            <div class="relative mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                                <div
                                    class="progress-shine h-full rounded-full bg-gradient-to-r <?= e($meta['bar']) ?>"
                                    style="width: <?= number_format($score, 2, '.', '') ?>%"
                                ></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section aria-labelledby="performanceTitle">
                <div
                    class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                    data-aos="fade-right"
                >
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">
                            Detailed evaluation
                        </p>

                        <h2
                            id="performanceTitle"
                            class="mt-2 font-display text-2xl font-extrabold sm:text-3xl"
                        >
                            Content performance analysis
                        </h2>
                    </div>

                    <div class="flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-400">
                        <i data-lucide="arrow-down-wide-narrow" class="h-4 w-4"></i>
                        Highest score first
                    </div>
                </div>

                <div class="grid gap-5">
                    <?php foreach ($contents as $index => $item): ?>
                        <?php
                        $score = (float) $item['viral_score'];
                        $meta = scoreMeta($score);
                        ?>

                        <article
                            class="glass card-hover rounded-[1.75rem] p-5 sm:p-7"
                            data-aos="fade-up"
                            data-aos-delay="<?= min($index * 40, 240) ?>"
                        >
                            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/5 text-sm font-extrabold text-slate-300">
                                            <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?>
                                        </span>

                                        <div>
                                            <h3 class="font-display text-xl font-extrabold sm:text-2xl">
                                                <?= e($item['content_name'] ?? 'Untitled Content') ?>
                                            </h3>

                                            <span class="mt-2 inline-flex rounded-full border border-indigo-300/15 bg-indigo-400/10 px-3 py-1 text-xs font-bold text-indigo-200">
                                                <?= e($item['theme'] ?? 'No Theme') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="lg:text-right">
                                    <p class="text-xs uppercase tracking-[.16em] text-slate-500">
                                        Viral score
                                    </p>

                                    <div class="mt-1 flex items-end gap-3 lg:justify-end">
                                        <p class="font-display text-4xl font-extrabold <?= e($meta['text']) ?>">
                                            <?= number_format($score, 0) ?>%
                                        </p>

                                        <span class="mb-1 rounded-full border px-3 py-1 text-[11px] font-bold <?= e($meta['badge']) ?> <?= e($meta['text']) ?>">
                                            <?= e($meta['label']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                                <div
                                    class="progress-shine h-full rounded-full bg-gradient-to-r <?= e($meta['bar']) ?>"
                                    style="width: <?= number_format($score, 2, '.', '') ?>%"
                                ></div>
                            </div>

                            <div class="mt-7 grid gap-5 lg:grid-cols-2">
                                <div class="rounded-2xl border border-cyan-300/10 bg-cyan-400/[.035] p-5">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-cyan-400/10 text-cyan-300">
                                            <i data-lucide="clipboard-check" class="h-5 w-5"></i>
                                        </span>

                                        <h4 class="font-display text-base font-extrabold text-cyan-200">
                                            Evaluation
                                        </h4>
                                    </div>

                                    <p class="mt-4 text-sm leading-7 text-slate-300">
                                        <?= e((string) ($item['evaluation'] ?? 'Belum ada evaluasi.')) ?>
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-violet-300/10 bg-violet-400/[.035] p-5">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-400/10 text-violet-300">
                                            <i data-lucide="lightbulb" class="h-5 w-5"></i>
                                        </span>

                                        <h4 class="font-display text-base font-extrabold text-violet-200">
                                            Recommendation
                                        </h4>
                                    </div>

                                    <p class="mt-4 text-sm leading-7 text-slate-300">
                                        <?= e((string) ($item['recommendation'] ?? 'Belum ada rekomendasi.')) ?>
                                    </p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="border-t border-white/5 bg-slate-950/50">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left lg:px-8">
            <p>© <?= date('Y') ?> Social Media Intelligence Platform.</p>
            <p>Content Evaluation • Viral Score • Recommendation</p>
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