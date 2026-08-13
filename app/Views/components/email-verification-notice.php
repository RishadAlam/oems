<?php if (is_array($currentUser ?? null) && empty($currentUser['email_verified_at'])): ?>
    <section
        class="email-verification-notice"
        aria-labelledby="email-verification-notice-heading"
        data-email-verification-notice
    >
        <span class="email-verification-notice__icon" aria-hidden="true">
            <i class="ph ph-envelope-simple-open"></i>
        </span>
        <div class="email-verification-notice__content">
            <p class="email-verification-notice__eyebrow">Account action required</p>
            <h2 id="email-verification-notice-heading">Verify your email address</h2>
            <p>Confirm email ownership to unlock every account action and receive important event updates.</p>
            <p class="email-verification-notice__email">
                <i class="ph ph-at" aria-hidden="true"></i>
                <span><?= e((string) ($currentUser['email'] ?? 'Account email unavailable')) ?></span>
            </p>
        </div>
        <div class="email-verification-notice__action">
            <form action="/verify-email/resend" method="post" data-form-kind="action">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="email" value="<?= e((string) ($currentUser['email'] ?? '')) ?>">
                <button class="button button--primary" type="submit" data-submit-label="Sending verification email…">
                    <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                    <span data-submit-text>Resend verification email</span>
                </button>
            </form>
        </div>
    </section>
<?php endif; ?>
