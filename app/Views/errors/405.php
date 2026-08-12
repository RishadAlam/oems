<section class="error-page">
    <div class="page-shell">
        <div class="error-state">
            <span class="error-state__icon"><i class="ph ph-arrow-u-up-left" aria-hidden="true"></i></span>
            <p class="error-state__code">Action unavailable</p>
            <h1>That action is not available here.</h1>
            <p>The page cannot accept that request. Return to the previous page and use one of its available actions.</p>
            <a class="button button--primary" href="<?= e($recoveryUrl ?? '/') ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Return to the previous page</span></a>
        </div>
    </div>
</section>
