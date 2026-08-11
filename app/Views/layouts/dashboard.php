<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/oems-192.png" type="image/png">
    <?php if (!empty($robots)): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif; ?>
    <title><?= e($pageTitle ?? 'Dashboard') ?> | <?= e($siteSettings['site_name'] ?? $app['name']) ?></title>
    <script src="/assets/js/theme.js?v=20260811-form-controls-fix"></script>
    <?php if (!empty($leafletEnabled)): ?><link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css?v=20260812-form-separators">
    <script src="/assets/js/app.js?v=20260812-form-system" defer></script>
    <script src="/assets/js/dashboard-sidebar.js" defer></script>
    <script src="/assets/js/pwa.js?v=20260811-form-controls-fix" defer></script>
    <?php if (!empty($analyticsChartsEnabled)): ?><script src="/assets/vendor/chartjs/chart.umd.min.js" defer></script><script src="/assets/js/analytics-charts.js" defer></script><?php endif; ?>
    <?php if (!empty($leafletEnabled)): ?><script src="/assets/vendor/leaflet/leaflet.js" defer></script><?php endif; ?>
    <?php if (!empty($venueMapEnabled)): ?><script src="/assets/js/venue-map.js?v=20260811-geolocation-secure" defer></script><?php endif; ?>
</head>
<body class="min-h-[100dvh] bg-[var(--surface-soft)] text-[var(--ink)] antialiased">
    <?php
    $currentPath = '/' . trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    $currentPath = $currentPath === '//' ? '/' : $currentPath;
    $overviewPaths = ['/dashboard', '/participant/dashboard', '/organizer/dashboard', '/admin/dashboard'];
    $overviewActive = in_array($currentPath, $overviewPaths, true);
    $organizerTrashActive = str_starts_with($currentPath, '/organizer/events/trash');
    $organizerEventsActive = str_starts_with($currentPath, '/organizer/events') && !$organizerTrashActive;
    $organizerOperationsActive = preg_match('#^/organizer/events/[^/]+/(participants|check-in)#', $currentPath) === 1;
    $organizerEventsActive = $organizerEventsActive && !$organizerOperationsActive;
    $organizerVenuesActive = str_starts_with($currentPath, '/organizer/venues');
    $organizerCouponsActive = str_starts_with($currentPath, '/organizer/coupons');
    $organizerAnalyticsActive = str_starts_with($currentPath, '/organizer/analytics');
    $participantReviewFormActive = str_starts_with($currentPath, '/participant/events/')
        && str_ends_with($currentPath, '/review');
    $participantRegistrationsActive = str_starts_with($currentPath, '/participant/registrations')
        || (str_starts_with($currentPath, '/participant/events/') && !$participantReviewFormActive);
    $participantTicketsActive = str_starts_with($currentPath, '/participant/tickets');
    $participantCertificatesActive = str_starts_with($currentPath, '/participant/certificates');
    $participantFavoritesActive = str_starts_with($currentPath, '/participant/favorites');
    $participantWaitlistActive = str_starts_with($currentPath, '/participant/waitlist');
    $participantNotificationsActive = str_starts_with($currentPath, '/participant/notifications');
    $participantReviewsActive = str_starts_with($currentPath, '/participant/reviews') || $participantReviewFormActive;
    $adminTrashActive = str_starts_with($currentPath, '/admin/events/trash');
    $adminEventsActive = str_starts_with($currentPath, '/admin/events') && !$adminTrashActive;
    $adminUsersActive = str_starts_with($currentPath, '/admin/users');
    $adminOrganizersActive = str_starts_with($currentPath, '/admin/organizers');
    $adminPaymentsActive = str_starts_with($currentPath, '/admin/payments');
    $adminCategoriesActive = str_starts_with($currentPath, '/admin/categories');
    $organizerReviewsActive = str_starts_with($currentPath, '/organizer/reviews');
    $adminReviewsActive = str_starts_with($currentPath, '/admin/reviews');
    $adminAnalyticsActive = str_starts_with($currentPath, '/admin/analytics');
    $adminReportsActive = str_starts_with($currentPath, '/admin/reports');
    $adminSettingsActive = str_starts_with($currentPath, '/admin/settings');
    $adminContactActive = str_starts_with($currentPath, '/admin/contact');
    $adminNewsletterActive = str_starts_with($currentPath, '/admin/newsletter');
    $adminCmsActive = str_starts_with($currentPath, '/admin/cms');
    $adminOperationsActive = str_starts_with($currentPath, '/admin/operations');
    $adminBlogActive = str_starts_with($currentPath, '/admin/blog');
    $userName = (string) ($currentUser['name'] ?? 'OEMS user');
    $nameParts = preg_split('/\s+/', trim($userName)) ?: [];
    $userInitials = implode('', array_map(
        static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
        array_slice(array_filter($nameParts), 0, 2),
    ));
    ?>
    <a class="skip-link" href="#dashboard-content">Skip to content</a>
    <div class="min-h-[100dvh] lg:grid lg:grid-cols-[264px_1fr]">
        <aside id="dashboard-sidebar" class="dashboard-sidebar" data-dashboard-sidebar aria-label="Workspace navigation">
            <div class="flex h-[72px] items-center justify-between">
                <?php require base_path('app/Views/components/brand.php'); ?>
                <button class="icon-button lg:hidden" type="button" data-dashboard-close aria-controls="dashboard-sidebar" aria-label="Close navigation" title="Close navigation"><i class="ph ph-x" aria-hidden="true"></i></button>
            </div>
            <div class="mt-6">
                <p class="dashboard-sidebar__label">Workspace</p>
                <nav class="mt-3 grid gap-1" aria-label="Dashboard navigation">
                    <a class="dashboard-nav-link<?= $overviewActive ? ' dashboard-nav-link--active' : '' ?>" href="/dashboard"<?= $overviewActive ? ' aria-current="page"' : '' ?>><i class="ph ph-squares-four" aria-hidden="true"></i><span>Overview</span></a>
                    <?php if (($currentUser['role_slug'] ?? '') === 'participant'): ?>
                        <a class="dashboard-nav-link<?= $participantRegistrationsActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/registrations"<?= $participantRegistrationsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-list-checks" aria-hidden="true"></i><span>Registrations</span></a>
                        <a class="dashboard-nav-link<?= $participantTicketsActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/tickets"<?= $participantTicketsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-ticket" aria-hidden="true"></i><span>Tickets</span></a>
                        <a class="dashboard-nav-link<?= $participantCertificatesActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/certificates"<?= $participantCertificatesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-seal-check" aria-hidden="true"></i><span>Certificates</span></a>
                        <a class="dashboard-nav-link<?= $participantFavoritesActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/favorites"<?= $participantFavoritesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Favorites</span></a>
                        <a class="dashboard-nav-link<?= $participantWaitlistActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/waitlist"<?= $participantWaitlistActive ? ' aria-current="page"' : '' ?>><i class="ph ph-hourglass-medium" aria-hidden="true"></i><span>Waitlist</span></a>
                        <a class="dashboard-nav-link<?= $participantNotificationsActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/notifications"<?= $participantNotificationsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-bell" aria-hidden="true"></i><span>Notifications</span><?php if ((int) ($unreadNotifications ?? 0) > 0): ?><span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--accent)] px-1.5 py-0.5 text-xs font-bold text-white" aria-label="<?= e((int) $unreadNotifications) ?> unread notifications"><?= e((int) $unreadNotifications) ?></span><?php endif; ?></a>
                        <a class="dashboard-nav-link<?= $participantReviewsActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/reviews"<?= $participantReviewsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-star" aria-hidden="true"></i><span>Reviews</span></a>
                    <?php endif; ?>
                    <?php if (($currentUser['role_slug'] ?? '') === 'organizer'): ?>
                        <a class="dashboard-nav-link<?= $organizerEventsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/events"<?= $organizerEventsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Events</span></a>
                        <a class="dashboard-nav-link<?= $organizerTrashActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/events/trash"<?= $organizerTrashActive ? ' aria-current="page"' : '' ?>><i class="ph ph-archive" aria-hidden="true"></i><span>Event trash</span></a>
                        <a class="dashboard-nav-link<?= $organizerOperationsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/events"<?= $organizerOperationsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-users-three" aria-hidden="true"></i><span>Event operations</span></a>
                        <a class="dashboard-nav-link<?= $organizerVenuesActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/venues"<?= $organizerVenuesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-buildings" aria-hidden="true"></i><span>Venues</span></a>
                        <a class="dashboard-nav-link<?= $organizerCouponsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/coupons"<?= $organizerCouponsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-ticket" aria-hidden="true"></i><span>Coupons</span></a>
                        <a class="dashboard-nav-link<?= $organizerReviewsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/reviews"<?= $organizerReviewsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chat-centered-text" aria-hidden="true"></i><span>Reviews</span></a>
                        <a class="dashboard-nav-link<?= $organizerAnalyticsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/analytics"<?= $organizerAnalyticsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chart-line-up" aria-hidden="true"></i><span>Analytics</span></a>
                    <?php endif; ?>
                    <?php if (($currentUser['role_slug'] ?? '') === 'super-admin'): ?>
                        <a class="dashboard-nav-link<?= $adminUsersActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/users"<?= $adminUsersActive ? ' aria-current="page"' : '' ?>><i class="ph ph-users" aria-hidden="true"></i><span>Users</span></a>
                        <a class="dashboard-nav-link<?= $adminOrganizersActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/organizers"<?= $adminOrganizersActive ? ' aria-current="page"' : '' ?>><i class="ph ph-buildings" aria-hidden="true"></i><span>Organizers</span></a>
                        <a class="dashboard-nav-link<?= $adminPaymentsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/payments"<?= $adminPaymentsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-receipt" aria-hidden="true"></i><span>Payment review</span></a>
                        <a class="dashboard-nav-link<?= $adminEventsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/events"<?= $adminEventsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-shield-chevron" aria-hidden="true"></i><span>Event moderation</span></a>
                        <a class="dashboard-nav-link<?= $adminTrashActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/events/trash"<?= $adminTrashActive ? ' aria-current="page"' : '' ?>><i class="ph ph-archive" aria-hidden="true"></i><span>Event trash</span></a>
                        <a class="dashboard-nav-link<?= $adminCategoriesActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/categories"<?= $adminCategoriesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-tag" aria-hidden="true"></i><span>Categories</span></a>
                        <a class="dashboard-nav-link<?= $adminReviewsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/reviews"<?= $adminReviewsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chat-centered-text" aria-hidden="true"></i><span>Review moderation</span></a>
                        <a class="dashboard-nav-link<?= $adminAnalyticsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/analytics"<?= $adminAnalyticsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chart-line-up" aria-hidden="true"></i><span>Analytics</span></a>
                        <a class="dashboard-nav-link<?= $adminReportsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/reports"<?= $adminReportsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-files" aria-hidden="true"></i><span>Reports</span></a>
                        <a class="dashboard-nav-link<?= $adminContactActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/contact"<?= $adminContactActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chats" aria-hidden="true"></i><span>Contact inbox</span></a>
                        <a class="dashboard-nav-link<?= $adminNewsletterActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/newsletter"<?= $adminNewsletterActive ? ' aria-current="page"' : '' ?>><i class="ph ph-megaphone" aria-hidden="true"></i><span>Newsletter</span></a>
                        <a class="dashboard-nav-link<?= $adminCmsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/cms"<?= $adminCmsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-browser" aria-hidden="true"></i><span>Content</span></a>
                        <a class="dashboard-nav-link<?= $adminBlogActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/blog"<?= $adminBlogActive ? ' aria-current="page"' : '' ?>><i class="ph ph-newspaper" aria-hidden="true"></i><span>Blog</span></a>
                        <a class="dashboard-nav-link<?= $adminSettingsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/settings"<?= $adminSettingsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-sliders-horizontal" aria-hidden="true"></i><span>Settings</span></a>
                        <a class="dashboard-nav-link<?= $adminOperationsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/operations"<?= $adminOperationsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-pulse" aria-hidden="true"></i><span>Operations</span></a>
                    <?php endif; ?>
                    <a class="dashboard-nav-link<?= $currentPath === '/events' ? ' dashboard-nav-link--active' : '' ?>" href="/events"<?= $currentPath === '/events' ? ' aria-current="page"' : '' ?>><i class="ph ph-compass" aria-hidden="true"></i><span>Explore events</span></a>
                    <a class="dashboard-nav-link<?= $currentPath === '/profile' ? ' dashboard-nav-link--active' : '' ?>" href="/profile"<?= $currentPath === '/profile' ? ' aria-current="page"' : '' ?>><i class="ph ph-user-circle" aria-hidden="true"></i><span>Profile</span></a>
                    <a class="dashboard-nav-link<?= $currentPath === '/settings/password' ? ' dashboard-nav-link--active' : '' ?>" href="/settings/password"<?= $currentPath === '/settings/password' ? ' aria-current="page"' : '' ?>><i class="ph ph-shield-check" aria-hidden="true"></i><span>Security</span></a>
                </nav>
            </div>
            <div class="mt-auto border-t border-[var(--line)] pt-5">
                <div class="dashboard-user">
                    <span class="dashboard-user__avatar" aria-hidden="true"><?= e($userInitials !== '' ? $userInitials : 'O') ?></span>
                    <span class="min-w-0"><strong><?= e($userName) ?></strong><small><?= e($currentUser['email'] ?? '') ?></small></span>
                </div>
                <form action="/logout" method="post" class="mt-4" data-form-kind="action">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="button button--quiet w-full" type="submit" data-submit-label="Logging out…"><i class="ph ph-sign-out" aria-hidden="true"></i><span data-submit-text>Log out</span></button>
                </form>
            </div>
        </aside>

        <div class="min-w-0 lg:col-start-2" data-dashboard-main>
            <header class="dashboard-header">
                <button class="menu-button lg:hidden" type="button" data-dashboard-open aria-label="Open navigation" aria-controls="dashboard-sidebar" aria-expanded="false"><i class="ph ph-list" aria-hidden="true"></i><span>Menu</span></button>
                <p class="dashboard-header__context hidden lg:flex"><i class="ph ph-buildings" aria-hidden="true"></i><span>OEMS workspace</span></p>
                <div class="ml-auto flex items-center gap-3">
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme"><i class="ph ph-moon" data-theme-icon aria-hidden="true"></i></button>
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
