<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/oems-192.png" type="image/png">
    <title><?= e($pageTitle ?? 'Account') ?> | <?= e($siteSettings['site_name'] ?? $app['name']) ?></title>
    <script src="/assets/js/theme.js?v=20260811-form-controls-fix"></script>
    <link rel="stylesheet" href="/assets/css/app.css?v=20260814-sidebar-scroll-v1">
    <script src="/assets/js/app.js?v=20260812-form-system" defer></script>
    <script src="/assets/js/pwa.js?v=20260811-form-controls-fix" defer></script>
</head>
<body class="min-h-[100dvh] bg-[var(--surface)] text-[var(--ink)] antialiased">
    <a class="skip-link" href="#auth-content">Skip to form</a>
    <div class="grid min-h-[100dvh] lg:grid-cols-[minmax(380px,0.92fr)_minmax(540px,1.08fr)]">
        <aside class="auth-visual hidden lg:flex">
            <div class="auth-visual__top"><?php $brandVariant = 'inverse'; require base_path('app/Views/components/brand.php'); ?></div>
            <div class="auth-visual__content">
                <p class="eyebrow eyebrow--inverse"><i class="ph ph-sparkle" aria-hidden="true"></i><span>Made for real communities</span></p>
                <p class="auth-visual__title">Good events begin before anyone enters the room.</p>
                <p class="auth-visual__copy"><?= e($siteSettings['site_tagline'] ?? 'Build the account that helps you discover, organize, and remember what matters.') ?></p>
                <div class="auth-visual__list" aria-label="OEMS account benefits">
                    <span><i class="ph ph-ticket" aria-hidden="true"></i>One account for every ticket</span>
                    <span><i class="ph ph-shield-check" aria-hidden="true"></i>Secure role-based workspaces</span>
                </div>
            </div>
        </aside>

        <main id="auth-content" class="flex min-h-[100dvh] flex-col">
            <header class="flex h-[72px] items-center justify-between px-5 sm:px-8 lg:px-12">
                <div class="lg:hidden"><?php require base_path('app/Views/components/brand.php'); ?></div>
                <div class="ml-auto flex items-center gap-3">
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">
                        <i class="ph ph-moon" data-theme-icon aria-hidden="true"></i>
                    </button>
                    <a class="button button--quiet button--compact" href="/"><i class="ph ph-arrow-left" aria-hidden="true"></i><span class="hidden sm:inline">Back to events</span><span class="sm:hidden">Back</span></a>
                </div>
            </header>
            <div class="auth-form-shell">
                <?php require base_path('app/Views/components/flash.php'); ?>
                <?= $content ?>
            </div>
        </main>
    </div>
</body>
</html>
