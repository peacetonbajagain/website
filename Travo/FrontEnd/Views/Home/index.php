<?= $this->extend('Travo\FrontEnd\Views\Layout\App') ?>
<?= $this->section('content') ?>
<div class="container-xl py-4 py-lg-5">

    <?php if (!empty($breaking)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3 py-2 mb-4 overflow-hidden">
            <span class="fw-bold text-uppercase small flex-shrink-0">Breaking</span>
            <span class="text-truncate">
                <?php foreach ($breaking as $b): ?>
                    <a href="<?= base_url('blog/' . esc($b['slug'], 'url')) ?>"
                        class="link-light me-4"><?= esc($b['title']) ?></a>
                <?php endforeach; ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if (empty($hero)): ?>
        <div class="text-center py-5 text-secondary">No stories published yet. Log in at <a
                href="<?= base_url('admin') ?>">/admin</a> to add your first post.</div>
    <?php else: ?>
        <!-- Hero & Trending -->
        <div class="row g-4 mb-5">
            <article class="col-lg-8 position-relative overflow-hidden rounded">
                <?php if (!empty($hero['thumbnail_path'])): ?>
                    <img src="<?= esc(post_img($hero['thumbnail_path']), 'attr') ?>" alt="<?= esc($hero['title'], 'attr') ?>"
                        class="w-100 story-image">
                <?php else: ?>
                    <div class="w-100 story-image" aria-hidden="true"></div>
                <?php endif; ?>
                <div
                    class="hero-overlay position-absolute inset-0 bottom-0 d-flex flex-column justify-content-end p-3 p-md-4 text-white">
                    <?php if (!empty($hero['category_title'])): ?><span
                            class="small fw-bold text-uppercase text-info"><?= esc($hero['category_title']) ?></span><?php endif; ?>
                    <h1 class="display-6 fw-bold mt-2"><a class="link-light text-decoration-none"
                            href="<?= base_url('blog/' . esc($hero['slug'], 'url')) ?>"><?= esc($hero['title']) ?></a></h1>
                    <p class="small text-light line-clamp-2 mb-0"><?= esc($hero['excerpt']) ?></p>
                </div>
            </article>
            <aside class="col-lg-4">
                <div class="bg-body-tertiary rounded h-100 p-4">
                    <h2 class="h5 fw-bold mb-3">Trending</h2>
                    <?php foreach ($trending as $i => $t): ?>
                        <a href="<?= base_url('blog/' . esc($t['slug'], 'url')) ?>"
                            class="d-flex gap-3 border-bottom py-3 text-decoration-none text-body">
                            <span class="fs-4 fw-bold text-secondary"><?= $i + 1 ?></span>
                            <span class="small fw-semibold"><?= esc($t['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>

        <!-- Original Latest Section -->
        <section class="mb-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 fw-bold mb-0">Latest</h2><a href="<?= base_url('blog') ?>" class="small fw-semibold">All news
                    &rarr;</a>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                <?php foreach ($latest as $p): ?>        <?= view('Travo\FrontEnd\Views\Partials\card', ['p' => $p]) ?>    <?php endforeach; ?>
            </div>
        </section>

        <!-- Dynamic Category Sections -->
        <?php
        // Automatically groups your existing $latest array items by category_title
        $grouped_posts = array_reduce($latest, function ($result, $item) {
            $catName = !empty($item['category_title']) ? $item['category_title'] : 'General';
            $result[$catName][] = $item;
            return $result;
        }, []);
        ?>

        <?php if (!empty($grouped_posts)): ?>
            <?php foreach ($grouped_posts as $category_name => $posts): ?>
                <section class="mb-5">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h2 class="h4 fw-bold mb-0 text-capitalize"><?= esc($category_name) ?></h2>
                    </div>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                        <?php foreach ($posts as $p): ?>
                            <?= view('Travo\FrontEnd\Views\Partials\card', ['p' => $p]) ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>
<?= $this->endSection() ?>