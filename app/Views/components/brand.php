<?php
$brandVariant = $brandVariant ?? 'default';
$brandCompact = $brandCompact ?? false;
$brandClass = $brandVariant === 'inverse' ? ' brand-mark--inverse' : '';
?>
<a class="brand-mark<?= $brandClass ?>" href="/" aria-label="OEMS home">
    <svg class="brand-mark__logo" viewBox="0 0 34 34" role="img" aria-hidden="true" focusable="false">
        <rect x="2" y="2" width="14" height="8" rx="4" fill="currentColor"/>
        <rect x="24" y="2" width="8" height="14" rx="4" fill="currentColor"/>
        <rect x="18" y="24" width="14" height="8" rx="4" fill="currentColor"/>
        <rect x="2" y="18" width="8" height="14" rx="4" fill="currentColor"/>
        <circle cx="17" cy="17" r="3.5" fill="currentColor"/>
    </svg>
    <?php if (!$brandCompact): ?>
        <span class="brand-mark__wordmark">OEMS</span>
    <?php endif; ?>
</a>
<?php unset($brandVariant, $brandCompact, $brandClass); ?>
