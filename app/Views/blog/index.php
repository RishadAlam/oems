<section class="page-shell py-14 lg:py-20">
    <div class="max-w-3xl"><p class="eyebrow"><i class="ph ph-newspaper-clipping" aria-hidden="true"></i><span>OEMS editorial</span></p><h1 class="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">Guides for better gatherings.</h1><p class="mt-5 max-w-2xl text-base leading-7 text-[var(--ink-muted)]">Practical ideas for choosing, hosting, and growing events that people remember.</p></div>

    <?php if ($categories !== []): ?><nav class="mt-8 flex flex-wrap gap-2" aria-label="Blog categories"><a class="filter-chip<?= $category === null ? ' filter-chip--active' : '' ?>" href="/blog"<?= $category === null ? ' aria-current="page"' : '' ?>>All stories</a><?php foreach ($categories as $item): ?><a class="filter-chip<?= $category === $item ? ' filter-chip--active' : '' ?>" href="/blog?category=<?= e(rawurlencode($item)) ?>"<?= $category === $item ? ' aria-current="page"' : '' ?>><?= e($item) ?></a><?php endforeach; ?></nav><?php endif; ?>

    <?php if (!empty($filterError)): ?>
        <div class="empty-state mt-10" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><h2>Those Blog filters are invalid</h2><p>Clear the URL filters and try again.</p><a class="button button--primary" href="/blog">Open all stories</a></div>
    <?php elseif ($posts === []): ?>
        <div class="empty-state mt-10"><i class="ph ph-notebook" aria-hidden="true"></i><h2>No stories published yet</h2><p>Check back for event guides and community notes.</p><a class="button button--primary" href="/events">Explore events</a></div>
    <?php else: ?>
        <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($posts as $index => $post): ?>
                <article class="event-card overflow-hidden">
                    <?php if (!empty($post['cover_image'])): ?><a href="/blog/<?= e($post['slug']) ?>" tabindex="-1" aria-hidden="true"><img class="aspect-[16/9] w-full object-cover" src="<?= e($post['cover_image']) ?>" alt="" width="640" height="360" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async"></a><?php endif; ?>
                    <div class="p-6"><div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[.1em] text-[var(--ink-muted)]"><?php if (!empty($post['category'])): ?><span><?= e($post['category']) ?></span><span aria-hidden="true">·</span><?php endif; ?><time datetime="<?= e($post['published_at']) ?>"><?= e($post['published_display']) ?></time><span aria-hidden="true">·</span><span><?= e($post['reading_minutes']) ?> min read</span></div><h2 class="mt-3 text-2xl font-semibold leading-tight"><a href="/blog/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h2><p class="mt-3 text-sm leading-6 text-[var(--ink-muted)]"><?= e($post['excerpt']) ?></p><a class="text-link mt-5 inline-flex" href="/blog/<?= e($post['slug']) ?>">Read story <i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php $pageHref = static fn (int $page): string => '/blog?' . http_build_query(array_filter(['category' => $category, 'page' => $page], static fn (mixed $value): bool => $value !== null && $value !== '')); ?>
        <?php if (($pagination['last_page'] ?? 1) > 1): ?><nav class="mt-10 flex items-center justify-center gap-3" aria-label="Blog pagination"><?php if ($pagination['page'] > 1): ?><a class="button button--quiet" href="<?= e($pageHref($pagination['page'] - 1)) ?>">Previous</a><?php endif; ?><span class="text-sm text-[var(--ink-muted)]">Page <?= e($pagination['page']) ?> of <?= e($pagination['last_page']) ?></span><?php if ($pagination['page'] < $pagination['last_page']): ?><a class="button button--quiet" href="<?= e($pageHref($pagination['page'] + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
    <?php endif; ?>
</section>
