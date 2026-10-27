<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<?php $v = fn($k, $d = '') => old($k, $post[$k] ?? $d); $id = $post['id'] ?? null; $selectedCategoryIds = old('category_ids', $categoryIds); ?>
<form method="post" enctype="multipart/form-data" action="<?= base_url($id ? 'admin/posts/update/' . (int) $id : 'admin/posts/create') ?>" class="row g-4">
    <?= csrf_field() ?>
    <div class="col-12 col-lg-8"><div class="card shadow-sm"><div class="card-body vstack gap-3">
        <label class="form-label fw-semibold mb-0">Title<input name="title" required value="<?= esc($v('title'), 'attr') ?>" class="form-control mt-1"></label>
        <label class="form-label fw-semibold mb-0">Excerpt<textarea name="excerpt" rows="2" class="form-control mt-1"><?= esc($v('excerpt')) ?></textarea></label>
        <label class="form-label fw-semibold mb-0">Content (HTML allowed: p, h2-h4, lists, a, img, blockquote, table…)
            <textarea name="content" rows="16" class="form-control font-monospace mt-1"><?= esc($v('content')) ?></textarea></label>
        <label class="form-label fw-semibold mb-0">SEO title<input name="meta_title" value="<?= esc($v('meta_title'), 'attr') ?>" class="form-control mt-1"></label>
        <label class="form-label fw-semibold mb-0">SEO description<textarea name="meta_desc" rows="2" class="form-control mt-1"><?= esc($v('meta_desc')) ?></textarea></label>
    </div></div></div>
    <div class="col-12 col-lg-4"><div class="card shadow-sm"><div class="card-body vstack gap-3">
        <label class="form-label fw-semibold mb-0">Status
            <select name="status" class="form-select mt-1"><option value="0" <?= (string) $v('status', 0) === '0' ? 'selected' : '' ?>>Draft</option><option value="1" <?= (string) $v('status', 0) === '1' ? 'selected' : '' ?>>Published</option></select></label>
        <fieldset class="mb-0">
            <legend class="form-label fw-semibold mb-1">Categories</legend>
            <div class="border rounded p-2 vstack gap-1" style="max-height: 12rem; overflow-y: auto">
                <?php foreach ($cats as $c): ?>
                    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], array_map('intval', (array) $selectedCategoryIds), true) ? 'checked' : '' ?>> <span class="form-check-label"><?= esc($c['title']) ?></span></label>
                <?php endforeach; ?>
                <?php if (! $cats): ?><span class="small text-secondary">Create a category first.</span><?php endif; ?>
            </div>
        </fieldset>
        <label class="form-label fw-semibold mb-0">Author name<input name="author_name" value="<?= esc($v('author_name'), 'attr') ?>" class="form-control mt-1"></label>
        <div class="vstack gap-2">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1" <?= $v('is_featured') ? 'checked' : '' ?>> <span class="form-check-label">Featured (homepage hero)</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_breaking_news" value="1" <?= $v('is_breaking_news') ? 'checked' : '' ?>> <span class="form-check-label">Breaking news</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_trending" value="1" <?= $v('is_trending') ? 'checked' : '' ?>> <span class="form-check-label">Trending</span></label>
        </div>
        <label class="form-label fw-semibold mb-0">Thumbnail (JPG, PNG, GIF, or WebP; up to 5 MB)<input type="file" name="thumbnail" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control mt-1"></label>
        <input type="hidden" name="thumbnail_id" value="<?= esc($v('thumbnail_id'), 'attr') ?>">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#media-picker">Choose from media library</button>
        <?php if (! empty($post['thumbnail_id']) && ! empty($post['thumbnail_path'])): ?>
            <div class="border rounded p-2">
            <img src="<?= esc(post_img($post['thumbnail_path']), 'attr') ?>" alt="Current thumbnail" class="img-fluid rounded mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remove_thumbnail" value="1" id="remove-thumbnail" <?= old('remove_thumbnail') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="remove-thumbnail">Remove current image</label>
                </div>
                <div class="form-text">Removes it from this post; the media file stays in the library.</div>
            </div>
        <?php endif; ?>
        <button class="btn btn-primary">Save post</button>
    </div>
    </div></div>
</form>
<div class="modal fade" id="media-picker" tabindex="-1" aria-labelledby="media-picker-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="media-picker-title">Choose a thumbnail</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <label class="visually-hidden" for="media-search">Search media</label>
            <input id="media-search" type="search" class="form-control mb-3" placeholder="Search filenames">
            <?php if ($images): ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 g-3" data-media-grid>
                    <?php foreach ($images as $image): ?>
                        <div class="col" data-media-item data-name="<?= esc(strtolower($image['name']), 'attr') ?>">
                            <button type="button" class="btn btn-outline-secondary p-2 w-100 h-100 text-start" data-media-id="<?= (int) $image['id'] ?>">
                                <img src="<?= esc($image['url'], 'attr') ?>" alt="" loading="lazy" class="w-100 object-fit-cover rounded mb-2" style="aspect-ratio: 1">
                                <span class="d-block small text-truncate"><?= esc($image['name']) ?></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="small text-secondary mt-3 mb-0 d-none" data-media-empty>No matching images.</p>
            <?php else: ?>
                <p class="text-secondary mb-0">No images in the library yet. Upload images from the Media library page.</p>
            <?php endif; ?>
        </div>
    </div></div>
</div>
<script>
const mediaSearch = document.querySelector('#media-search');
mediaSearch?.addEventListener('input', () => {
    const query = mediaSearch.value.trim().toLowerCase();
    let visibleCount = 0;
    document.querySelectorAll('[data-media-item]').forEach((item) => {
        const visible = item.dataset.name.includes(query);
        item.classList.toggle('d-none', !visible);
        visibleCount += visible ? 1 : 0;
    });
    document.querySelector('[data-media-empty]')?.classList.toggle('d-none', visibleCount !== 0);
});
document.querySelectorAll('[data-media-id]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('[name="thumbnail_id"]').value = button.dataset.mediaId;
        bootstrap.Modal.getOrCreateInstance(document.querySelector('#media-picker')).hide();
    });
});
</script>
<?= $this->endSection() ?>
