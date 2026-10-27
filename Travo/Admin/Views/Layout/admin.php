<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="RC Dahal">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="app-url" content="<?= base_url() ?>">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <title><?= esc($title ?? 'Workspace') ?> | <?= esc(site_name()) ?> Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        try {
            const savedTheme = localStorage.getItem('travo-theme');
            document.documentElement.dataset.theme = savedTheme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.dataset.theme = 'light';
        }
    </script>
      <link rel="stylesheet" href="<?= base_url('css/bootstrap.css') ?>">
    <?= $this->renderSection('styles') ?>
    <style>
        :root {
            --workspace-bg: linear-gradient(180deg, #f4f7fb 0%, #eef5f1 100%);
            --workspace-surface: rgba(255, 255, 255, 0.9);
            --workspace-line: rgba(15, 23, 42, 0.08);
            --workspace-ink: #16212b;
            --workspace-muted: #5b6b7a;
            --workspace-accent: #1d4ed8;
            --bs-body-font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        :root[data-theme="dark"] { color-scheme: dark; --workspace-bg: linear-gradient(180deg, #07131d 0%, #0c1721 100%); --workspace-surface: rgba(15, 23, 42, 0.82); --workspace-line: rgba(148, 163, 184, 0.18); --workspace-ink: #edf4ff; --workspace-muted: #9db0c4; --workspace-accent: #60a5fa; }
        body { min-height: 100vh; background: var(--workspace-bg); color: var(--workspace-ink); }
        .workspace-sidebar { min-height: 100vh; background: var(--workspace-surface); border-right: 1px solid var(--workspace-line); box-shadow: inset -1px 0 0 rgba(15, 23, 42, 0.02); }
        .workspace-header { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-color: var(--workspace-line) !important; }
        :root[data-theme="dark"] .workspace-header { background: rgba(15, 23, 42, 0.68); }
        .workspace-main { min-width: 0; }
        .nav-pills .nav-link { border-radius: 0.8rem; padding: 0.7rem 0.85rem; font-weight: 600; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, var(--workspace-accent), #2563eb); box-shadow: 0 10px 20px rgba(37,99,235,0.18); }
        .card, .table { border-color: var(--workspace-line) !important; }
        .card { border-radius: 1rem; box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06); }
        .btn-primary { background: linear-gradient(135deg, #0d6efd, #2563eb); border: 0; box-shadow: 0 10px 20px rgba(37,99,235,0.18); }
        .btn-primary:hover { filter: brightness(1.04); }
        :root[data-theme="dark"] .card, :root[data-theme="dark"] .table { --bs-card-bg: var(--workspace-surface); --bs-table-bg: var(--workspace-surface); color: var(--workspace-ink); }
        :root[data-theme="dark"] .table-light { --bs-table-bg: rgba(15, 23, 42, 0.96); --bs-table-color: var(--workspace-ink); }
        :root[data-theme="dark"] .text-secondary, :root[data-theme="dark"] .form-text { color: var(--workspace-muted) !important; }
        :root[data-theme="dark"] .form-control, :root[data-theme="dark"] .form-select { background-color: #0f172a; border-color: var(--workspace-line); color: var(--workspace-ink); }
        :root[data-theme="dark"] .table { --bs-table-color: var(--workspace-ink); --bs-table-border-color: var(--workspace-line); }
    </style>
</head>
<body class="antialiased">
<?php
$segment = service('uri')->getSegment(2);
$navigation = [
    '' => ['Overview', 'admin.access'],
    'settings' => ['Site settings', 'admin.settings'],
    'posts' => ['Posts', 'content.manage'],
    'categories' => ['Categories', 'content.manage'],
    'pages' => ['Pages', 'content.manage'],
    'comments' => ['Comments', 'comments.moderate'],
    'users' => ['Users', 'users.view'],
    'gallery' => ['Media library', 'media.manage'],
];
$adminUser = auth()->user();
$navigation = array_filter($navigation, static fn (array $item): bool => $adminUser->can($item[1]));
?>
<div class="container-fluid">
    <div class="row min-vh-100">
        <aside class="col-lg-2 d-none d-lg-flex flex-column p-3 workspace-sidebar">
            <a href="<?= base_url('admin') ?>" class="text-decoration-none text-reset border-bottom pb-3 px-2">
                <span class="d-block fs-5 fw-bold"><?= esc(site_name()) ?></span>
                <span class="d-block mt-1 small text-secondary">Editorial workspace</span>
            </a>
            <nav class="nav nav-pills flex-column gap-1 my-3" aria-label="Admin navigation">
                <?php foreach ($navigation as $path => [$label, $permission]): ?>
                    <?php $active = $segment === $path || $path === '' && $segment === null; ?>
                    <a href="<?= base_url($path === '' ? 'admin' : 'admin/' . $path) ?>" <?= $active ? 'aria-current="page"' : '' ?> class="nav-link <?= $active ? 'active' : 'text-body-secondary' ?>"><?= esc($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <a href="<?= base_url() ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm mt-auto">Open public site</a>
        </aside>

        <div class="col-12 col-lg-10 workspace-main px-0">
            <header class="workspace-header sticky-top border-bottom px-3 px-md-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="small fw-semibold text-success text-uppercase">Newsroom</div>
                    <h1 class="h5 mb-0 mt-1"><?= esc($title ?? 'Workspace') ?></h1>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-secondary d-none d-sm-inline"><?= esc($adminUser->username ?? '') ?></span>
                    <button type="button" data-theme-toggle aria-pressed="false" class="btn btn-sm btn-outline-secondary">Dark mode</button>
                    <a href="<?= base_url('logout') ?>" class="btn btn-sm btn-outline-danger">Sign out</a>
                </div>
            </header>

            <nav class="nav nav-pills flex-nowrap gap-1 overflow-x-auto d-lg-none border-bottom bg-body p-2" aria-label="Admin navigation">
                <?php foreach ($navigation as $path => [$label, $permission]): ?>
                    <?php $active = $segment === $path || $path === '' && $segment === null; ?>
                    <a href="<?= base_url($path === '' ? 'admin' : 'admin/' . $path) ?>" <?= $active ? 'aria-current="page"' : '' ?> class="nav-link text-nowrap <?= $active ? 'active' : 'text-body-secondary' ?>"><?= esc($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="container-fluid p-3 p-md-4 p-xl-5">
                <?php if (session()->getFlashdata('success')): ?><div role="status" class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?><div role="alert" class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
</div>
<script>
    var base_url = '<?= base_url() ?>';
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';
</script>
<script defer src="<?= base_url('js/theme.js') ?>"></script>
<script defer src="<?= base_url('js/bootstrap-min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
