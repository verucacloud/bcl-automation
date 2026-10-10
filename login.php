<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mode debugging sementara
|--------------------------------------------------------------------------
| Setelah halaman berhasil dibuka, ubah display_errors menjadi 0.
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

/*
|--------------------------------------------------------------------------
| Jalankan session
|--------------------------------------------------------------------------
| login.php berada di folder utama.
| session.php berada di folder auth.
*/

$sessionFile = __DIR__ . '/auth/session.php';

if (!is_file($sessionFile)) {
    http_response_code(500);

    exit(
        'File session tidak ditemukan pada lokasi: ' .
        htmlspecialchars($sessionFile, ENT_QUOTES, 'UTF-8')
    );
}

require_once $sessionFile;

/*
|--------------------------------------------------------------------------
| Jika sudah login, arahkan langsung ke dashboard
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['user_id']) &&
    is_numeric($_SESSION['user_id']) &&
    (int) $_SESSION['user_id'] > 0
) {
    header('Location: dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Pesan status
|--------------------------------------------------------------------------
*/

$message = '';

if (isset($_GET['expired'])) {
    $message = 'Sesi Anda telah berakhir. Silakan masuk kembali.';
}

if (isset($_GET['logout'])) {
    $message = 'Anda berhasil keluar dari aplikasi.';
}

if (isset($_GET['error'])) {
    $message = 'Login gagal. Silakan coba kembali.';
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

?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="theme-color" content="#020617">

    <title>Login - Social Intelligence</title>

    <meta
        name="description"
        content="Masuk ke Social Intelligence untuk mengakses dashboard analytics dan automation."
    >

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-full bg-slate-950 text-white antialiased">
    <div
        class="fixed inset-0 -z-10"
        aria-hidden="true"
        style="
            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(99, 102, 241, 0.22),
                    transparent 28rem
                ),
                radial-gradient(
                    circle at 85% 80%,
                    rgba(34, 211, 238, 0.16),
                    transparent 28rem
                ),
                #020617;
        "
    ></div>

    <main class="grid min-h-screen place-items-center px-4 py-10">
        <section
            class="w-full max-w-md rounded-3xl border border-white/10 bg-white/[0.06] p-7 shadow-2xl backdrop-blur-xl sm:p-9"
        >
            <div
                class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-cyan-500 text-2xl font-extrabold shadow-lg"
                aria-hidden="true"
            >
                SI
            </div>

            <div class="mt-6 text-center">
                <p
                    class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-300"
                >
                    Social Media Platform
                </p>

                <h1 class="mt-3 text-3xl font-extrabold tracking-tight">
                    Social Intelligence
                </h1>

                <p class="mt-3 text-sm leading-6 text-slate-400">
                    Masuk untuk mengakses dashboard analytics,
                    monitoring, dan automation.
                </p>
            </div>

            <?php if ($message !== ''): ?>
                <div
                    class="mt-6 rounded-2xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-sm leading-6 text-amber-200"
                    role="alert"
                >
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <a
                href="auth/google.php"
                class="mt-8 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 font-bold text-slate-900 transition duration-200 hover:-translate-y-0.5 hover:bg-slate-200 focus:outline-none focus:ring-4 focus:ring-indigo-500/30"
            >
                <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        fill="#4285F4"
                        d="M21.6 12.23c0-.71-.06-1.24-.2-1.79H12v3.4h5.52a4.72 4.72 0 0 1-2.05 3.09v2.21h3.32c1.95-1.8 3.07-4.45 3.07-7.58 0-.46-.04-.89-.26-1.33z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 22c2.78 0 5.12-.92 6.83-2.5l-3.32-2.57c-.92.62-2.1.99-3.51.99-2.68 0-4.95-1.81-5.77-4.25H2.8v2.65A10.31 10.31 0 0 0 12 22z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M6.23 13.67A6.2 6.2 0 0 1 5.9 12c0-.58.1-1.14.3-1.67V7.68H2.8A10.1 10.1 0 0 0 1.72 12c0 1.55.37 3.02 1.08 4.32l3.43-2.65z"
                    />
                    <path
                        fill="#EA4335"
                        d="M12 6.08c1.51 0 2.87.52 3.94 1.54l2.95-2.95C17.11 3.01 14.78 2 12 2a10.31 10.31 0 0 0-9.2 5.68l3.4 2.65C7.05 7.89 9.32 6.08 12 6.08z"
                    />
                </svg>

                <span>Masuk dengan Google</span>
            </a>

            <p class="mt-6 text-center text-xs leading-5 text-slate-500">
                Dengan masuk, Anda menyetujui penggunaan akun Google
                untuk proses autentikasi aplikasi.
            </p>
        </section>
    </main>
</body>
</html>