<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<section class="border-bottom pb-4 mb-4">
    <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-end justify-content-between">
        <form method="get" action="<?= base_url('admin/gallery') ?>" class="row g-2 align-items-end">
            <div class="col-12 col-sm-auto">
                <label for="media-search" class="form-label small fw-semibold mb-1">Search media</label>
                <input id="media-search" type="search" name="q" value="<?= esc($search, 'attr') ?>" class="form-control form-control-sm" placeholder="Filename" autocomplete="off">
            </div>
            <div class="col-8 col-sm-auto">
                <label for="media-type" class="form-label small fw-semibold mb-1">Type</label>
                <select id="media-type" name="type" class="form-select form-select-sm">
                    <option value="">All media</option>
                    <?php foreach (['image' => 'Images', 'video' => 'Videos', 'pdf' => 'PDFs'] as $value => $label): ?>
                        <option value="<?= esc($value, 'attr') ?>" <?= $type === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filter</button></div>
            <?php if ($search !== '' || $type !== ''): ?><div class="col-auto"><a href="<?= base_url('admin/gallery') ?>" class="btn btn-sm btn-link">Clear</a></div><?php endif; ?>
        </form>
        <form method="post" action="<?= base_url('admin/gallery/upload') ?>" enctype="multipart/form-data" data-media-upload class="d-flex flex-wrap align-items-end gap-2">
            <?= csrf_field() ?>
            <div>
                <label for="media-files" class="form-label small fw-semibold mb-1">Add media</label>
                <input id="media-files" type="file" name="images[]" accept="image/*,video/*,application/pdf" multiple required class="form-control form-control-sm">
            </div>
            <button class="btn btn-primary btn-sm" type="submit">Upload</button>
        </form>
    </div>
    <p class="small text-secondary mt-2 mb-0" data-upload-feedback aria-live="polite"></p>
</section>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h6 fw-bold text-secondary text-uppercase mb-0">Media library</h2>
    <span class="small text-secondary"><?= count($images) ?> files</span>
</div>

<?php if ($images): ?>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-5 g-3">
        <?php foreach ($images as $image): ?>
            <article class="col"><div class="card h-100 shadow-sm">
                <?php if (($image['type'] ?? 'image') === 'video'): ?>
                    <video src="<?= esc($image['url'], 'attr') ?>" controls preload="metadata" class="card-img-top object-fit-cover" style="aspect-ratio: 1" aria-label="<?= esc($image['name'], 'attr') ?>"></video>
                <?php elseif (($image['type'] ?? 'image') === 'pdf'): ?>
                    <a href="<?= esc($image['url'], 'attr') ?>" target="_blank" rel="noopener" class="d-flex align-items-center justify-content-center bg-body-tertiary text-danger text-decoration-none" style="aspect-ratio: 1" aria-label="Open <?= esc($image['name'], 'attr') ?>">
                        <span class="display-6 fw-bold">PDF</span>
                    </a>
                <?php else: ?>
                    <img src="<?= esc($image['url'], 'attr') ?>" alt="<?= esc($image['name'], 'attr') ?>" loading="lazy" class="card-img-top object-fit-cover" style="aspect-ratio: 1">
                <?php endif; ?>
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
        <h2 class="h5 fw-bold">No media found</h2>
        <p class="text-secondary mb-0">Try another search or add image, video, or PDF files.</p>
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
<script>
document.querySelector('[data-media-upload]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    const feedback = document.querySelector('[data-upload-feedback]');
    button.disabled = true;
    button.textContent = 'Uploading...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const result = await response.json();
        feedback.textContent = result.message || 'Upload failed.';
        feedback.className = `small mt-2 mb-0 ${response.ok ? 'text-success' : 'text-danger'}`;
        if (response.ok && result.status === 200) {
            form.reset();
            window.location.reload();
        }
    } catch {
        feedback.textContent = 'The upload could not be completed.';
        feedback.className = 'small mt-2 mb-0 text-danger';
    } finally {
        button.disabled = false;
        button.textContent = 'Upload';
    }
});
</script>
<?= $this->endSection() ?>