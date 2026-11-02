<?= $this->extend('Travo\\FrontEnd\\Views\\Layout\\App') ?>
<?= $this->section('content') ?>
<div class="container py-5" style="max-width: 52rem">
    <header class="mb-4">
        <h1 class="h2 fw-bold">Submit a story</h1>
        <p class="text-secondary mb-0">Submissions stay unpublished until reviewed.</p>
    </header>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <div><?= esc($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('blog/submit') ?>" enctype="multipart/form-data" class="vstack gap-3">
        <?= csrf_field() ?>
        <div>
            <label for="story-title" class="form-label">Title</label>
            <input id="story-title" name="title" value="<?= esc(old('title'), 'attr') ?>" maxlength="255" required class="form-control">
        </div>
        <div>
            <label for="story-excerpt" class="form-label">Summary</label>
            <textarea id="story-excerpt" name="excerpt" rows="2" maxlength="500" class="form-control"><?= esc(old('excerpt')) ?></textarea>
        </div>
        <div>
            <label class="form-label" for="story-content-surface">Story</label>
            <?= view('Travo\\FrontEnd\\Views\\Partials\\rich_editor', [
                'editorId' => 'story-content',
                'editorName' => 'content',
                'editorContent' => old('content'),
            ]) ?>
        </div>
        <div>
            <label for="story-thumbnail" class="form-label">Cover image (optional)</label>
            <input id="story-thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control">
            <div class="form-text">JPG, PNG, GIF, or WebP; maximum 5 MB.</div>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Send for review</button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>