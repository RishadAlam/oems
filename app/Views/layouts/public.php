<?php
$layoutOld = is_array($old ?? null) ? $old : [];
$layoutErrors = is_array($errors ?? null) ? $errors : [];
$layoutCsrfToken = is_scalar($csrfToken ?? null) ? (string) $csrfToken : '';
$newsletterError = array_key_exists('newsletter_email', $layoutOld) ? field_error($layoutErrors, 'email') : null;
$layoutSiteName = (string) ($siteSettings['site_name'] ?? $app['name']);
$layoutPageTitle = (string) ($pageTitle ?? $layoutSiteName);
$layoutHasBrandSuffix = preg_match('/\|\s*' . preg_quote($layoutSiteName, '/') . '\s*\z/ui', $layoutPageTitle) === 1;
$layoutDocumentTitle = strcasecmp(trim($layoutPageTitle), trim($layoutSiteName)) === 0 || $layoutHasBrandSuffix
    ? $layoutPageTitle
    : $layoutPageTitle . ' | ' . $layoutSiteName;
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($metaDescription ?? $siteSettings['default_seo_description'] ?? 'Discover and host memorable events with OEMS.') ?>">
    <?php if (!empty($canonicalUrl)): ?>
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php endif; ?>
    <?php foreach (($openGraph ?? []) as $property => $value): ?>
        <?php if (is_scalar($value) && (string) $value !== ''): ?>
            <meta property="og:<?= e($property) ?>" content="<?= e($value) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <meta name="theme-color" content="#f5f7fb">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/oems-192.png" type="image/png">
    <title><?= e($layoutDocumentTitle) ?></title>
    <script src="/assets/js/theme.js?v=20260811-form-controls-fix"></script>
    <?php if (!empty($leafletEnabled)): ?>
        <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css?v=20260813-event-view-v1">
    <?php if (isset($jsonLd) && is_array($jsonLd)): ?>
        <script type="application/ld+json"><?= json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
    <?php endif; ?>
    <script src="/assets/js/app.js?v=20260812-form-system" defer></script>
    <script src="/assets/js/pwa.js?v=20260811-form-controls-fix" defer></script>
    <?php if (!empty($leafletEnabled)): ?>
        <script src="/assets/vendor/leaflet/leaflet.js" defer></script>
        <script src="/assets/js/location.js?v=20260813-event-view-v1" defer></script>
    <?php endif; ?>
</head>
<body class="min-h-[100dvh] bg-[var(--surface)] text-[var(--ink)] antialiased">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header">
        <div class="page-shell flex h-[72px] items-center justify-between gap-5">
            <?php require base_path('app/Views/components/brand.php'); ?>

            <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary navigation">
                <a class="nav-link" href="/events">Explore events</a>
                <a class="nav-link" href="/events/calendar">Calendar</a>
                <a class="nav-link" href="/blog">Blog</a>
                <a class="nav-link" href="/register?role=organizer">For organizers</a>
                <a class="nav-link" href="/#how-it-works">How it works</a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">
                    <i class="ph ph-moon" data-theme-icon aria-hidden="true"></i>
                </button>
                <?php if ($currentUser !== null): ?>
                    <a class="button button--primary button--compact" href="/dashboard"><span>Dashboard</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <?php else: ?>
                    <a class="button button--quiet button--compact" href="/login">Log in</a>
                    <a class="button button--primary button--compact" href="/register"><span>Get started</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
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
                <i class="ph ph-list" aria-hidden="true"></i>
            </button>
        </div>

        <div id="mobile-menu" class="mobile-menu lg:hidden" data-mobile-menu hidden>
            <nav class="page-shell grid gap-2 py-5" aria-label="Mobile navigation">
                <a class="mobile-menu__link" href="/events"><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
                <a class="mobile-menu__link" href="/events/calendar"><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Event calendar</span></a>
                <a class="mobile-menu__link" href="/blog"><i class="ph ph-newspaper-clipping" aria-hidden="true"></i><span>Blog</span></a>
                <a class="mobile-menu__link" href="/register?role=organizer"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>For organizers</span></a>
                <a class="mobile-menu__link" href="/#how-it-works"><i class="ph ph-path" aria-hidden="true"></i><span>How it works</span></a>
                <button class="mobile-menu__link text-left" type="button" data-theme-toggle aria-label="Switch to dark theme">
                    <i class="ph ph-moon" data-theme-icon aria-hidden="true"></i><span data-theme-label>Switch to dark theme</span>
                </button>
                <?php if ($currentUser !== null): ?>
                    <a class="button button--primary mt-3" href="/dashboard"><span>Dashboard</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <?php else: ?>
                    <a class="button button--quiet mt-3" href="/login">Log in</a>
                    <a class="button button--primary" href="/register"><span>Get started</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="page-shell pt-4">
        <?php require base_path('app/Views/components/flash.php'); ?>
    </div>

    <main id="main-content"><?= $content ?></main>

    <footer class="site-footer">
        <div class="page-shell grid gap-10 py-14 md:grid-cols-2 xl:grid-cols-[1.2fr_.8fr_.8fr_1.2fr]">
            <div>
                <?php require base_path('app/Views/components/brand.php'); ?>
                <p class="mt-4 max-w-sm text-sm leading-6 text-[var(--ink-muted)]">
                    <?= e($siteSettings['footer_blurb'] ?? 'Better tools for finding a crowd, filling a room, and running an event people remember.') ?>
                </p>
            </div>
            <div>
                <h2 class="footer-heading">Discover</h2>
                <div class="mt-4 grid gap-3 text-sm text-[var(--ink-muted)]">
                    <a class="hover:text-[var(--ink)]" href="/events">All events</a>
                    <a class="hover:text-[var(--ink)]" href="/events/calendar">Event calendar</a>
                    <a class="hover:text-[var(--ink)]" href="/blog">Blog</a>
                    <a class="hover:text-[var(--ink)]" href="/events?category=technology">Technology</a>
                    <a class="hover:text-[var(--ink)]" href="/events?category=community">Community</a>
                </div>
            </div>
            <div>
                <h2 class="footer-heading">OEMS</h2>
                <div class="mt-4 grid gap-3 text-sm text-[var(--ink-muted)]">
                    <a class="hover:text-[var(--ink)]" href="/register?role=organizer">Host an event</a>
                    <a class="hover:text-[var(--ink)]" href="/about">About</a>
                    <a class="hover:text-[var(--ink)]" href="/faq">FAQ</a>
                    <a class="hover:text-[var(--ink)]" href="/privacy">Privacy</a>
                    <a class="hover:text-[var(--ink)]" href="/terms">Terms</a>
                    <a class="hover:text-[var(--ink)]" href="/login">Sign in</a>
                    <a class="hover:text-[var(--ink)]" href="/contact">Contact</a>
                    <a class="hover:text-[var(--ink)]" href="mailto:<?= e($siteSettings['contact_email'] ?? 'hello@oems.local') ?>"><?= e($siteSettings['contact_email'] ?? 'hello@oems.local') ?></a>
                    <a class="hover:text-[var(--ink)]" href="tel:<?= e(preg_replace('/[^+0-9]/', '', $siteSettings['support_phone'] ?? '+880200000000')) ?>"><?= e($siteSettings['support_phone'] ?? '+880 2 0000 0000') ?></a>
                </div>
            </div>
            <div id="newsletter">
                <h2 class="footer-heading">Event updates</h2>
                <p class="mt-4 text-sm leading-6 text-[var(--ink-muted)]">Confirm your address before OEMS sends occasional event news.</p>
                <form class="mt-4 grid gap-3" action="/newsletter/subscribe" method="post" data-form-kind="entry">
                    <input type="hidden" name="_token" value="<?= e($layoutCsrfToken) ?>">
                    <?php
                    $layoutPageErrors = $errors ?? [];
                    $errors = $newsletterError ? ['email' => [$newsletterError]] : [];
                    $fieldTargets = ['email' => 'newsletter-email'];
                    $fieldLabels = ['email' => 'Email address'];
                    $formErrorSummaryId = 'newsletter-error-summary';
                    require base_path('app/Views/components/form-errors.php');
                    $errors = $layoutPageErrors;
                    ?>
                    <div class="sr-only" aria-hidden="true"><label for="newsletter-website">Website</label><input id="newsletter-website" name="website" tabindex="-1" autocomplete="off"></div>
                    <label class="sr-only" for="newsletter-email">Email address</label>
                    <input class="newsletter-input" id="newsletter-email" name="email" type="email" maxlength="190" value="<?= old_value($layoutOld, 'newsletter_email') ?>" placeholder="you@example.com" autocomplete="email" inputmode="email" required data-form-label="Email address"<?= form_control_attributes($newsletterError ? ['email' => [$newsletterError]] : [], 'email', ['newsletter-help'], 'newsletter-error') ?>>
                    <p id="newsletter-help" class="text-xs leading-5 text-[var(--ink-muted)]">Double opt-in. Unsubscribe from any campaign.</p>
                    <?php if ($newsletterError): ?><p id="newsletter-error" class="field-error" role="alert"><?= e($newsletterError) ?></p><?php endif; ?>
                    <button class="button button--primary" type="submit" data-submit-label="Requesting confirmation…"><span data-submit-text>Request confirmation</span></button>
                </form>
            </div>
        </div>
        <div class="page-shell flex flex-col gap-3 border-t border-[var(--line)] py-6 text-xs text-[var(--ink-muted)] sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; <?= date('Y') ?> <?= e($siteSettings['site_name'] ?? 'OEMS') ?>. <?= e($siteSettings['site_tagline'] ?? 'Built for real communities.') ?></p>
            <p><?= e($siteSettings['footer_location'] ?? 'Dhaka, Bangladesh') ?></p>
            <button class="button button--quiet button--compact" type="button" data-pwa-install hidden>Install app</button>
        </div>
    </footer>
</body>
</html>
