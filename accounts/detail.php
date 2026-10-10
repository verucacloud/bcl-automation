<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Account Monitoring Detail
|--------------------------------------------------------------------------
| File:
| public_html/accounts/detail.php
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

/*
|--------------------------------------------------------------------------
| Production error handling
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

/*
|--------------------------------------------------------------------------
| Helpers
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

function compactNumber(mixed $value): string
{
    $number = is_numeric($value)
        ? (float) $value
        : 0.0;

    if ($number >= 1_000_000_000) {
        return rtrim(
            rtrim(
                number_format(
                    $number / 1_000_000_000,
                    1,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . 'B';
    }

    if ($number >= 1_000_000) {
        return rtrim(
            rtrim(
                number_format(
                    $number / 1_000_000,
                    1,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . 'M';
    }

    if ($number >= 1_000) {
        return rtrim(
            rtrim(
                number_format(
                    $number / 1_000,
                    1,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        ) . 'K';
    }

    return number_format(
        $number,
        0,
        ',',
        '.'
    );
}

function formatDateTime(?string $value): string
{
    if (
        $value === null ||
        trim($value) === ''
    ) {
        return 'Belum tersedia';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date(
        'd M Y, H:i',
        $timestamp
    );
}

function statusStyle(string $status): array
{
    return match ($status) {
        'connected' => [
            'label' => 'Connected',
            'badge' => 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300',
            'dot' => 'bg-emerald-400',
        ],

        'expired' => [
            'label' => 'Token Expired',
            'badge' => 'border-amber-400/20 bg-amber-400/10 text-amber-300',
            'dot' => 'bg-amber-400',
        ],

        'revoked' => [
            'label' => 'Revoked',
            'badge' => 'border-rose-400/20 bg-rose-400/10 text-rose-300',
            'dot' => 'bg-rose-400',
        ],

        'error' => [
            'label' => 'Error',
            'badge' => 'border-rose-400/20 bg-rose-400/10 text-rose-300',
            'dot' => 'bg-rose-400',
        ],

        default => [
            'label' => ucfirst($status),
            'badge' => 'border-slate-400/20 bg-slate-400/10 text-slate-300',
            'dot' => 'bg-slate-400',
        ],
    };
}

/*
|--------------------------------------------------------------------------
| Validasi account ID
|--------------------------------------------------------------------------
*/

$accountId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $accountId === false ||
    $accountId === null ||
    $accountId <= 0
) {
    http_response_code(400);

    exit(
        'ID akun tidak valid.'
    );
}

/*
|--------------------------------------------------------------------------
| Ambil akun dari database
|--------------------------------------------------------------------------
*/

$statement = $conn->prepare(
    "
    SELECT
        id,
        user_id,
        platform,
        platform_account_id,
        username,
        display_name,
        avatar_url,
        token_expires_at,
        scopes,
        subscriber_count,
        video_count,
        view_count,
        status,
        last_sync_at,
        created_at,
        updated_at
    FROM social_accounts
    WHERE id = ?
    LIMIT 1
    "
);

if (!$statement) {
    error_log(
        'Prepare account detail gagal: ' .
        $conn->error
    );

    http_response_code(500);

    exit(
        'Gagal membaca data akun.'
    );
}

$statement->bind_param(
    'i',
    $accountId
);

if (!$statement->execute()) {
    error_log(
        'Execute account detail gagal: ' .
        $statement->error
    );

    $statement->close();

    http_response_code(500);

    exit(
        'Gagal membaca data akun.'
    );
}

$result = $statement->get_result();

$account = $result instanceof mysqli_result
    ? $result->fetch_assoc()
    : null;

$statement->close();

if (!is_array($account)) {
    http_response_code(404);

    exit(
        'Akun tidak ditemukan.'
    );
}

/*
|--------------------------------------------------------------------------
| Siapkan data tampilan
|--------------------------------------------------------------------------
*/

$displayName = trim(
    (string) (
        $account['display_name'] ??
        'YouTube Channel'
    )
);

$username = trim(
    (string) (
        $account['username'] ??
        ''
    )
);

$platform = strtolower(
    trim(
        (string) (
            $account['platform'] ??
            'youtube'
        )
    )
);

$status = strtolower(
    trim(
        (string) (
            $account['status'] ??
            'connected'
        )
    )
);

$statusData = statusStyle($status);

$subscriberCount = (int) (
    $account['subscriber_count'] ?? 0
);

$videoCount = (int) (
    $account['video_count'] ?? 0
);

$viewCount = (int) (
    $account['view_count'] ?? 0
);

$avatarUrl = trim(
    (string) (
        $account['avatar_url'] ??
        ''
    )
);

$channelId = trim(
    (string) (
        $account['platform_account_id'] ??
        ''
    )
);

$channelUrl = $channelId !== ''
    ? 'https://www.youtube.com/channel/' .
        rawurlencode($channelId)
    : '#';

$initial = strtoupper(
    mb_substr(
        $displayName,
        0,
        1,
        'UTF-8'
    )
);

$tokenExpiresAt = formatDateTime(
    $account['token_expires_at'] ?? null
);

$lastSyncAt = formatDateTime(
    $account['last_sync_at'] ?? null
);

$connectedAt = formatDateTime(
    $account['created_at'] ?? null
);

$updatedAt = formatDateTime(
    $account['updated_at'] ?? null
);

$successMessage = isset(
    $_SESSION['account_success']
)
    ? trim(
        (string) $_SESSION['account_success']
    )
    : '';

$errorMessage = isset(
    $_SESSION['account_error']
)
    ? trim(
        (string) $_SESSION['account_error']
    )
    : '';

unset(
    $_SESSION['account_success'],
    $_SESSION['account_error']
);

?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#020617"
    >

    <title>
        <?= e($displayName) ?> — Monitoring
    </title>

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
                        display: [
                            'Plus Jakarta Sans',
                            'sans-serif'
                        ]
                    },
                    boxShadow: {
                        glow:
                            '0 0 45px rgba(99, 102, 241, .25)'
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
                    circle at 8% 4%,
                    rgba(99, 102, 241, .28),
                    transparent 25rem
                ),
                radial-gradient(
                    circle at 90% 84%,
                    rgba(6, 182, 212, .18),
                    transparent 27rem
                ),
                #020617;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            opacity: .18;
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
                linear-gradient(
                    to bottom,
                    black,
                    transparent 88%
                );
        }

        .glass {
            background:
                linear-gradient(
                    145deg,
                    rgba(255, 255, 255, .10),
                    rgba(255, 255, 255, .045)
                );
            border:
                1px solid rgba(255, 255, 255, .11);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .06),
                0 22px 55px rgba(0, 0, 0, .17);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .card-hover {
            transition:
                transform .3s ease,
                border-color .3s ease,
                background .3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            border-color:
                rgba(129, 140, 248, .4);
        }
    </style>
</head>

<body class="font-sans text-white antialiased">
    <header
        class="sticky top-0 z-30 border-b border-white/5 bg-slate-950/60 backdrop-blur-xl"
    >
        <div
            class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
        >
            <a
                href="index.php"
                class="inline-flex items-center gap-3 font-bold text-slate-300 transition hover:text-white"
            >
                <span
                    class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-white/5"
                >
                    <i
                        data-lucide="arrow-left"
                        class="h-5 w-5"
                    ></i>
                </span>

                <span class="hidden sm:inline">
                    Akun Terhubung
                </span>
            </a>

            <div class="text-right">
                <p
                    class="font-display font-extrabold"
                >
                    Account Monitoring
                </p>

                <p class="text-xs text-slate-500">
                    YouTube Data API v3
                </p>
            </div>
        </div>
    </header>

    <main
        class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8"
    >
        <?php if ($successMessage !== ''): ?>
            <div
                class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-emerald-200"
            >
                <i
                    data-lucide="circle-check-big"
                    class="mt-0.5 h-5 w-5 shrink-0"
                ></i>

                <p class="text-sm font-semibold">
                    <?= e($successMessage) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div
                class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-400/20 bg-rose-400/10 p-4 text-rose-200"
            >
                <i
                    data-lucide="circle-alert"
                    class="mt-0.5 h-5 w-5 shrink-0"
                ></i>

                <p class="text-sm font-semibold">
                    <?= e($errorMessage) ?>
                </p>
            </div>
        <?php endif; ?>

        <section
            class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-red-600/25 via-slate-950/30 to-cyan-500/15 p-6 sm:p-9"
        >
            <div
                class="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-red-500/15 blur-3xl"
            ></div>

            <div
                class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-center"
            >
                <div
                    class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center"
                >
                    <?php if ($avatarUrl !== ''): ?>
                        <img
                            src="<?= e($avatarUrl) ?>"
                            alt="<?= e($displayName) ?>"
                            class="h-24 w-24 rounded-[1.75rem] border border-white/15 object-cover shadow-2xl"
                            referrerpolicy="no-referrer"
                        >
                    <?php else: ?>
                        <div
                            class="grid h-24 w-24 shrink-0 place-items-center rounded-[1.75rem] bg-gradient-to-br from-red-500 to-violet-600 font-display text-4xl font-extrabold shadow-2xl"
                        >
                            <?= e($initial) ?>
                        </div>
                    <?php endif; ?>

                    <div class="min-w-0">
                        <div
                            class="mb-3 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($statusData['badge']) ?>"
                        >
                            <span
                                class="h-2 w-2 rounded-full <?= e($statusData['dot']) ?>"
                            ></span>

                            <?= e($statusData['label']) ?>
                        </div>

                        <h1
                            class="truncate font-display text-3xl font-extrabold sm:text-4xl"
                        >
                            <?= e($displayName) ?>
                        </h1>

                        <p
                            class="mt-2 break-all text-sm font-semibold text-slate-400"
                        >
                            <?= e($platform) ?>

                            <?php if ($username !== ''): ?>
                                • <?= e($username) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row"
                >
                    <a
                        href="<?= e($channelUrl) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-5 font-bold text-white transition hover:-translate-y-0.5 hover:bg-white/10"
                    >
                        <i
                            data-lucide="youtube"
                            class="h-5 w-5"
                        ></i>

                        Buka Channel
                    </a>

                    <a
                        href="../connectors/youtube/sync.php?id=<?= (int) $accountId ?>"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-cyan-500 px-5 font-bold text-white shadow-glow transition hover:-translate-y-0.5"
                    >
                        <i
                            data-lucide="refresh-cw"
                            class="h-5 w-5"
                        ></i>

                        Sinkronkan
                    </a>
                </div>
            </div>
        </section>

        <section
            class="mt-7 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4"
        >
            <article
                class="glass card-hover rounded-[1.6rem] p-6"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <p
                        class="text-sm font-semibold text-slate-400"
                    >
                        Subscribers
                    </p>

                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-400/10 text-indigo-300"
                    >
                        <i
                            data-lucide="users"
                            class="h-5 w-5"
                        ></i>
                    </span>
                </div>

                <p
                    class="mt-6 font-display text-4xl font-extrabold"
                >
                    <?= e(compactNumber($subscriberCount)) ?>
                </p>

                <p class="mt-2 text-xs text-slate-500">
                    <?= e(
                        number_format(
                            $subscriberCount,
                            0,
                            ',',
                            '.'
                        )
                    ) ?> total subscriber
                </p>
            </article>

            <article
                class="glass card-hover rounded-[1.6rem] p-6"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <p
                        class="text-sm font-semibold text-slate-400"
                    >
                        Total Video
                    </p>

                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-red-400/10 text-red-300"
                    >
                        <i
                            data-lucide="clapperboard"
                            class="h-5 w-5"
                        ></i>
                    </span>
                </div>

                <p
                    class="mt-6 font-display text-4xl font-extrabold"
                >
                    <?= e(compactNumber($videoCount)) ?>
                </p>

                <p class="mt-2 text-xs text-slate-500">
                    Video publik di channel
                </p>
            </article>

            <article
                class="glass card-hover rounded-[1.6rem] p-6"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <p
                        class="text-sm font-semibold text-slate-400"
                    >
                        Total Views
                    </p>

                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-cyan-400/10 text-cyan-300"
                    >
                        <i
                            data-lucide="eye"
                            class="h-5 w-5"
                        ></i>
                    </span>
                </div>

                <p
                    class="mt-6 font-display text-4xl font-extrabold"
                >
                    <?= e(compactNumber($viewCount)) ?>
                </p>

                <p class="mt-2 text-xs text-slate-500">
                    <?= e(
                        number_format(
                            $viewCount,
                            0,
                            ',',
                            '.'
                        )
                    ) ?> total views
                </p>
            </article>

            <article
                class="relative overflow-hidden rounded-[1.6rem] border border-violet-300/20 bg-gradient-to-br from-indigo-600 via-violet-600 to-cyan-600 p-6 shadow-glow"
            >
                <div
                    class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-white/10 blur-2xl"
                ></div>

                <div
                    class="relative flex items-center justify-between"
                >
                    <p
                        class="text-sm font-semibold text-white/80"
                    >
                        Connection
                    </p>

                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-white/15"
                    >
                        <i
                            data-lucide="plug-zap"
                            class="h-5 w-5"
                        ></i>
                    </span>
                </div>

                <p
                    class="relative mt-6 font-display text-3xl font-extrabold"
                >
                    <?= e($statusData['label']) ?>
                </p>

                <p
                    class="relative mt-2 text-xs text-white/70"
                >
                    YouTube OAuth connection
                </p>
            </article>
        </section>

        <section
            class="mt-7 grid gap-5 lg:grid-cols-[1.2fr_.8fr]"
        >
            <article
                class="glass rounded-[1.75rem] p-6 sm:p-8"
            >
                <div
                    class="flex items-center justify-between gap-4"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300"
                        >
                            Account information
                        </p>

                        <h2
                            class="mt-2 font-display text-2xl font-extrabold"
                        >
                            Detail koneksi
                        </h2>
                    </div>

                    <i
                        data-lucide="circle-user-round"
                        class="h-7 w-7 text-indigo-300"
                    ></i>
                </div>

                <dl class="mt-7 grid gap-4">
                    <div
                        class="flex flex-col justify-between gap-2 rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:flex-row sm:items-center"
                    >
                        <dt class="text-sm text-slate-500">
                            Channel ID
                        </dt>

                        <dd
                            class="break-all text-sm font-bold text-slate-200"
                        >
                            <?= e($channelId) ?>
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-2 rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:flex-row sm:items-center"
                    >
                        <dt class="text-sm text-slate-500">
                            Platform
                        </dt>

                        <dd
                            class="font-bold capitalize text-slate-200"
                        >
                            <?= e($platform) ?>
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-2 rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:flex-row sm:items-center"
                    >
                        <dt class="text-sm text-slate-500">
                            Terhubung sejak
                        </dt>

                        <dd class="font-bold text-slate-200">
                            <?= e($connectedAt) ?>
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-2 rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:flex-row sm:items-center"
                    >
                        <dt class="text-sm text-slate-500">
                            Sinkronisasi terakhir
                        </dt>

                        <dd class="font-bold text-cyan-300">
                            <?= e($lastSyncAt) ?>
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-2 rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:flex-row sm:items-center"
                    >
                        <dt class="text-sm text-slate-500">
                            Token kedaluwarsa
                        </dt>

                        <dd class="font-bold text-amber-300">
                            <?= e($tokenExpiresAt) ?>
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-2 rounded-2xl border border-white/10 bg-white/[.035] p-4 sm:flex-row sm:items-center"
                    >
                        <dt class="text-sm text-slate-500">
                            Data diperbarui
                        </dt>

                        <dd class="font-bold text-slate-200">
                            <?= e($updatedAt) ?>
                        </dd>
                    </div>
                </dl>
            </article>

            <article
                class="glass rounded-[1.75rem] p-6 sm:p-8"
            >
                <div
                    class="flex items-center justify-between gap-4"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300"
                        >
                            Security
                        </p>

                        <h2
                            class="mt-2 font-display text-2xl font-extrabold"
                        >
                            Kelola koneksi
                        </h2>
                    </div>

                    <i
                        data-lucide="shield-check"
                        class="h-7 w-7 text-cyan-300"
                    ></i>
                </div>

                <div
                    class="mt-7 rounded-2xl border border-emerald-400/15 bg-emerald-400/[.06] p-5"
                >
                    <div class="flex items-start gap-3">
                        <i
                            data-lucide="lock-keyhole"
                            class="mt-0.5 h-5 w-5 shrink-0 text-emerald-300"
                        ></i>

                        <div>
                            <p
                                class="font-bold text-emerald-200"
                            >
                                OAuth connection
                            </p>

                            <p
                                class="mt-2 text-sm leading-6 text-slate-400"
                            >
                                Password akun Google tidak disimpan.
                                Sistem hanya menyimpan token OAuth
                                untuk membaca data channel.
                            </p>
                        </div>
                    </div>
                </div>

                <a
                    href="../connectors/youtube/sync.php?id=<?= (int) $accountId ?>"
                    class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-cyan-500 px-5 font-bold text-white"
                >
                    <i
                        data-lucide="refresh-cw"
                        class="h-5 w-5"
                    ></i>

                    Sinkronkan Sekarang
                </a>

                <a
                    href="disconnect.php?id=<?= (int) $accountId ?>"
                    class="mt-3 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-rose-400/20 bg-rose-400/10 px-5 font-bold text-rose-300 transition hover:bg-rose-400/20"
                >
                    <i
                        data-lucide="unlink"
                        class="h-5 w-5"
                    ></i>

                    Putuskan Koneksi
                </a>
            </article>
        </section>
    </main>

    <footer
        class="mt-8 border-t border-white/5 bg-slate-950/50"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-7 text-center text-xs text-slate-500 sm:flex-row sm:justify-between sm:px-6 lg:px-8"
        >
            <p>
                © <?= date('Y') ?>
                Social Media Intelligence Platform
            </p>

            <p>
                YouTube Monitoring • OAuth • Analytics
            </p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>