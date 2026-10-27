<?= $this->extend('Travo\Admin\Views\Layout\admin') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Filter posts">
        <a href="<?= base_url('admin/posts') ?>" class="btn btn-outline-secondary">All</a>
        <a href="<?= base_url('admin/posts?status=1') ?>" class="btn btn-outline-secondary">Published</a>
        <a href="<?= base_url('admin/posts?status=0') ?>" class="btn btn-outline-secondary">Drafts</a>
    </div>
    <a href="<?= base_url('admin/posts/new') ?>" class="btn btn-primary">+ New post</a>
</div>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th class="ps-3">Title</th><th>Category</th><th>Status</th><th>Views</th><th class="text-end pe-3">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
        <tr>
            <td class="ps-3 fw-semibold"><?= esc($p['title']) ?>
                <?php if ($p['is_featured']): ?><span class="badge text-bg-warning">Featured</span><?php endif; ?>
                <?php if ($p['is_breaking_news']): ?><span class="badge text-bg-danger">Breaking</span><?php endif; ?></td>
            <td><?= esc($p['category_title'] ?? '—') ?></td>
            <td><?= $p['status'] ? 'Published' : 'Draft' ?></td>
            <td><?= (int) $p['view_count'] ?></td>
            <td class="text-end text-nowrap pe-3">
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/posts/edit/' . (int) $p['id']) ?>">Edit</a>
                <form method="post" action="<?= base_url('admin/posts/delete/' . (int) $p['id']) ?>" class="d-inline" onsubmit="return confirm('Delete this post?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger ms-1">Delete</button></form>
            </td>
        </tr>
    <?php endforeach; if (! $posts): ?><tr><td class="text-center text-secondary py-4" colspan="5">No posts.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= $pager->links() ?>
<?= $this->endSection() ?>
