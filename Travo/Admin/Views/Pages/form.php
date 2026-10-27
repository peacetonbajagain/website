<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<?php $v = fn($k, $d = '') => old($k, $page[$k] ?? $d); $id = $page['id'] ?? null; ?>
<form method="post" action="<?= base_url($id ? 'admin/pages/update/' . (int) $id : 'admin/pages/create') ?>" class="card card-body shadow-sm" style="max-width: 52rem">
    <?= csrf_field() ?>
    <label class="form-label fw-semibold">Title<input name="title" required value="<?= esc($v('title'), 'attr') ?>" class="form-control mt-1"></label>
    <label class="form-label fw-semibold">Content (HTML)<textarea name="content" rows="14" class="form-control font-monospace mt-1"><?= esc($v('content')) ?></textarea></label>
    <label class="form-label fw-semibold">SEO title<input name="meta_title" value="<?= esc($v('meta_title'), 'attr') ?>" class="form-control mt-1"></label>
    <label class="form-label fw-semibold">SEO description<textarea name="meta_desc" rows="2" class="form-control mt-1"><?= esc($v('meta_desc')) ?></textarea></label>
    <label class="form-label fw-semibold">Status<select name="status" class="form-select mt-1"><option value="1" <?= (string) $v('status', 1) === '1' ? 'selected' : '' ?>>Published</option><option value="0" <?= (string) $v('status', 1) === '0' ? 'selected' : '' ?>>Hidden</option></select></label>
    <button class="btn btn-primary align-self-start">Save page</button>
</form>
<?= $this->endSection() ?>
