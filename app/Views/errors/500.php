<section class="error-page">
    <div class="page-shell">
        <div class="error-state">
            <span class="error-state__icon"><i class="ph ph-lifebuoy" aria-hidden="true"></i></span>
            <p class="error-state__code">Server error</p>
            <h1>We could not open this page.</h1>
            <p>The problem has been recorded. Please try again shortly or return to a safe starting point.</p>
            <?php if (($currentUser ?? null) !== null): ?>
                <a class="button button--primary" href="/dashboard"><i class="ph ph-squares-four" aria-hidden="true"></i><span>Return to dashboard</span></a>
            <?php else: ?>
                <a class="button button--primary" href="/"><i class="ph ph-house" aria-hidden="true"></i><span>Return home</span></a>
            <?php endif; ?>
        </div>
    </div>
</section>
