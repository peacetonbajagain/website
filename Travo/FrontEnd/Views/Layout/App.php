<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="RC Dahal">
    <meta name="robots" content="index, follow">
    <title><?= esc($title ?? site_name()) ?></title>
    <?php $description = ! empty($metaDesc) ? $metaDesc : (setting('Travo.siteDescription') ?? ''); ?>
    <?php if ($description !== ''): ?><meta name="description" content="<?= esc(mb_substr($description, 0, 160), 'attr') ?>"><?php endif; ?>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="app-url" content="<?= base_url() ?>">
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
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
    <link rel="stylesheet" href="<?= base_url('css/rich-editor.css') ?>">
      <link rel="stylesheet" href="<?= base_url('css/style.css') ?> ">
</head>
<body class="min-vh-100 d-flex flex-column">
<header class="bg-body border-bottom sticky-top">
    <div class="container-xl py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <a href="<?= base_url() ?>" class="site-brand fs-4 fw-bold text-decoration-none text-body"><?= esc(site_name()) ?></a>
            <?php if (site_tagline() !== ''): ?><span class="small text-secondary d-none d-md-inline"><?= esc(site_tagline()) ?></span><?php endif; ?>
            <form action="<?= base_url('search') ?>" method="get" class="order-3 order-lg-0 flex-grow-1" style="max-width: 22rem">
                <input type="search" name="q" value="<?= esc($q ?? '', 'attr') ?>" placeholder="Search news…" class="form-control form-control-sm">
            </form>
            <div class="d-flex align-items-center gap-2 small fw-semibold">
            <button type="button" data-theme-toggle aria-pressed="false" class="btn btn-sm btn-outline-secondary">Dark mode</button>
            <?php if (auth()->loggedIn()): ?>
                <?php $currentUser = auth()->user(); ?>
                <span class="d-none d-md-inline text-secondary"><?= esc($currentUser->username ?? $currentUser->email) ?></span>
                <a href="<?= base_url('blog/submissions') ?>" class="btn btn-sm btn-outline-secondary">My stories</a>
                <a href="<?= base_url('blog/submit') ?>" class="btn btn-sm btn-outline-primary">Submit story</a>
                <?php if ($currentUser->can('admin.access')): ?><a href="<?= base_url('admin') ?>" class="btn btn-sm btn-success">Admin</a><?php endif; ?>
                <a href="<?= base_url('logout') ?>" class="btn btn-sm btn-outline-secondary">Sign out</a>
            <?php else: ?>
                <a href="<?= base_url('login') ?>" class="btn btn-sm btn-outline-secondary">Sign in</a>
                <?php if (setting('Auth.allowRegistration')): ?><a href="<?= base_url('register') ?>" class="btn btn-sm btn-link d-none d-sm-inline">Register</a><?php endif; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <nav class="container-xl nav flex-nowrap gap-3 overflow-x-auto pb-2 small fw-semibold" aria-label="Main navigation">
        <a href="<?= base_url() ?>" class="nav-link px-0 text-nowrap">Home</a>
        <?php foreach (menu_categories() as $c): ?>
            <a href="<?= base_url('blog/category/' . esc($c['slug'], 'url')) ?>" class="nav-link px-0 text-nowrap"><?= esc($c['title']) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<?php if (session()->getFlashdata('success')): ?><div class="container-xl pt-3"><div class="alert alert-success mb-0"><?= esc(session()->getFlashdata('success')) ?></div></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="container-xl pt-3"><div class="alert alert-danger mb-0"><?= esc(session()->getFlashdata('error')) ?></div></div><?php endif; ?>

<main class="flex-grow-1"><?= $this->renderSection('content') ?></main>

<footer class="bg-body border-top mt-5">
    <div class="container-xl py-4 d-flex flex-wrap align-items-center justify-content-between gap-3 small text-secondary">
        <span>&copy; <?= date('Y') ?> <?= esc(site_name()) ?></span>
        <span class="d-flex flex-wrap gap-3">
            <?php if (setting('Travo.contactEmail')): ?><a href="mailto:<?= esc(setting('Travo.contactEmail'), 'attr') ?>"><?= esc(setting('Travo.contactEmail')) ?></a><?php endif; ?>
            <?php foreach (footer_pages() as $fp): ?>
                <a href="<?= base_url('pages/' . esc($fp['slug'], 'url')) ?>"><?= esc($fp['title']) ?></a>
            <?php endforeach; ?>
        </span>
    </div>
</footer>
<script>
    var base_url = '<?= base_url() ?>';
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';
</script>
<script defer src="<?= base_url('js/theme.js') ?>"></script>
<script defer src="<?= base_url('js/rich-editor.js') ?>"></script>
<script defer src="<?= base_url('js/bootstrap-min.js') ?>"></script>
</body>
</html>
