<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= e($pageTitle ?? 'Dashboard') ?> | <?= e($app['name']) ?></title>
    <script src="/assets/js/theme.js"></script>
    <?php if (!empty($leafletEnabled)): ?><link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
    <script src="/assets/js/dashboard-sidebar.js" defer></script>
    <?php if (!empty($leafletEnabled)): ?><script src="/assets/vendor/leaflet/leaflet.js" defer></script><?php endif; ?>
    <?php if (!empty($venueMapEnabled)): ?><script src="/assets/js/venue-map.js" defer></script><?php endif; ?>
</head>
<body class="min-h-[100dvh] bg-[var(--surface-soft)] text-[var(--ink)] antialiased">
    <?php
    $currentPath = '/' . trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    $currentPath = $currentPath === '//' ? '/' : $currentPath;
    $overviewPaths = ['/dashboard', '/participant/dashboard', '/organizer/dashboard', '/admin/dashboard'];
    $overviewActive = in_array($currentPath, $overviewPaths, true);
    $organizerEventsActive = str_starts_with($currentPath, '/organizer/events');
    $organizerOperationsActive = preg_match('#^/organizer/events/[^/]+/(participants|check-in)#', $currentPath) === 1;
    $organizerEventsActive = $organizerEventsActive && !$organizerOperationsActive;
    $organizerVenuesActive = str_starts_with($currentPath, '/organizer/venues');
    $participantReviewFormActive = str_starts_with($currentPath, '/participant/events/')
        && str_ends_with($currentPath, '/review');
    $participantRegistrationsActive = str_starts_with($currentPath, '/participant/registrations')
        || (str_starts_with($currentPath, '/participant/events/') && !$participantReviewFormActive);
    $participantTicketsActive = str_starts_with($currentPath, '/participant/tickets');
    $participantFavoritesActive = str_starts_with($currentPath, '/participant/favorites');
    $participantNotificationsActive = str_starts_with($currentPath, '/participant/notifications');
    $participantReviewsActive = str_starts_with($currentPath, '/participant/reviews') || $participantReviewFormActive;
    $adminEventsActive = str_starts_with($currentPath, '/admin/events');
    $adminPaymentsActive = str_starts_with($currentPath, '/admin/payments');
    $adminCategoriesActive = str_starts_with($currentPath, '/admin/categories');
    $organizerReviewsActive = str_starts_with($currentPath, '/organizer/reviews');
    $adminReviewsActive = str_starts_with($currentPath, '/admin/reviews');
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
                        <a class="dashboard-nav-link<?= $participantFavoritesActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/favorites"<?= $participantFavoritesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Favorites</span></a>
                        <a class="dashboard-nav-link<?= $participantNotificationsActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/notifications"<?= $participantNotificationsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-bell" aria-hidden="true"></i><span>Notifications</span><?php if ((int) ($unreadNotifications ?? 0) > 0): ?><span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--accent)] px-1.5 py-0.5 text-xs font-bold text-white" aria-label="<?= e((int) $unreadNotifications) ?> unread notifications"><?= e((int) $unreadNotifications) ?></span><?php endif; ?></a>
                        <a class="dashboard-nav-link<?= $participantReviewsActive ? ' dashboard-nav-link--active' : '' ?>" href="/participant/reviews"<?= $participantReviewsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-star" aria-hidden="true"></i><span>Reviews</span></a>
                    <?php endif; ?>
                    <?php if (($currentUser['role_slug'] ?? '') === 'organizer'): ?>
                        <a class="dashboard-nav-link<?= $organizerEventsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/events"<?= $organizerEventsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Events</span></a>
                        <a class="dashboard-nav-link<?= $organizerOperationsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/events"<?= $organizerOperationsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-users-three" aria-hidden="true"></i><span>Event operations</span></a>
                        <a class="dashboard-nav-link<?= $organizerVenuesActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/venues"<?= $organizerVenuesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-buildings" aria-hidden="true"></i><span>Venues</span></a>
                        <a class="dashboard-nav-link<?= $organizerReviewsActive ? ' dashboard-nav-link--active' : '' ?>" href="/organizer/reviews"<?= $organizerReviewsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chat-centered-text" aria-hidden="true"></i><span>Reviews</span></a>
                    <?php endif; ?>
                    <?php if (($currentUser['role_slug'] ?? '') === 'super-admin'): ?>
                        <a class="dashboard-nav-link<?= $adminPaymentsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/payments"<?= $adminPaymentsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-receipt" aria-hidden="true"></i><span>Payment review</span></a>
                        <a class="dashboard-nav-link<?= $adminEventsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/events"<?= $adminEventsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-shield-chevron" aria-hidden="true"></i><span>Event moderation</span></a>
                        <a class="dashboard-nav-link<?= $adminCategoriesActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/categories"<?= $adminCategoriesActive ? ' aria-current="page"' : '' ?>><i class="ph ph-tag" aria-hidden="true"></i><span>Categories</span></a>
                        <a class="dashboard-nav-link<?= $adminReviewsActive ? ' dashboard-nav-link--active' : '' ?>" href="/admin/reviews"<?= $adminReviewsActive ? ' aria-current="page"' : '' ?>><i class="ph ph-chat-centered-text" aria-hidden="true"></i><span>Review moderation</span></a>
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
                <form action="/logout" method="post" class="mt-4">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="button button--quiet w-full" type="submit"><i class="ph ph-sign-out" aria-hidden="true"></i><span>Log out</span></button>
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
