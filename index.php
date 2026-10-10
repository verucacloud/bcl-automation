<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>BCL Entertainment | Creative Agency & Talent Management</title>
  <meta name="description" content="BCL Entertainment menghubungkan brand, talent, influencer, dan audiens melalui talent management, digital campaign, content creation, event, serta brand partnership." />
  <meta name="theme-color" content="#061a34" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" />

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#eef7ff',
              100: '#d9edff',
              200: '#bce0ff',
              300: '#8ecbff',
              400: '#59adff',
              500: '#338cf5',
              600: '#1e6dd8',
              700: '#1957ae',
              800: '#194b8a',
              900: '#173f70',
              950: '#061a34'
            },
            gold: '#f6c915',
            emeraldx: '#22b573',
            canvas: '#f8f7f2'
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            display: ['Plus Jakarta Sans', 'sans-serif']
          },
          boxShadow: {
            glow: '0 24px 80px rgba(30, 109, 216, 0.24)',
            soft: '0 20px 60px rgba(6, 26, 52, 0.10)'
          },
          keyframes: {
            float: {
              '0%, 100%': { transform: 'translateY(0px)' },
              '50%': { transform: 'translateY(-14px)' }
            },
            drift: {
              '0%, 100%': { transform: 'translate3d(0,0,0) scale(1)' },
              '50%': { transform: 'translate3d(35px,-25px,0) scale(1.12)' }
            },
            shimmer: {
              '0%': { backgroundPosition: '200% center' },
              '100%': { backgroundPosition: '-200% center' }
            },
            pulseRing: {
              '0%': { transform: 'scale(.85)', opacity: '.75' },
              '100%': { transform: 'scale(1.8)', opacity: '0' }
            }
          },
          animation: {
            float: 'float 6s ease-in-out infinite',
            drift: 'drift 10s ease-in-out infinite',
            shimmer: 'shimmer 6s linear infinite',
            pulseRing: 'pulseRing 1.8s ease-out infinite'
          }
        }
      }
    };
  </script>

  <style>
    :root { color-scheme: light; }
    body { background: #f8f7f2; }
    ::selection { background: #f6c915; color: #061a34; }

    .glass {
      background: rgba(255,255,255,.72);
      border: 1px solid rgba(255,255,255,.72);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
    }

    .gradient-text {
      background: linear-gradient(100deg, #1e6dd8 0%, #0b4f9e 42%, #22b573 100%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .reveal {
      opacity: 0;
      transform: translateY(28px);
      transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1);
    }
    .reveal.is-visible { opacity: 1; transform: translateY(0); }

    .card-tilt {
      transform-style: preserve-3d;
      transition: transform .25s ease, box-shadow .3s ease;
    }

    .grid-pattern {
      background-image:
        linear-gradient(rgba(30,109,216,.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(30,109,216,.07) 1px, transparent 1px);
      background-size: 34px 34px;
      mask-image: linear-gradient(to bottom, black, transparent 92%);
      -webkit-mask-image: linear-gradient(to bottom, black, transparent 92%);
    }

    .marquee-track { animation: marquee 28s linear infinite; }
    @keyframes marquee { to { transform: translateX(-50%); } }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
      }
      .reveal { opacity: 1; transform: none; }
    }
  </style>
</head>

<body class="overflow-x-hidden font-sans text-slate-900 antialiased">
  <!-- Decorative background -->
  <div class="pointer-events-none fixed inset-0 -z-50 overflow-hidden">
    <div class="absolute -left-32 -top-32 h-[34rem] w-[34rem] rounded-full bg-gold/20 blur-3xl animate-drift"></div>
    <div class="absolute -right-36 top-24 h-[36rem] w-[36rem] rounded-full bg-brand-300/25 blur-3xl animate-drift [animation-delay:2s]"></div>
  </div>

  <!-- Navbar -->
  <header id="navbar" class="fixed inset-x-0 top-0 z-50 px-3 pt-3 transition-all duration-500 sm:px-5 sm:pt-5">
    <nav class="glass mx-auto flex max-w-7xl items-center justify-between rounded-2xl px-4 py-3 shadow-soft transition-all duration-500 sm:px-6" aria-label="Navigasi utama">
      <a href="#home" class="group flex items-center gap-3" aria-label="BCL Entertainment">
        <span class="grid h-11 w-14 place-items-center overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200/70 transition group-hover:-rotate-2 group-hover:scale-105">
          <img src="assets/logo.PNG" alt="Logo BCL Entertainment" class="h-9 w-12 object-contain" />
        </span>
        <span class="hidden sm:block">
          <strong class="block font-display text-sm font-extrabold tracking-tight text-brand-950">BCL Entertainment</strong>
          <span class="block text-[10px] font-bold uppercase tracking-[.2em] text-slate-500">Build Creative Life</span>
        </span>
      </a>

      <div class="hidden items-center gap-1 lg:flex">
        <a href="#home" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700">Home</a>
        <a href="#tentang" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700">Tentang</a>
        <a href="#layanan" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700">Layanan</a>
        <a href="#keunggulan" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700">Keunggulan</a>
        <a href="#kontak" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700">Kontak</a>
      </div>

      <div class="flex items-center gap-2">
        <a href="dashboard.php" class="hidden items-center gap-2 rounded-xl bg-brand-950 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-950/20 transition hover:-translate-y-0.5 hover:bg-brand-800 sm:inline-flex">
          Dashboard <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
        </a>
        <button id="menuButton" type="button" class="grid h-11 w-11 place-items-center rounded-xl bg-white text-brand-950 ring-1 ring-slate-200 transition hover:bg-brand-50 lg:hidden" aria-label="Buka menu" aria-controls="mobileMenu" aria-expanded="false">
          <i id="menuIcon" data-lucide="menu" class="h-5 w-5"></i>
        </button>
      </div>
    </nav>

    <div id="mobileMenu" class="glass mx-auto mt-2 hidden max-w-7xl rounded-2xl p-3 shadow-soft lg:hidden">
      <div class="grid gap-1">
        <a href="#home" class="mobile-link rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Home</a>
        <a href="#tentang" class="mobile-link rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Tentang</a>
        <a href="#layanan" class="mobile-link rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Layanan</a>
        <a href="#keunggulan" class="mobile-link rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Keunggulan</a>
        <a href="#kontak" class="mobile-link rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Kontak</a>
        <a href="dashboard.php" class="mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-950 px-4 py-3 font-bold text-white">Masuk Dashboard <i data-lucide="arrow-up-right" class="h-4 w-4"></i></a>
      </div>
    </div>
  </header>

  <main>
    <!-- Hero -->
    <section id="home" class="relative min-h-screen overflow-hidden pb-20 pt-36 sm:pt-40 lg:flex lg:items-center lg:pb-28">
      <div class="grid-pattern absolute inset-x-0 top-0 -z-20 h-[82%]"></div>
      <div class="absolute left-[8%] top-[22%] -z-10 h-2 w-2 rounded-full bg-emeraldx"><span class="absolute inset-0 rounded-full bg-emeraldx animate-pulseRing"></span></div>

      <div class="mx-auto grid w-full max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[1.06fr_.94fr] lg:px-10">
        <div class="reveal">
          <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/75 px-3.5 py-2 text-xs font-extrabold uppercase tracking-[.16em] text-brand-700 shadow-sm backdrop-blur">
            <span class="relative flex h-2.5 w-2.5">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emeraldx opacity-70"></span>
              <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emeraldx"></span>
            </span>
            Creative Agency & Talent Management
          </div>

          <h1 class="max-w-4xl font-display text-[clamp(2.65rem,7vw,5.5rem)] font-extrabold leading-[1.02] tracking-[-.045em] text-brand-950">
            Ide kreatif yang berubah menjadi <span class="gradient-text">impact nyata.</span>
          </h1>

          <p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
            BCL Entertainment menyatukan brand, talent, influencer, kreator, dan audiens melalui strategi, produksi, aktivasi, serta kolaborasi yang terukur dalam satu ekosistem kreatif.
          </p>

          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="#layanan" class="group inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-brand-950 px-6 font-bold text-white shadow-glow transition hover:-translate-y-1 hover:bg-brand-800">
              Eksplorasi Layanan
              <i data-lucide="arrow-right" class="h-5 w-5 transition-transform group-hover:translate-x-1"></i>
            </a>
            <a href="https://wa.me/6287810999998?text=Halo%20BCL%20Entertainment%2C%20saya%20ingin%20berdiskusi%20mengenai%20layanan%20Anda." target="_blank" rel="noopener noreferrer" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-6 font-bold text-brand-950 shadow-sm backdrop-blur transition hover:-translate-y-1 hover:border-brand-300 hover:bg-brand-50">
              <i data-lucide="message-circle" class="h-5 w-5 text-emeraldx"></i>
              Mulai Diskusi
            </a>
          </div>

          <div class="mt-10 grid max-w-2xl grid-cols-3 gap-3">
            <div class="rounded-2xl border border-white/80 bg-white/70 p-4 shadow-sm backdrop-blur">
              <strong class="block font-display text-2xl font-extrabold text-brand-700 sm:text-3xl">9+</strong>
              <span class="text-xs font-semibold text-slate-500 sm:text-sm">Layanan kreatif</span>
            </div>
            <div class="rounded-2xl border border-white/80 bg-white/70 p-4 shadow-sm backdrop-blur">
              <strong class="block font-display text-2xl font-extrabold text-brand-700 sm:text-3xl">360°</strong>
              <span class="text-xs font-semibold text-slate-500 sm:text-sm">Campaign support</span>
            </div>
            <div class="rounded-2xl border border-white/80 bg-white/70 p-4 shadow-sm backdrop-blur">
              <strong class="block font-display text-2xl font-extrabold text-brand-700 sm:text-3xl">1</strong>
              <span class="text-xs font-semibold text-slate-500 sm:text-sm">Creative ecosystem</span>
            </div>
          </div>
        </div>

        <div class="reveal relative mx-auto w-full max-w-xl lg:max-w-none">
          <div class="absolute -left-10 top-16 h-40 w-40 rounded-full bg-gold/25 blur-3xl"></div>
          <div class="absolute -right-12 bottom-4 h-52 w-52 rounded-full bg-brand-400/25 blur-3xl"></div>

          <div id="heroCard" class="card-tilt relative overflow-hidden rounded-[2rem] border border-white/70 bg-brand-950 p-5 shadow-[0_40px_100px_rgba(6,26,52,.25)] sm:p-7">
            <div class="absolute inset-0 opacity-60 [background:radial-gradient(circle_at_90%_5%,rgba(246,201,21,.35),transparent_24%),radial-gradient(circle_at_5%_95%,rgba(34,181,115,.22),transparent_28%)]"></div>
            <div class="relative">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <span class="grid h-12 w-16 place-items-center rounded-xl bg-white p-2 shadow-lg">
                    <img src="assets/logo.PNG" alt="Logo BCL" class="h-full w-full object-contain" />
                  </span>
                  <div>
                    <p class="font-display text-sm font-bold text-white">BCL Creative Hub</p>
                    <p class="text-xs text-white/55">Integrated agency workspace</p>
                  </div>
                </div>
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.18em] text-emerald-300">Live</span>
              </div>

              <div class="mt-8 rounded-2xl border border-white/10 bg-white/[.07] p-4 backdrop-blur-sm sm:p-5">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[.18em] text-white/45">Campaign Performance</p>
                    <p class="mt-2 font-display text-3xl font-extrabold text-white sm:text-4xl">+42.8%</p>
                    <p class="mt-1 text-sm text-emerald-300">↑ Growth this month</p>
                  </div>
                  <span class="grid h-11 w-11 place-items-center rounded-xl bg-gold text-brand-950"><i data-lucide="trending-up" class="h-5 w-5"></i></span>
                </div>
                <div class="mt-6 flex h-28 items-end gap-2" aria-label="Grafik performa ilustratif">
                  <span class="h-[36%] flex-1 rounded-t-md bg-white/15"></span>
                  <span class="h-[48%] flex-1 rounded-t-md bg-white/20"></span>
                  <span class="h-[43%] flex-1 rounded-t-md bg-white/15"></span>
                  <span class="h-[68%] flex-1 rounded-t-md bg-brand-400"></span>
                  <span class="h-[58%] flex-1 rounded-t-md bg-brand-300"></span>
                  <span class="h-[84%] flex-1 rounded-t-md bg-gold"></span>
                  <span class="h-full flex-1 rounded-t-md bg-emeraldx"></span>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-white p-4 text-brand-950 shadow-xl">
                  <div class="flex items-center justify-between">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-50 text-brand-700"><i data-lucide="users" class="h-4 w-4"></i></span>
                    <span class="text-xs font-bold text-emerald-600">+18%</span>
                  </div>
                  <p class="mt-4 text-xs font-semibold text-slate-400">Talent Reach</p>
                  <p class="font-display text-xl font-extrabold">8.7M</p>
                </div>
                <div class="rounded-2xl bg-gold p-4 text-brand-950 shadow-xl">
                  <div class="flex items-center justify-between">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-950 text-white"><i data-lucide="sparkles" class="h-4 w-4"></i></span>
                    <span class="text-xs font-bold">Active</span>
                  </div>
                  <p class="mt-4 text-xs font-semibold text-brand-950/60">Creative Projects</p>
                  <p class="font-display text-xl font-extrabold">24</p>
                </div>
              </div>
            </div>
          </div>

          <div class="absolute -left-4 top-[22%] hidden animate-float rounded-2xl border border-white bg-white/90 px-4 py-3 shadow-soft backdrop-blur sm:flex sm:items-center sm:gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="user-star" class="h-4 w-4"></i></span>
            <div><strong class="block text-xs text-brand-950">Talent Management</strong><span class="text-[11px] text-slate-500">Curated & professional</span></div>
          </div>

          <div class="absolute -right-4 bottom-[18%] hidden animate-float rounded-2xl border border-white bg-white/90 px-4 py-3 shadow-soft backdrop-blur [animation-delay:1.2s] sm:flex sm:items-center sm:gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="rocket" class="h-4 w-4"></i></span>
            <div><strong class="block text-xs text-brand-950">Brand Partnership</strong><span class="text-[11px] text-slate-500">Relevant & impactful</span></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Marquee -->
    <section class="overflow-hidden border-y border-slate-200/80 bg-white py-5">
      <div class="marquee-track flex w-max items-center gap-10 whitespace-nowrap pr-10 text-sm font-extrabold uppercase tracking-[.16em] text-brand-950/65">
        <div class="flex items-center gap-10">
          <span>Talent Management</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Influencer Marketing</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Digital Campaign</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Content Creation</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Event Management</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Brand Partnership</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
        </div>
        <div class="flex items-center gap-10" aria-hidden="true">
          <span>Talent Management</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Influencer Marketing</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Digital Campaign</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Content Creation</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Event Management</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
          <span>Brand Partnership</span><i data-lucide="sparkles" class="h-4 w-4 text-gold"></i>
        </div>
      </div>
    </section>

    <!-- About -->
    <section id="tentang" class="py-20 sm:py-28">
      <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:gap-20 lg:px-10">
        <div class="reveal relative">
          <div class="overflow-hidden rounded-[2rem] bg-brand-950 p-7 shadow-soft sm:p-10">
            <div class="absolute right-0 top-0 h-36 w-36 rounded-full bg-gold/25 blur-3xl"></div>
            <div class="relative">
              <span class="inline-flex rounded-2xl bg-white p-3 shadow-lg"><img src="assets/logo.PNG" alt="BCL Entertainment" class="h-14 w-20 object-contain" /></span>
              <p class="mt-14 text-xs font-bold uppercase tracking-[.24em] text-gold">Our Purpose</p>
              <h2 class="mt-4 font-display text-3xl font-extrabold leading-tight text-white sm:text-4xl">Mendorong pertumbuhan brand dan talent melalui kreativitas yang relevan.</h2>
              <p class="mt-5 leading-7 text-white/65">Kami menggabungkan strategi, jaringan, kreativitas, dan eksekusi untuk membangun hubungan yang kuat antara brand, talent, serta audiens.</p>
              <div class="mt-8 grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-white/10 bg-white/[.07] p-4"><i data-lucide="target" class="h-5 w-5 text-gold"></i><p class="mt-3 text-sm font-bold text-white">Strategi terarah</p></div>
                <div class="rounded-2xl border border-white/10 bg-white/[.07] p-4"><i data-lucide="handshake" class="h-5 w-5 text-emerald-300"></i><p class="mt-3 text-sm font-bold text-white">Kolaborasi kuat</p></div>
              </div>
            </div>
          </div>
        </div>

        <div class="reveal">
          <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[.2em] text-brand-700"><span class="h-1 w-7 rounded-full bg-gold"></span>Tentang BCL</p>
          <h2 class="mt-4 font-display text-4xl font-extrabold tracking-tight text-brand-950 sm:text-5xl">Satu partner untuk seluruh perjalanan kreatif.</h2>
          <p class="mt-6 text-base leading-8 text-slate-600">BCL Entertainment bergerak di bidang talent management, influencer marketing, event organizer, creative production, digital campaign, dan brand partnership.</p>
          <p class="mt-4 text-base leading-8 text-slate-600">Setiap program dirancang adaptif berdasarkan tujuan klien, mulai dari kurasi talent dan penyusunan konsep hingga produksi, aktivasi, serta evaluasi hasil.</p>

          <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="check" class="h-4 w-4"></i></span><p class="font-semibold text-slate-700">Talent profesional dan berkarakter</p></div>
            <div class="flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="check" class="h-4 w-4"></i></span><p class="font-semibold text-slate-700">Strategi sesuai identitas brand</p></div>
            <div class="flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600"><i data-lucide="check" class="h-4 w-4"></i></span><p class="font-semibold text-slate-700">Kolaborasi berkelanjutan</p></div>
            <div class="flex gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="check" class="h-4 w-4"></i></span><p class="font-semibold text-slate-700">Campaign terukur dan berdampak</p></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Services -->
    <section id="layanan" class="relative overflow-hidden bg-white py-20 sm:py-28">
      <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-brand-50/70 to-transparent"></div>
      <div class="relative mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
        <div class="reveal max-w-3xl">
          <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[.2em] text-brand-700"><span class="h-1 w-7 rounded-full bg-gold"></span>Layanan Kami</p>
          <h2 class="mt-4 font-display text-4xl font-extrabold tracking-tight text-brand-950 sm:text-5xl">Solusi lengkap untuk ide yang lebih besar.</h2>
          <p class="mt-5 text-lg leading-8 text-slate-600">Dari pengelolaan talent hingga produksi kampanye, kami merancang solusi kreatif yang relevan dengan kebutuhan dan target audiens Anda.</p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-950 text-white transition group-hover:rotate-6"><i data-lucide="user-round-star" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">01</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Talent Management</h3><p class="mt-3 leading-7 text-slate-600">Pengembangan talent, pengelolaan jadwal, personal branding, dan kolaborasi komersial secara profesional.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-gold text-brand-950 transition group-hover:rotate-6"><i data-lucide="megaphone" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">02</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Influencer Marketing</h3><p class="mt-3 leading-7 text-slate-600">Menghubungkan brand dengan influencer yang relevan untuk menciptakan kampanye autentik dan efektif.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-emeraldx text-white transition group-hover:rotate-6"><i data-lucide="handshake" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">03</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Partnership Collaboration</h3><p class="mt-3 leading-7 text-slate-600">Kolaborasi strategis antara brand, talent, dan mitra untuk menciptakan nilai tambah jangka panjang.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-600 text-white transition group-hover:rotate-6"><i data-lucide="calendar-check" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">04</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Event Management</h3><p class="mt-3 leading-7 text-slate-600">Pengelolaan event end-to-end, dari pengembangan konsep, produksi, koordinasi, hingga evaluasi.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-rose-500 text-white transition group-hover:rotate-6"><i data-lucide="clapperboard" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">05</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Content Creation</h3><p class="mt-3 leading-7 text-slate-600">Produksi konten visual dan naratif yang menarik, konsisten, dan selaras dengan identitas brand.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-sky-500 text-white transition group-hover:rotate-6"><i data-lucide="chart-no-axes-combined" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">06</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Digital Campaign</h3><p class="mt-3 leading-7 text-slate-600">Strategi kampanye digital terintegrasi untuk meningkatkan awareness, engagement, dan hasil yang terukur.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-orange-500 text-white transition group-hover:rotate-6"><i data-lucide="radio" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">07</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Social Media Activation</h3><p class="mt-3 leading-7 text-slate-600">Aktivasi digital yang interaktif untuk memperkuat hubungan brand dengan komunitas dan audiens.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-500 text-white transition group-hover:rotate-6"><i data-lucide="rocket" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">08</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Product Launching</h3><p class="mt-3 leading-7 text-slate-600">Peluncuran produk yang kreatif dan berkesan untuk menarik perhatian serta memperkuat citra brand.</p>
          </article>
          <article class="service-card reveal group rounded-[1.5rem] border border-slate-200 bg-canvas p-6 transition duration-500 hover:-translate-y-2 hover:border-brand-200 hover:bg-white hover:shadow-soft">
            <div class="flex items-center justify-between"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-500 text-white transition group-hover:rotate-6"><i data-lucide="camera" class="h-5 w-5"></i></span><span class="font-display text-sm font-extrabold text-slate-300">09</span></div>
            <h3 class="mt-7 font-display text-xl font-extrabold text-brand-950">Model Photoshoot</h3><p class="mt-3 leading-7 text-slate-600">Model profesional dan pengarahan visual untuk katalog, lookbook, kampanye, serta materi promosi.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Why us -->
    <section id="keunggulan" class="py-20 sm:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
        <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
          <div class="reveal">
            <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[.2em] text-brand-700"><span class="h-1 w-7 rounded-full bg-gold"></span>Mengapa BCL</p>
            <h2 class="mt-4 font-display text-4xl font-extrabold tracking-tight text-brand-950 sm:text-5xl">Kreatif, strategis, dan siap dieksekusi.</h2>
          </div>
          <p class="reveal text-lg leading-8 text-slate-600">Kami tidak berhenti pada ide. Setiap konsep dikembangkan menjadi strategi dan eksekusi yang jelas agar menghasilkan pengalaman bermakna bagi brand, talent, dan audiens.</p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
          <div class="reveal rounded-[1.5rem] bg-brand-950 p-6 text-white shadow-soft"><i data-lucide="layers-3" class="h-7 w-7 text-gold"></i><h3 class="mt-6 font-display text-xl font-extrabold">Terintegrasi</h3><p class="mt-3 text-sm leading-7 text-white/60">Strategi, talent, produksi, dan aktivasi dikelola dalam satu alur kerja.</p></div>
          <div class="reveal rounded-[1.5rem] bg-white p-6 ring-1 ring-slate-200 shadow-sm"><i data-lucide="lightbulb" class="h-7 w-7 text-brand-700"></i><h3 class="mt-6 font-display text-xl font-extrabold text-brand-950">Adaptif</h3><p class="mt-3 text-sm leading-7 text-slate-600">Konsep disesuaikan dengan karakter brand dan perubahan perilaku audiens.</p></div>
          <div class="reveal rounded-[1.5rem] bg-gold p-6 text-brand-950 shadow-sm"><i data-lucide="chart-spline" class="h-7 w-7"></i><h3 class="mt-6 font-display text-xl font-extrabold">Terukur</h3><p class="mt-3 text-sm leading-7 text-brand-950/65">Setiap campaign diarahkan pada tujuan dan indikator performa yang jelas.</p></div>
          <div class="reveal rounded-[1.5rem] bg-white p-6 ring-1 ring-slate-200 shadow-sm"><i data-lucide="shield-check" class="h-7 w-7 text-emerald-600"></i><h3 class="mt-6 font-display text-xl font-extrabold text-brand-950">Profesional</h3><p class="mt-3 text-sm leading-7 text-slate-600">Komunikasi cepat, koordinasi rapi, dan eksekusi yang dapat diandalkan.</p></div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="pb-20 sm:pb-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
        <div class="reveal relative overflow-hidden rounded-[2rem] bg-brand-950 px-6 py-12 shadow-[0_40px_100px_rgba(6,26,52,.25)] sm:px-10 lg:px-14 lg:py-16">
          <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-gold/25 blur-3xl"></div>
          <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-emeraldx/20 blur-3xl"></div>
          <div class="relative flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
              <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs font-bold uppercase tracking-[.16em] text-gold"><i data-lucide="layout-dashboard" class="h-4 w-4"></i>Internal Workspace</span>
              <h2 class="mt-5 font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">Kelola aktivitas melalui dashboard BCL.</h2>
              <p class="mt-4 text-base leading-8 text-white/65">Pantau proyek, kelola profil talent, dan akses kebutuhan operasional dalam satu dashboard terpadu.</p>
            </div>
            <a href="dashboard.php" class="group inline-flex min-h-14 shrink-0 items-center justify-center gap-2 rounded-2xl bg-white px-6 font-bold text-brand-950 transition hover:-translate-y-1 hover:bg-gold">Buka Dashboard <i data-lucide="arrow-up-right" class="h-5 w-5 transition-transform group-hover:translate-x-1 group-hover:-translate-y-1"></i></a>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact -->
    <section id="kontak" class="bg-white py-20 sm:py-28">
      <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[.95fr_1.05fr] lg:gap-16 lg:px-10">
        <div class="reveal">
          <p class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-[.2em] text-brand-700"><span class="h-1 w-7 rounded-full bg-gold"></span>Hubungi Kami</p>
          <h2 class="mt-4 font-display text-4xl font-extrabold tracking-tight text-brand-950 sm:text-5xl">Mari mulai kolaborasi berikutnya.</h2>
          <p class="mt-5 text-lg leading-8 text-slate-600">Diskusikan kebutuhan brand, event, campaign, talent, atau produksi konten bersama tim BCL Entertainment.</p>

          <div class="mt-9 grid gap-3">
            <a href="https://wa.me/6287810999998" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:-translate-y-1 hover:border-emerald-200 hover:bg-emerald-50/50 hover:shadow-sm"><span class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="message-circle" class="h-5 w-5"></i></span><div><span class="block text-xs font-semibold text-slate-400">WhatsApp Business</span><strong class="text-sm text-brand-950 sm:text-base">0878 1099 9998 (Putri)</strong></div><i data-lucide="chevron-right" class="ml-auto h-5 w-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-500"></i></a>
            <a href="mailto:bcl.entertainment@bcl.co.id" class="group flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:-translate-y-1 hover:border-brand-200 hover:bg-brand-50/50 hover:shadow-sm"><span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="mail" class="h-5 w-5"></i></span><div class="min-w-0"><span class="block text-xs font-semibold text-slate-400">Email Resmi</span><strong class="block truncate text-sm text-brand-950 sm:text-base">bcl.entertainment@bcl.co.id</strong></div><i data-lucide="chevron-right" class="ml-auto h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-1 group-hover:text-brand-600"></i></a>
            <a href="https://instagram.com/BCLentertainment" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:-translate-y-1 hover:border-rose-200 hover:bg-rose-50/40 hover:shadow-sm"><span class="grid h-12 w-12 place-items-center rounded-xl bg-rose-50 text-rose-500"><i data-lucide="instagram" class="h-5 w-5"></i></span><div><span class="block text-xs font-semibold text-slate-400">Instagram</span><strong class="text-sm text-brand-950 sm:text-base">@BCLentertainment</strong></div><i data-lucide="chevron-right" class="ml-auto h-5 w-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-rose-500"></i></a>
            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 p-4"><span class="grid h-12 w-12 place-items-center rounded-xl bg-amber-50 text-amber-600"><i data-lucide="map-pin" class="h-5 w-5"></i></span><div><span class="block text-xs font-semibold text-slate-400">Lokasi</span><strong class="text-sm text-brand-950 sm:text-base">Purwakarta, Jawa Barat</strong></div></div>
          </div>
        </div>

        <aside class="reveal relative overflow-hidden rounded-[2rem] bg-canvas p-6 ring-1 ring-slate-200 sm:p-9">
          <div class="absolute right-0 top-0 h-40 w-40 rounded-full bg-brand-200/40 blur-3xl"></div>
          <div class="relative">
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-950 text-white shadow-lg"><i data-lucide="messages-square" class="h-6 w-6"></i></span>
            <h3 class="mt-8 font-display text-3xl font-extrabold text-brand-950">Punya ide atau kebutuhan khusus?</h3>
            <p class="mt-4 leading-8 text-slate-600">Ceritakan tujuan proyek Anda. Tim kami siap membantu merancang pendekatan yang paling relevan dan efektif.</p>
            <div class="mt-8 space-y-3 text-sm font-semibold text-slate-600">
              <p class="flex items-center gap-3"><i data-lucide="circle-check-big" class="h-5 w-5 text-emerald-600"></i>Konsultasi awal</p>
              <p class="flex items-center gap-3"><i data-lucide="circle-check-big" class="h-5 w-5 text-emerald-600"></i>Rekomendasi layanan</p>
              <p class="flex items-center gap-3"><i data-lucide="circle-check-big" class="h-5 w-5 text-emerald-600"></i>Perencanaan kolaborasi</p>
            </div>
            <a href="https://wa.me/6287810999998?text=Halo%20BCL%20Entertainment%2C%20saya%20ingin%20berdiskusi%20mengenai%20layanan%20Anda." target="_blank" rel="noopener noreferrer" class="mt-9 inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-brand-950 px-6 font-bold text-white shadow-lg shadow-brand-950/20 transition hover:-translate-y-1 hover:bg-brand-800"><i data-lucide="message-circle" class="h-5 w-5"></i>Hubungi via WhatsApp</a>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <footer class="bg-brand-950 py-8 text-white/60">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-5 text-center sm:px-8 md:flex-row md:text-left lg:px-10">
      <div class="flex items-center gap-3"><span class="grid h-10 w-14 place-items-center rounded-xl bg-white p-1.5"><img src="assets/logo.PNG" alt="Logo BCL" class="h-full w-full object-contain" /></span><div><strong class="block font-display text-sm text-white">BCL Entertainment</strong><span class="text-xs">Build Creative Life</span></div></div>
      <p class="text-xs sm:text-sm">© <span id="year"></span> BCL Entertainment. All rights reserved.</p>
      <a href="#home" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[.15em] text-white transition hover:text-gold">Kembali ke atas <i data-lucide="arrow-up" class="h-4 w-4"></i></a>
    </div>
  </footer>

  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      lucide.createIcons();

      const menuButton = document.getElementById('menuButton');
      const mobileMenu = document.getElementById('mobileMenu');
      let menuOpen = false;

      function setMenu(open) {
        menuOpen = open;
        mobileMenu.classList.toggle('hidden', !open);
        menuButton.setAttribute('aria-expanded', String(open));
        menuButton.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        menuButton.innerHTML = `<i data-lucide="${open ? 'x' : 'menu'}" class="h-5 w-5"></i>`;
        lucide.createIcons();
      }

      menuButton.addEventListener('click', () => setMenu(!menuOpen));
      document.querySelectorAll('.mobile-link').forEach(link => link.addEventListener('click', () => setMenu(false)));

      const navbar = document.getElementById('navbar');
      window.addEventListener('scroll', () => {
        const scrolled = window.scrollY > 24;
        navbar.classList.toggle('pt-0', scrolled);
        navbar.classList.toggle('sm:pt-0', scrolled);
      }, { passive: true });

      const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            obs.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

      document.querySelectorAll('.reveal').forEach((el, index) => {
        el.style.transitionDelay = `${Math.min(index % 4, 3) * 80}ms`;
        observer.observe(el);
      });

      const heroCard = document.getElementById('heroCard');
      const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
      if (heroCard && canHover) {
        heroCard.addEventListener('mousemove', (event) => {
          const rect = heroCard.getBoundingClientRect();
          const x = (event.clientX - rect.left) / rect.width - 0.5;
          const y = (event.clientY - rect.top) / rect.height - 0.5;
          heroCard.style.transform = `perspective(1000px) rotateY(${x * 6}deg) rotateX(${y * -6}deg) translateY(-4px)`;
        });
        heroCard.addEventListener('mouseleave', () => {
          heroCard.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg) translateY(0)';
        });
      }

      document.getElementById('year').textContent = new Date().getFullYear();
    });
  </script>
</body>
</html>