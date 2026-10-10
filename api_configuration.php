<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$configFile = __DIR__ . '/config.local.php';

$config = file_exists($configFile)
    ? require $configFile
    : [];

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$clientIdReady =
    !empty($config['google_client_id']);

$clientSecretReady =
    !empty($config['google_client_secret']);

$redirectUri = isset(
    $config['google_redirect_uri']
)
    ? $config['google_redirect_uri']
    : '';

$youtubeApiReady =
    $clientIdReady &&
    $clientSecretReady &&
    $redirectUri !== '';

$accountCount = 0;

$countResult = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM social_accounts
    WHERE platform = 'youtube'
      AND status = 'connected'
    "
);

if ($countResult) {
    $countRow = $countResult->fetch_assoc();

    $accountCount = isset($countRow['total'])
        ? (int) $countRow['total']
        : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>API Configuration</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body
    class="min-h-screen bg-slate-950 text-white"
>
    <header
        class="border-b border-white/10 bg-slate-950/80 backdrop-blur-xl"
    >
        <div
            class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5"
        >
            <a
                href="dashboard.php"
                class="inline-flex items-center gap-3 font-bold text-slate-300 hover:text-white"
            >
                <span
                    class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5"
                >
                    <i
                        data-lucide="arrow-left"
                        class="h-5 w-5"
                    ></i>
                </span>

                Kembali ke Dashboard
            </a>

            <div class="text-right">
                <p class="font-bold">
                    API Configuration
                </p>

                <p class="text-xs text-slate-500">
                    OAuth dan integrasi platform
                </p>
            </div>
        </div>
    </header>

    <main
        class="mx-auto max-w-6xl px-5 py-12"
    >
        <section
            class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-indigo-600/25 via-slate-900 to-cyan-500/15 p-8"
        >
            <div
                class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"
            ></div>

            <p
                class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300"
            >
                Integration Center
            </p>

            <h1
                class="mt-3 text-3xl font-extrabold sm:text-5xl"
            >
                API Configuration
            </h1>

            <p
                class="mt-4 max-w-2xl leading-7 text-slate-300"
            >
                Kelola koneksi OAuth, status kredensial,
                dan akun platform yang terhubung.
            </p>
        </section>

        <section
            class="mt-8 overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/[.05]"
        >
            <div
                class="flex flex-col justify-between gap-4 border-b border-white/10 p-6 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-4">
                    <span
                        class="grid h-14 w-14 place-items-center rounded-2xl bg-red-500/10 text-red-300"
                    >
                        <i
                            data-lucide="youtube"
                            class="h-7 w-7"
                        ></i>
                    </span>

                    <div>
                        <h2 class="text-xl font-extrabold">
                            YouTube Data API v3
                        </h2>

                        <p class="mt-1 text-sm text-slate-400">
                            OAuth 2.0 Web Application
                        </p>
                    </div>
                </div>

                <?php if ($youtubeApiReady): ?>
                    <span
                        class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-4 py-2 text-xs font-bold text-emerald-300"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-emerald-400"
                        ></span>

                        Configuration ready
                    </span>
                <?php else: ?>
                    <span
                        class="inline-flex w-fit items-center gap-2 rounded-full border border-amber-400/20 bg-amber-400/10 px-4 py-2 text-xs font-bold text-amber-300"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-amber-400"
                        ></span>

                        Configuration incomplete
                    </span>
                <?php endif; ?>
            </div>

            <div
                class="grid gap-4 p-6 md:grid-cols-2"
            >
                <div
                    class="rounded-2xl border border-white/10 bg-slate-950/40 p-5"
                >
                    <p class="text-xs uppercase text-slate-500">
                        Client ID
                    </p>

                    <p
                        class="mt-2 font-bold <?= $clientIdReady
                            ? 'text-emerald-300'
                            : 'text-rose-300'
                        ?>"
                    >
                        <?= $clientIdReady
                            ? 'Configured'
                            : 'Not configured'
                        ?>
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-white/10 bg-slate-950/40 p-5"
                >
                    <p class="text-xs uppercase text-slate-500">
                        Client Secret
                    </p>

                    <p
                        class="mt-2 font-bold <?= $clientSecretReady
                            ? 'text-emerald-300'
                            : 'text-rose-300'
                        ?>"
                    >
                        <?= $clientSecretReady
                            ? 'Configured securely'
                            : 'Not configured'
                        ?>
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-white/10 bg-slate-950/40 p-5 md:col-span-2"
                >
                    <p class="text-xs uppercase text-slate-500">
                        Authorized Redirect URI
                    </p>

                    <p
                        class="mt-2 break-all text-sm font-semibold text-cyan-300"
                    >
                        <?= e(
                            $redirectUri !== ''
                                ? $redirectUri
                                : 'Belum diatur'
                        ) ?>
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-white/10 bg-slate-950/40 p-5"
                >
                    <p class="text-xs uppercase text-slate-500">
                        Connected channels
                    </p>

                    <p
                        class="mt-2 text-3xl font-extrabold text-violet-300"
                    >
                        <?= number_format(
                            $accountCount,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-white/10 bg-slate-950/40 p-5"
                >
                    <p class="text-xs uppercase text-slate-500">
                        Requested access
                    </p>

                    <p class="mt-2 font-bold text-slate-200">
                        YouTube Read Only
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-white/10 p-6 sm:flex-row"
            >
                <?php if ($youtubeApiReady): ?>
                    <a
                        href="connectors/youtube/connect.php"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-600 to-red-500 px-5 py-3 font-bold text-white hover:brightness-110"
                    >
                        <i
                            data-lucide="log-in"
                            class="h-5 w-5"
                        ></i>

                        Login with Google
                    </a>
                <?php else: ?>
                    <button
                        type="button"
                        disabled
                        class="cursor-not-allowed rounded-xl bg-slate-700 px-5 py-3 font-bold text-slate-400"
                    >
                        Lengkapi konfigurasi dahulu
                    </button>
                <?php endif; ?>

                <a
                    href="accounts/index.php"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-5 py-3 font-bold text-slate-200 hover:bg-white/10"
                >
                    <i
                        data-lucide="users"
                        class="h-5 w-5"
                    ></i>

                    Lihat akun terhubung
                </a>
            </div>
        </section>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>