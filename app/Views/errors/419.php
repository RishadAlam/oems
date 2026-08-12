<section class="error-page">
    <div class="page-shell">
        <div class="error-state">
            <span class="error-state__icon"><i class="ph ph-clock-countdown" aria-hidden="true"></i></span>
            <p class="error-state__code">Session expired</p>
            <h1>Your form session expired.</h1>
            <p>Nothing was submitted. Return to the previous page, review your information, and submit the form again.</p>
            <a class="button button--primary" href="<?= e($recoveryUrl ?? '/') ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><span>Return to the previous page</span></a>
        </div>
    </div>
</section>
