<?php
$value = static fn (string $key): string => old_value($old, $key, (string) ($settings[$key] ?? ''));
$invalid = static fn (string $key): string => field_error($errors, $key) === null ? '' : ' aria-invalid="true" aria-describedby="' . str_replace('_', '-', $key) . '-error"';
$error = static function (string $key) use ($errors): void { if ($message = field_error($errors, $key)) echo '<p id="' . e(str_replace('_', '-', $key)) . '-error" class="field-error" role="alert">' . e($message) . '</p>'; };
?>
<div class="dashboard-page-heading organizer-page-heading"><div><p class="dashboard-kicker"><i class="ph ph-sliders-horizontal" aria-hidden="true"></i><span>Public platform identity</span></p><h1>Platform settings</h1><p>Manage only the public copy used across OEMS. Security, SMTP, and infrastructure stay environment-owned.</p></div></div>
<form class="dashboard-panel organizer-form mt-8" action="/admin/settings" method="post">
<input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
<?php if ($message = field_error($errors, 'settings')): ?><div class="form-alert" role="alert"><i class="ph ph-warning-circle" aria-hidden="true"></i><span><?= e($message) ?></span></div><?php endif; ?>
<section class="organizer-form__section" aria-labelledby="identity-settings"><div class="organizer-form__heading"><span><i class="ph ph-identification-badge" aria-hidden="true"></i></span><div><h2 id="identity-settings">Identity and contact</h2><p>These fields appear in navigation, account pages, and the public footer.</p></div></div>
<div class="grid gap-5 sm:grid-cols-2">
<div class="field-group"><label for="site_name">Site name</label><input id="site_name" name="site_name" maxlength="80" value="<?= $value('site_name') ?>" required<?= $invalid('site_name') ?>><?php $error('site_name'); ?></div>
<div class="field-group"><label for="contact_email">Public contact email</label><input id="contact_email" name="contact_email" type="email" maxlength="190" autocomplete="email" value="<?= $value('contact_email') ?>" required<?= $invalid('contact_email') ?>><?php $error('contact_email'); ?></div>
<div class="field-group"><label for="support_phone">Public support phone</label><input id="support_phone" name="support_phone" type="tel" maxlength="40" autocomplete="tel" value="<?= $value('support_phone') ?>" required<?= $invalid('support_phone') ?>><?php $error('support_phone'); ?></div>
<div class="field-group sm:col-span-2"><label for="site_tagline">Site tagline</label><input id="site_tagline" name="site_tagline" maxlength="160" value="<?= $value('site_tagline') ?>" required<?= $invalid('site_tagline') ?>><?php $error('site_tagline'); ?></div>
<div class="field-group sm:col-span-2"><label for="footer_blurb">Footer summary</label><textarea id="footer_blurb" name="footer_blurb" rows="3" maxlength="240" required<?= $invalid('footer_blurb') ?>><?= $value('footer_blurb') ?></textarea><?php $error('footer_blurb'); ?></div>
<div class="field-group sm:col-span-2"><label for="footer_location">Footer location</label><input id="footer_location" name="footer_location" maxlength="120" value="<?= $value('footer_location') ?>" required<?= $invalid('footer_location') ?>><?php $error('footer_location'); ?></div>
</div></section>
<section class="organizer-form__section" aria-labelledby="home-settings"><div class="organizer-form__heading"><span><i class="ph ph-house-line" aria-hidden="true"></i></span><div><h2 id="home-settings">Home page copy</h2><p>Keep the headline direct and the supporting copy concise.</p></div></div>
<div class="grid gap-5 sm:grid-cols-2">
<div class="field-group sm:col-span-2"><label for="home_hero_kicker">Hero kicker</label><input id="home_hero_kicker" name="home_hero_kicker" maxlength="80" value="<?= $value('home_hero_kicker') ?>" required<?= $invalid('home_hero_kicker') ?>><?php $error('home_hero_kicker'); ?></div>
<div class="field-group sm:col-span-2"><label for="home_hero_title">Hero title</label><input id="home_hero_title" name="home_hero_title" maxlength="100" value="<?= $value('home_hero_title') ?>" required<?= $invalid('home_hero_title') ?>><?php $error('home_hero_title'); ?></div>
<div class="field-group sm:col-span-2"><label for="home_hero_copy">Hero summary</label><textarea id="home_hero_copy" name="home_hero_copy" rows="3" maxlength="240" required<?= $invalid('home_hero_copy') ?>><?= $value('home_hero_copy') ?></textarea><?php $error('home_hero_copy'); ?></div>
<div class="field-group sm:col-span-2"><label for="default_seo_description">Default SEO description</label><textarea id="default_seo_description" name="default_seo_description" rows="3" maxlength="320" required<?= $invalid('default_seo_description') ?>><?= $value('default_seo_description') ?></textarea><?php $error('default_seo_description'); ?></div>
</div></section>
<div class="organizer-form__actions"><p><i class="ph ph-shield-check" aria-hidden="true"></i><span>Only these nine public values can be stored here.</span></p><button class="button button--primary" type="submit"><i class="ph ph-floppy-disk" aria-hidden="true"></i><span>Save settings</span></button></div>
</form>
