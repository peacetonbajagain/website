<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3"><a href="<?= base_url('admin/pages/new') ?>" class="btn btn-primary">+ New page</a></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th class="ps-3">Title</th><th>URL</th><th>Status</th><th class="text-end pe-3">Actions</th></tr></thead><tbody>
<?php foreach ($pages as $p): ?>
    <tr><td class="ps-3 fw-semibold"><?= esc($p['title']) ?></td><td class="text-secondary">/pages/<?= esc($p['slug']) ?></td><td><?= $p['status'] ? 'Published' : 'Hidden' ?></td>
    <td class="text-end text-nowrap pe-3"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/pages/edit/' . (int) $p['id']) ?>">Edit</a>
    <form method="post" action="<?= base_url('admin/pages/delete/' . (int) $p['id']) ?>" class="d-inline" onsubmit="return confirm('Delete page?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger ms-1">Delete</button></form></td></tr>
<?php endforeach; if (! $pages): ?><tr><td colspan="4" class="text-center text-secondary py-4">No pages yet (try About, Contact, Privacy).</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
