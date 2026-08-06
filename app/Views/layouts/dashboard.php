<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3f4ef">
    <title><?= e($pageTitle ?? 'Dashboard') ?> | <?= e($app['name']) ?></title>
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
<body class="min-h-[100dvh] bg-[var(--surface-soft)] text-[var(--ink)] antialiased">
    <a class="skip-link" href="#dashboard-content">Skip to content</a>
    <div class="min-h-[100dvh] lg:grid lg:grid-cols-[264px_1fr]">
        <aside class="dashboard-sidebar" data-dashboard-sidebar>
            <div class="flex h-[72px] items-center justify-between">
                <a class="brand-mark" href="/" aria-label="OEMS home">
                    <span class="brand-mark__symbol" aria-hidden="true">O</span>
                    <span>OEMS</span>
                </a>
                <button class="menu-button lg:hidden" type="button" data-dashboard-close aria-label="Close navigation">Close</button>
            </div>
            <div class="mt-6">
                <p class="dashboard-sidebar__label">Workspace</p>
                <nav class="mt-3 grid gap-1" aria-label="Dashboard navigation">
                    <a class="dashboard-nav-link dashboard-nav-link--active" href="/dashboard">Overview</a>
                    <a class="dashboard-nav-link" href="/events">Explore events</a>
                    <a class="dashboard-nav-link" href="/settings/password">Security</a>
                </nav>
            </div>
            <div class="mt-auto border-t border-[var(--line)] pt-5">
                <p class="truncate text-sm font-semibold"><?= e($currentUser['name'] ?? 'OEMS user') ?></p>
                <p class="mt-1 truncate text-xs text-[var(--ink-muted)]"><?= e($currentUser['email'] ?? '') ?></p>
                <form action="/logout" method="post" class="mt-4">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="button button--quiet w-full" type="submit">Log out</button>
                </form>
            </div>
        </aside>

        <div class="min-w-0 lg:col-start-2">
            <header class="dashboard-header">
                <button class="menu-button lg:hidden" type="button" data-dashboard-open aria-label="Open navigation">Menu</button>
                <div class="ml-auto flex items-center gap-3">
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Change color theme">Theme</button>
                    <span class="role-badge"><?= e($currentUser['role_name'] ?? 'Member') ?></span>
                </div>
            </header>

            <main id="dashboard-content" class="px-5 py-7 sm:px-8 lg:px-10 lg:py-10">
                <div class="mx-auto max-w-[1280px]">
                    <?php require base_path('app/Views/components/flash.php'); ?>
                    <?= $content ?>
                </div>
            </main>
        </div>
    </div>
    <div class="dashboard-overlay" data-dashboard-overlay hidden></div>
</body>
</html>
