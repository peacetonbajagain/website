<?php /** @var array $p */ ?>
<article class="card h-100 border-0">
    <a href="<?= base_url('blog/' . esc($p['slug'], 'url')) ?>" class="d-block overflow-hidden rounded-top">
        <?php if (! empty($p['thumbnail'])): ?>
            <img src="<?= esc(post_img($p['thumbnail']), 'attr') ?>" alt="<?= esc($p['title'], 'attr') ?>" loading="lazy" class="card-img-top story-image rounded-top">
        <?php else: ?>
            <div class="card-img-top story-image rounded-top" aria-hidden="true"></div>
        <?php endif; ?>
    </a>
    <div class="card-body d-flex flex-column p-3 p-md-4">
        <?php if (! empty($p['category_title'])): ?><span class="small fw-bold text-uppercase text-primary mb-2"><?= esc($p['category_title']) ?></span><?php endif; ?>
        <h3 class="h5 fw-bold line-clamp-3 mb-3"><a href="<?= base_url('blog/' . esc($p['slug'], 'url')) ?>" class="text-decoration-none text-body"><?= esc($p['title']) ?></a></h3>
        <p class="small text-secondary mt-auto mb-0"><?= esc(time_ago($p['published_at'])) ?></p>
    </div>
</article>
