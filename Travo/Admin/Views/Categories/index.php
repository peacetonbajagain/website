<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<form method="post" action="<?= base_url('admin/categories/create') ?>" class="card card-body shadow-sm mb-4">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-end">
        <div class="col-md-6"><label class="form-label fw-semibold">Title<input name="title" required class="form-control"></label></div>
        <div class="col-md-2"><label class="form-label fw-semibold">Order<input name="sort_order" type="number" value="0" class="form-control"></label></div>
        <div class="col-md-2"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="on_menu" value="1" checked> <span class="form-check-label">Show in menu</span></label></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
    </div>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th class="ps-3">Title</th><th>Slug</th><th>Status</th><th class="text-end pe-3">Actions</th></tr></thead><tbody>
<?php foreach ($cats as $c): ?>
    <tr><td class="ps-3 fw-semibold"><?= esc($c['title']) ?></td><td class="text-secondary"><?= esc($c['slug']) ?></td><td><?= $c['on_menu'] ? 'In menu' : 'Hidden' ?></td>
    <td class="text-end pe-3"><form method="post" action="<?= base_url('admin/categories/delete/' . (int) $c['id']) ?>" onsubmit="return confirm('Delete category?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
<?php endforeach; if (! $cats): ?><tr><td colspan="4" class="text-center text-secondary py-4">No categories yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
