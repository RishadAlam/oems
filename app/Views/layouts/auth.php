<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3f4ef">
    <title><?= e($pageTitle ?? 'Account') ?> | <?= e($app['name']) ?></title>
    <script>
        (function () {
            const saved = localStorage.getItem('oems-theme');
            const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.dataset.theme = saved || (dark ? 'dark' : 'light');
        }());
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body class="min-h-[100dvh] bg-[var(--surface)] text-[var(--ink)] antialiased">
    <a class="skip-link" href="#auth-content">Skip to form</a>
    <div class="grid min-h-[100dvh] lg:grid-cols-[minmax(360px,0.9fr)_minmax(520px,1.1fr)]">
        <aside class="auth-visual hidden lg:flex">
            <a class="brand-mark brand-mark--inverse" href="/" aria-label="OEMS home">
                <span class="brand-mark__symbol" aria-hidden="true">O</span>
                <span>OEMS</span>
            </a>
            <div class="relative z-10 max-w-md">
                <p class="text-3xl font-semibold leading-tight tracking-[-0.03em] text-white">
                    Good events begin before anyone enters the room.
                </p>
                <p class="mt-4 max-w-sm text-sm leading-6 text-white/75">
                    Build the account that helps you discover, organize, and remember what matters.
                </p>
            </div>
        </aside>

        <main id="auth-content" class="flex min-h-[100dvh] flex-col">
            <header class="flex h-[72px] items-center justify-between px-5 sm:px-8 lg:px-12">
                <a class="brand-mark lg:hidden" href="/" aria-label="OEMS home">
                    <span class="brand-mark__symbol" aria-hidden="true">O</span>
                    <span>OEMS</span>
                </a>
                <a class="nav-link ml-auto" href="/">Back to events</a>
            </header>
            <div class="mx-auto flex w-full max-w-[520px] flex-1 flex-col justify-center px-5 py-10 sm:px-8">
                <?php require base_path('app/Views/components/flash.php'); ?>
                <?= $content ?>
            </div>
        </main>
    </div>
</body>
</html>

