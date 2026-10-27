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
   <style>
        :root {
            --brand-ink: #0f172a;
            --brand-accent: #1d4ed8;
            --brand-accent-soft: #dbeafe;
            --surface: rgba(255, 255, 255, 0.83);
            --surface-strong: #ffffff;
            --page-bg: linear-gradient(180deg, #f5f7fb 0%, #eef4f2 100%);
            --page-line: rgba(15, 23, 42, 0.08);
            --page-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
            --bs-body-font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        :root[data-theme="dark"] { color-scheme: dark; --brand-ink: #e5eef9; --surface: rgba(15, 23, 42, 0.76); --surface-strong: #111827; --page-bg: linear-gradient(180deg, #06131d 0%, #0d1a23 100%); --page-line: rgba(148, 163, 184, 0.18); --page-shadow: 0 14px 30px rgba(2, 6, 23, 0.55); }
        body { background: var(--page-bg); color: var(--brand-ink); }
        .site-brand { letter-spacing: -0.04em; }
        header.bg-body { background: rgba(255,255,255,0.72) !important; backdrop-filter: blur(12px); border-bottom: 1px solid var(--page-line) !important; box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03); }
        :root[data-theme="dark"] header.bg-body { background: rgba(15, 23, 42, 0.7) !important; }
        .nav-link { color: var(--bs-secondary-color); transition: color 0.2s ease, transform 0.2s ease; }
        .nav-link:hover { color: var(--bs-body-color); transform: translateY(-1px); }
        .hero-overlay { inset: 0; background: linear-gradient(180deg, rgba(3,7,18,0.06), rgba(3,7,18,0.8)); }
        .story-image { display: block; aspect-ratio: 16 / 9; background: linear-gradient(135deg, #0f172a, #111827); object-fit: cover; width: 100%; border-radius: 1.1rem; }
        .card { border: 1px solid var(--page-line); border-radius: 1rem; box-shadow: var(--page-shadow); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card:hover { transform: translateY(-2px); }
        .btn-primary { background: linear-gradient(135deg, #0d6efd, #2563eb); border: 0; box-shadow: 0 10px 20px rgba(37,99,235,0.22); }
        .btn-primary:hover { filter: brightness(1.04); }
        .line-clamp-2, .line-clamp-3 { display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-2 { -webkit-line-clamp: 2; }
        .line-clamp-3 { -webkit-line-clamp: 3; }
        .prose h2 { font-size: 1.5rem; font-weight: 700; margin: 1.6em 0 .6em; }
        .prose h3 { font-size: 1.25rem; font-weight: 700; margin: 1.4em 0 .5em; }
        .prose p { margin: 1em 0; line-height: 1.8; }
        .prose ul { list-style: disc; padding-left: 1.5rem; margin: 1em 0; }
        .prose ol { list-style: decimal; padding-left: 1.5rem; margin: 1em 0; }
        .prose a { color: var(--bs-link-color); text-decoration: underline; }
        .prose img { max-width: 100%; height: auto; border-radius: .5rem; margin: 1em 0; }
        .prose blockquote { border-left: 4px solid var(--bs-primary); padding-left: 1rem; font-style: italic; color: var(--bs-secondary-color); }
        .pager ul { justify-content: center; flex-wrap: wrap; }
        .comment-avatar { width: 2.5rem; height: 2.5rem; }
    </style>
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
<script defer src="<?= base_url('js/bootstrap-min.js') ?>"></script>
</body>
</html>
