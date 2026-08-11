<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= e($pageTitle ?? 'Maintenance') ?> | OEMS</title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/app.css?v=20260812-form-separators">
    <script src="/assets/js/app.js?v=20260812-form-system" defer></script>
</head>
<body class="min-h-[100dvh] bg-[var(--surface-soft)] text-[var(--ink)] antialiased">
    <main id="main-content" class="grid min-h-[100dvh] place-items-center px-5 py-10">
        <?= $content ?>
    </main>
</body>
</html>
