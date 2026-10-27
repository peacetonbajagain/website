<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <title><?= esc($this->renderSection('title')) ?> · <?= esc(site_name()) ?></title>
    <script>
        try {
            const savedTheme = localStorage.getItem('travo-theme');
            document.documentElement.dataset.theme = savedTheme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.bsTheme = document.documentElement.dataset.theme;
        } catch (error) {
            document.documentElement.dataset.theme = 'light';
            document.documentElement.dataset.bsTheme = 'light';
        }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bs-success: #147d5a; --bs-success-rgb: 20, 125, 90; }
        [data-bs-theme="dark"] { --bs-success: #58c99b; --bs-success-rgb: 88, 201, 155; }
        body { min-height: 100vh; }
        .auth-surface { background: var(--bs-body-bg); border-color: var(--bs-border-color) !important; }
        .auth-muted { color: var(--bs-secondary-color); }
        .auth-accent { color: var(--bs-success-text-emphasis); }
    </style>
    <?= $this->renderSection('head') ?>
</head>
<body class="bg-body-tertiary text-body">
    <header class="container-xl d-flex align-items-center justify-content-between py-4">
        <a href="<?= base_url() ?>" class="fs-5 fw-bold text-decoration-none text-body"><?= esc(site_name()) ?></a>
        <button type="button" data-theme-toggle aria-pressed="false" class="btn btn-sm btn-outline-secondary">Dark mode</button>
    </header>
    <?= $this->renderSection('main') ?>
    <script defer src="<?= base_url('js/theme.js') ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>