<?php
$calendarData = is_array($calendar ?? null) ? $calendar : [];
$calendarEvents = is_array($calendarData['events'] ?? null) ? $calendarData['events'] : [];
$calendarDays = is_array($calendarData['days'] ?? null) ? $calendarData['days'] : [];
$formatSchedule = static function (mixed $value): string {
    if (!is_scalar($value) || trim((string) $value) === '') {
        return 'Schedule unavailable';
    }
    try {
        return (new DateTimeImmutable((string) $value))->format('M j, Y, g:i A');
    } catch (Throwable) {
        return 'Schedule unavailable';
    }
};
$locationLabel = static function (array $event): string {
    $location = is_array($event['location'] ?? null) ? $event['location'] : [];
    return implode(', ', array_filter([
        is_scalar($location['city'] ?? null) ? trim((string) $location['city']) : '',
        is_scalar($location['country'] ?? null) ? trim((string) $location['country']) : '',
    ]));
};
?>

<section class="page-shell py-12 lg:py-16">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <p class="eyebrow"><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Plan ahead</span></p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">Event calendar</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-[var(--ink-muted)]">Browse published and completed events by month. The chronological list remains available on every screen size.</p>
        </div>
        <a class="button button--quiet" href="/events"><i class="ph ph-list-bullets" aria-hidden="true"></i><span>Browse event list</span></a>
    </div>

    <?php if (!($calendarData['success'] ?? false)): ?>
        <div class="empty-state mt-8" role="alert" aria-labelledby="calendar-error-heading">
            <i class="ph ph-calendar-x" aria-hidden="true"></i>
            <h2 id="calendar-error-heading">That month is unavailable</h2>
            <p><?= e($calendarData['errors']['month'][0] ?? 'Choose an available month in YYYY-MM format.') ?></p>
            <a class="button button--primary" href="/events/calendar">Open the current month</a>
        </div>
    <?php else: ?>
        <nav class="mt-8 flex flex-wrap items-center justify-between gap-3" aria-label="Calendar month navigation">
            <a class="button button--quiet" href="/events/calendar?month=<?= e($calendarData['previous_month']) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Previous</span></a>
            <h2 class="order-first w-full text-center text-xl font-semibold sm:order-none sm:w-auto sm:text-2xl"><?= e($calendarData['label']) ?></h2>
            <a class="button button--quiet" href="/events/calendar?month=<?= e($calendarData['next_month']) ?>"><span>Next</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </nav>

        <section class="mt-8 hidden overflow-hidden rounded-[var(--radius-card)] border border-[var(--line)] bg-[var(--surface-raised)] md:block" aria-labelledby="month-grid-heading">
            <h2 id="month-grid-heading" class="sr-only"><?= e($calendarData['label']) ?> month grid</h2>
            <ul class="grid grid-cols-7 border-b border-[var(--line)] bg-[var(--surface-soft)] text-center text-xs font-semibold uppercase tracking-[.12em] text-[var(--ink-muted)]" aria-hidden="true">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?><li class="px-2 py-3"><?= e($weekday) ?></li><?php endforeach; ?>
            </ul>
            <ol class="grid grid-cols-7" aria-label="<?= e($calendarData['label']) ?> calendar days">
                <?php foreach ($calendarDays as $day): ?>
                    <li class="min-h-36 border-b border-r border-[var(--line)] p-3<?= empty($day['in_month']) ? ' bg-[var(--surface-soft)] text-[var(--ink-muted)]' : '' ?>" aria-label="<?= e($day['label'] ?? '') ?>">
                        <time class="text-sm font-semibold" datetime="<?= e($day['date'] ?? '') ?>"><?= e($day['day'] ?? '') ?></time>
                        <?php if (!empty($day['events'])): ?>
                            <ul class="mt-3 grid gap-2">
                                <?php foreach ($day['events'] as $event): ?>
                                    <li><a class="block rounded-xl border border-[var(--line)] bg-[var(--surface)] px-3 py-2 text-xs font-semibold leading-5 text-[var(--accent-strong)] hover:border-[var(--accent)]" href="/events/<?= e($event['slug'] ?? '') ?>"><?= e($event['title'] ?? 'Event') ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="mt-10" aria-labelledby="chronological-events-heading">
            <div class="flex items-end justify-between gap-4">
                <div><p class="eyebrow"><i class="ph ph-list-numbers" aria-hidden="true"></i><span>Canonical view</span></p><h2 id="chronological-events-heading" class="mt-3 text-2xl font-semibold">Chronological event list</h2></div>
                <p class="text-sm text-[var(--ink-muted)]"><?= count($calendarEvents) ?> <?= count($calendarEvents) === 1 ? 'event' : 'events' ?></p>
            </div>
            <?php if ($calendarEvents === []): ?>
                <div class="empty-state mt-6" aria-label="No events this month">
                    <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                    <h3>No events this month</h3>
                    <p>Try the previous or next month, or browse every upcoming event.</p>
                    <a class="button button--primary" href="/events">Explore events</a>
                </div>
            <?php else: ?>
                <ol class="mt-6 grid gap-4">
                    <?php foreach ($calendarEvents as $event): ?>
                        <li class="event-card grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[var(--accent-strong)]"><time datetime="<?= e($event['schedule']['starts_at'] ?? '') ?>"><?= e($formatSchedule($event['schedule']['starts_at'] ?? null)) ?></time></p>
                                <h3 class="mt-2 text-xl font-semibold"><a href="/events/<?= e($event['slug'] ?? '') ?>"><?= e($event['title'] ?? 'Event') ?></a></h3>
                                <p class="mt-2 text-sm text-[var(--ink-muted)]"><?= e($event['category']['name'] ?? 'Event') ?><?php if ($locationLabel($event) !== ''): ?> · <?= e($locationLabel($event)) ?><?php endif; ?></p>
                            </div>
                            <a class="button button--quiet" href="/events/<?= e($event['slug'] ?? '') ?>"><span>View event</span><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
