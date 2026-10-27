<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<section class="card card-body shadow-sm mb-4">
    <div class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center justify-content-sm-between">
        <div>
            <h2 class="h6 fw-bold">Upload images</h2>
            <p class="small text-secondary mb-0">JPG, PNG, GIF, or WebP. Maximum 5 MB each, 12 at a time.</p>
        </div>
        <form method="post" action="<?= base_url('admin/gallery/upload') ?>" enctype="multipart/form-data" class="d-flex flex-wrap align-items-center gap-2">
            <?= csrf_field() ?>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required class="form-control form-control-sm" style="max-width: 20rem">
            <button class="btn btn-success btn-sm">Upload</button>
        </form>
    </div>
</section>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h6 fw-bold text-secondary text-uppercase mb-0">Library</h2>
    <span class="small text-secondary"><?= count($images) ?> images</span>
</div>

<?php if ($images): ?>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 g-3">
        <?php foreach ($images as $image): ?>
            <article class="col"><div class="card h-100 shadow-sm">
                <img src="<?= esc($image['url'], 'attr') ?>" alt="<?= esc($image['name'], 'attr') ?>" loading="lazy" class="card-img-top object-fit-cover" style="aspect-ratio: 1">
                <div class="card-body d-flex flex-column gap-2 p-3">
                    <?php if (str_starts_with($image['path'], 'uploads/')): ?>
                    <form method="post" action="<?= base_url('admin/gallery/rename') ?>" class="d-flex gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
                        <input type="text" name="name" value="<?= esc(pathinfo($image['name'], PATHINFO_FILENAME), 'attr') ?>" maxlength="100" aria-label="Filename" class="form-control form-control-sm" required>
                        <span class="small text-secondary align-self-center">.<?= esc(pathinfo($image['name'], PATHINFO_EXTENSION)) ?></span>
                        <button class="btn btn-outline-secondary btn-sm" title="Save filename" aria-label="Save filename">Save</button>
                    </form>
                    <?php endif; ?>
                    <p class="small text-secondary mb-0"><?= (int) $image['width'] ?> × <?= (int) $image['height'] ?> · <?= esc(number_format($image['size'] / 1024, 0)) ?> KB</p>
                    <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                        <button type="button" data-copy-id="<?= (int) $image['id'] ?>" class="btn btn-link btn-sm p-0 text-success text-decoration-none">Copy ID</button>
                        <?php if (str_starts_with($image['path'], 'uploads/')): ?>
                        <form method="post" action="<?= base_url('admin/gallery/delete') ?>" onsubmit="return confirm('Delete this image from the library?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $image['id'] ?>">
                            <button class="btn btn-link btn-sm p-0 text-danger text-decoration-none">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div></article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="border rounded p-5 text-center">
        <h2 class="h5 fw-bold">No images yet</h2>
        <p class="text-secondary mb-0">Upload images here to keep your newsroom media in one place.</p>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-copy-id]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copyId);
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = 'Copy ID'; }, 1400);
        } catch {
            button.textContent = 'Copy unavailable';
        }
    });
});
</script>
<?= $this->endSection() ?>