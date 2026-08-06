<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover and host memorable events with OEMS.">
    <meta name="theme-color" content="#f3f4ef">
    <title><?= e($pageTitle ?? $app['name']) ?> | <?= e($app['name']) ?></title>
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
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header">
        <div class="page-shell flex h-[72px] items-center justify-between gap-5">
            <a class="brand-mark" href="/" aria-label="OEMS home">
                <span class="brand-mark__symbol" aria-hidden="true">O</span>
                <span>OEMS</span>
            </a>

            <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary navigation">
                <a class="nav-link" href="/events">Explore events</a>
                <a class="nav-link" href="/register?role=organizer">For organizers</a>
                <a class="nav-link" href="#how-it-works">How it works</a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Change color theme">Theme</button>
                <?php if ($currentUser !== null): ?>
                    <a class="button button--primary button--compact" href="/dashboard">Dashboard</a>
                <?php else: ?>
                    <a class="button button--quiet button--compact" href="/login">Log in</a>
                    <a class="button button--primary button--compact" href="/register">Get started</a>
                <?php endif; ?>
            </div>

            <button
                class="menu-button lg:hidden"
                type="button"
                data-menu-toggle
                aria-expanded="false"
                aria-controls="mobile-menu"
            >
                <span>Menu</span>
                <span class="menu-button__lines" aria-hidden="true"><i></i><i></i></span>
            </button>
        </div>

        <div id="mobile-menu" class="mobile-menu" data-mobile-menu hidden>
            <nav class="page-shell grid gap-2 py-5" aria-label="Mobile navigation">
                <a class="mobile-menu__link" href="/events">Explore events</a>
                <a class="mobile-menu__link" href="/register?role=organizer">For organizers</a>
                <a class="mobile-menu__link" href="#how-it-works">How it works</a>
                <button class="mobile-menu__link text-left" type="button" data-theme-toggle>Change theme</button>
                <?php if ($currentUser !== null): ?>
                    <a class="button button--primary mt-3" href="/dashboard">Dashboard</a>
                <?php else: ?>
                    <a class="button button--quiet mt-3" href="/login">Log in</a>
                    <a class="button button--primary" href="/register">Get started</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="page-shell pt-4">
        <?php require base_path('app/Views/components/flash.php'); ?>
    </div>

    <main id="main-content"><?= $content ?></main>

    <footer class="site-footer">
        <div class="page-shell grid gap-10 py-14 md:grid-cols-[1.4fr_1fr_1fr]">
            <div>
                <a class="brand-mark" href="/" aria-label="OEMS home">
                    <span class="brand-mark__symbol" aria-hidden="true">O</span>
                    <span>OEMS</span>
                </a>
                <p class="mt-4 max-w-sm text-sm leading-6 text-[var(--ink-muted)]">
                    Better tools for finding a crowd, filling a room, and running an event people remember.
                </p>
            </div>
            <div>
                <h2 class="footer-heading">Discover</h2>
                <div class="mt-4 grid gap-3 text-sm text-[var(--ink-muted)]">
                    <a class="hover:text-[var(--ink)]" href="/events">All events</a>
                    <a class="hover:text-[var(--ink)]" href="/events?category=technology">Technology</a>
                    <a class="hover:text-[var(--ink)]" href="/events?category=community">Community</a>
                </div>
            </div>
            <div>
                <h2 class="footer-heading">OEMS</h2>
                <div class="mt-4 grid gap-3 text-sm text-[var(--ink-muted)]">
                    <a class="hover:text-[var(--ink)]" href="/register?role=organizer">Host an event</a>
                    <a class="hover:text-[var(--ink)]" href="/login">Sign in</a>
                    <a class="hover:text-[var(--ink)]" href="mailto:hello@oems.local">Contact</a>
                </div>
            </div>
        </div>
        <div class="page-shell flex flex-col gap-3 border-t border-[var(--line)] py-6 text-xs text-[var(--ink-muted)] sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= date('Y') ?> OEMS. Built for real communities.</p>
            <p>Dhaka, Bangladesh</p>
        </div>
    </footer>
</body>
</html>

